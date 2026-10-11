<?php

namespace App\Http\Controllers\Api\Full;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\Dates;
use App\Support\Money;
use App\Support\ProjectLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Full access API: list, read, create and update projects (no delete). */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::enum(ProjectStatus::class)]]);

        $page = Project::query()->visibleTo($request->user())
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->input('q').'%')->orWhere('code', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(min(max((int) $request->input('per_page', 50), 1), 100));

        return response()->json([
            'ok' => true,
            'total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'last_page' => $page->lastPage(),
            'projects' => collect($page->items())->map(fn (Project $p) => $this->data($p))->values(),
        ]);
    }

    public function show(Request $request, string $project)
    {
        return response()->json(['ok' => true, 'project' => $this->details(ProjectLookup::find($request->user(), $project))]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->allow($user->can('create', Project::class));
        $data = $this->validated($request, null);

        $project = DB::transaction(function () use ($request, $user, $data) {
            $project = Project::create($data + ['created_by' => $user->id]);
            $this->syncMembers($request, $project, $user, true);

            return $project;
        });

        return response()->json(['ok' => true, 'message' => "Project {$project->code} created.", 'project' => $this->details($project)], 201);
    }

    /** Partial update: only the sent fields change. */
    public function update(Request $request, string $project)
    {
        $user = $request->user();
        $project = ProjectLookup::find($user, $project);
        $this->allow($user->can('update', $project));
        $data = $this->validated($request, $project);

        DB::transaction(function () use ($request, $user, $project, $data) {
            $project->update($data);
            $this->syncMembers($request, $project, $user, false);
        });

        return response()->json(['ok' => true, 'message' => "Project {$project->code} updated.", 'project' => $this->details($project->fresh())]);
    }

    // ---------------------------------------------------------------------

    private function allow(bool $ok): void
    {
        if (! $ok) {
            throw new ApiError('forbidden', 'This user is not allowed to do that.', 403);
        }
    }

    private function validated(Request $request, ?Project $project): array
    {
        $create = $project === null;

        if ($request->has('code')) {
            $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        }
        if ($request->has('budget')) {
            $request->merge(['budget' => Money::parse((string) $request->input('budget'))]);
        }
        $dateRule = fn ($attr, $value, $fail) => $value === null || Dates::isValid($value) ? null : $fail("The {$attr} must be a date like 2026-09-24 (Gregorian) or 1405/07/02 (Jalali).");
        $req = $create ? 'required' : 'sometimes';

        $data = $request->validate([
            'name' => [$req, 'string', 'max:150'],
            'code' => [$req, 'string', 'max:12', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('projects')->ignore($project?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['sometimes', 'nullable', 'string', $dateRule],
            'end_date' => ['sometimes', 'nullable', 'string', $dateRule],
            'budget' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999999999999'],
            'currency' => ['sometimes', 'required', Rule::in(Money::CURRENCIES)],
            'phone1' => ['sometimes', 'nullable', 'string', 'max:30'],
            'phone2' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'developers' => ['sometimes', 'array'],
            'developers.*' => ['integer'],
            'customers' => ['sometimes', 'array'],
            'customers.*' => ['integer'],
        ]);
        unset($data['developers'], $data['customers']);

        foreach (['start_date', 'end_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = Dates::parse($data[$field])?->toDateString();
            }
        }
        if ($create) {
            $data += ['status' => ProjectStatus::Active->value, 'currency' => 'IRT'];
        } elseif (! $data && ! $request->hasAny(['developers', 'customers'])) {
            throw new ApiError('validation', 'Nothing to update. Send at least one field, for example {"status": "on_hold"}.');
        }

        return $data;
    }

    /**
     * Same rules as the web form. Admin sets developers and customers; a developer is always a member
     * and sets customers from their own customer list. When updating, only the sent lists change.
     */
    private function syncMembers(Request $request, Project $project, User $user, bool $creating): void
    {
        $setDevelopers = $user->isAdmin() && $request->has('developers');
        $setCustomers = $request->has('customers');
        if (! $creating && ! $setDevelopers && ! $setCustomers) {
            return;
        }

        $developers = match (true) {
            $setDevelopers => User::role(Role::Developer)->whereIn('id', (array) $request->input('developers'))->pluck('id')->all(),
            $creating => $user->isDeveloper() ? [$user->id] : [],
            default => $project->developers()->pluck('users.id')->all(),
        };
        $customers = $setCustomers
            ? User::query()->customersOf($user)->whereIn('id', (array) $request->input('customers'))->pluck('id')->all()
            : ($creating ? [] : $project->customers()->pluck('users.id')->all());

        $project->members()->sync(array_unique(array_merge($developers, $customers)));
        $user->flushProjectCache();
    }

    private function data(Project $p): array
    {
        return [
            'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'description' => $p->description,
            'status' => $p->status?->value, 'status_label' => $p->status?->label(),
            'start_date' => $p->start_date?->toDateString(), 'end_date' => $p->end_date?->toDateString(),
            'budget' => $p->budget, 'currency' => $p->currency,
            'phone1' => $p->phone1, 'phone2' => $p->phone2, 'email' => $p->email, 'website' => $p->website,
            'contact_person' => $p->contact_person, 'address' => $p->address, 'notes' => $p->notes,
            'url' => route('projects.show', $p),
        ];
    }

    private function details(Project $p): array
    {
        $p->load(['developers', 'customers']);

        return $this->data($p) + [
            'developers' => $p->developers->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'customers' => $p->customers->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ];
    }
}
