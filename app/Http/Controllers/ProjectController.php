<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\User;
use App\Support\Dates;
use App\Support\Grid;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);
        $user = $request->user();
        $openStatuses = array_map(fn ($s) => $s->value, array_filter(TicketStatus::cases(), fn ($s) => ! $s->isClosed()));

        $projects = Project::query()->visibleTo($user)
            ->withCount([
                'tickets as open_tickets_count' => fn ($q) => $q->whereIn('status', $openStatuses),
                'tickets',
                'developers',
                'customers',
            ])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('code', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'on_hold' THEN 1 ELSE 2 END")->orderBy('name')
            ->paginate(25)->withQueryString();

        $grid = Grid::make('projects', [
            'logo' => __('projects.fields.logo'),
            'name' => __('projects.fields.name'),
            'code' => __('projects.fields.code'),
            'status' => __('projects.fields.status'),
            'dates' => __('projects.fields.dates'),
            'budget' => __('projects.fields.budget'),
            'developers' => __('projects.fields.developers'),
            'customers' => __('projects.fields.customers'),
            'tickets' => __('projects.open_total_tickets'),
            'contact' => __('projects.fields.contact'),
        ], hidden: ['contact'], locked: ['name']);

        return view('projects.index', compact('projects', 'grid') + ['statuses' => ProjectStatus::cases()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Project::class);

        return view('projects.create', ['project' => new Project(['status' => ProjectStatus::Active, 'currency' => 'IRT'])] + $this->lookups($request->user()));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);
        $user = $request->user();
        $data = $this->validated($request);

        $project = DB::transaction(function () use ($request, $user, $data) {
            $project = Project::create($data + ['created_by' => $user->id]);
            $this->saveLogo($request, $project);
            $this->syncMembers($request, $project, $user);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', __('app.saved'));
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);
        $project->load(['developers', 'customers', 'sprints' => fn ($q) => $q->withCount('tickets')]);
        $byStatus = $project->tickets()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $spent = $project->tickets()->sum('cost');

        return view('projects.show', compact('project', 'byStatus', 'spent') + ['statuses' => TicketStatus::cases()]);
    }

    public function edit(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        return view('projects.edit', ['project' => $project->load('members')] + $this->lookups($request->user()));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $data = $this->validated($request, $project);

        DB::transaction(function () use ($request, $project, $data) {
            $project->update($data);
            $this->saveLogo($request, $project);
            $this->syncMembers($request, $project, $request->user());
        });

        return redirect()->route('projects.show', $project)->with('success', __('app.saved'));
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete(); // soft delete, tickets stay in the database

        return redirect()->route('projects.index')->with('success', __('app.deleted'));
    }

    // ---------------------------------------------------------------------

    private function validated(Request $request, ?Project $project = null): array
    {
        $request->merge([
            'code' => strtoupper((string) $request->input('code')),
            'budget' => Money::parse($request->input('budget')),
        ]);
        $dateRule = fn ($attr, $value, $fail) => Dates::isValid($value) ? null : $fail(__('validation.date', ['attribute' => __('projects.fields.'.$attr)]));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:12', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('projects')->ignore($project?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'string', $dateRule],
            'end_date' => ['nullable', 'string', $dateRule],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'currency' => ['required', Rule::in(Money::CURRENCIES)],
            'phone1' => ['nullable', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'developers' => ['nullable', 'array'],
            'developers.*' => ['integer'],
            'customers' => ['nullable', 'array'],
            'customers.*' => ['integer'],
        ]);

        $data['start_date'] = Dates::parse($data['start_date'] ?? null)?->toDateString();
        $data['end_date'] = Dates::parse($data['end_date'] ?? null)?->toDateString();
        unset($data['logo'], $data['developers'], $data['customers']);

        return $data;
    }

    private function saveLogo(Request $request, Project $project): void
    {
        if ($request->boolean('remove_logo') && $project->logo_path) {
            Storage::disk('public')->delete($project->logo_path);
            $project->update(['logo_path' => null]);
        }
        if ($request->hasFile('logo')) {
            if ($project->logo_path) {
                Storage::disk('public')->delete($project->logo_path);
            }
            $project->update(['logo_path' => $request->file('logo')->store('logos', 'public')]);
        }
    }

    /**
     * Admin: sets developers and customers.
     * Developer: is always a member, and sets customers from their own customer list.
     */
    private function syncMembers(Request $request, Project $project, User $user): void
    {
        $customerIds = User::query()->customersOf($user)->whereIn('id', (array) $request->input('customers', []))->pluck('id')->all();

        if ($user->isAdmin()) {
            $developerIds = User::role(Role::Developer)->whereIn('id', (array) $request->input('developers', []))->pluck('id')->all();
            $project->members()->sync(array_merge($developerIds, $customerIds));
        } else {
            $currentDevelopers = $project->developers()->pluck('users.id')->all();
            $project->members()->sync(array_unique(array_merge($currentDevelopers, [$user->id], $customerIds)));
        }
        $user->flushProjectCache();
    }

    private function lookups(User $user): array
    {
        return [
            'statuses' => ProjectStatus::cases(),
            'currencies' => Money::CURRENCIES,
            'allDevelopers' => $user->isAdmin() ? User::role(Role::Developer)->active()->orderBy('first_name')->get() : collect(),
            'allCustomers' => User::query()->customersOf($user)->active()->orderBy('first_name')->get(),
        ];
    }
}
