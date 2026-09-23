<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Concerns\SavesAvatar;
use App\Models\Project;
use App\Models\User;
use App\Support\Grid;
use App\Support\UserRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Developers manage their own customers: customers on their projects, or
 * customers they created. They can only assign them to their own projects.
 */
class CustomerController extends Controller
{
    use SavesAvatar;

    public function index(Request $request)
    {
        $developer = $request->user();
        $myProjectIds = $developer->accessibleProjectIds();

        $customers = User::query()->customersOf($developer)
            ->with(['projects' => fn ($q) => $q->whereIn('projects.id', $myProjectIds)])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)->orWhere('mobile', 'like', $term));
            })
            ->orderBy('first_name')->paginate(25)->withQueryString();

        $grid = Grid::make('customers', [
            'avatar' => __('users.fields.avatar'),
            'name' => __('users.fields.name'),
            'email' => __('users.fields.email'),
            'mobile' => __('users.fields.mobile'),
            'projects' => __('users.fields.projects'),
            'status' => __('users.fields.is_active'),
            'last_login' => __('users.fields.last_login_at'),
        ], hidden: ['last_login'], locked: ['name']);

        return view('customers.index', compact('customers', 'grid'));
    }

    public function create(Request $request)
    {
        return view('customers.form', [
            'customer' => new User(['is_active' => true, 'locale' => 'fa', 'calendar' => 'jalali']),
            'projects' => $this->myProjects($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $developer = $request->user();
        UserRules::prepare($request);
        $data = $request->validate(UserRules::rules());

        DB::transaction(function () use ($data, $request, $developer) {
            $customer = User::create(collect($data)->except(['projects', 'avatar'])->all() + [
                'role' => Role::Customer->value,
                'created_by' => $developer->id,
            ]);
            $this->saveAvatar($request, $customer);
            $customer->projects()->attach($this->allowedProjectIds($developer, $data['projects'] ?? []));
        });

        return redirect()->route('customers.index')->with('success', __('app.saved'));
    }

    public function edit(Request $request, User $customer)
    {
        $this->ensureMine($request->user(), $customer);

        return view('customers.form', ['customer' => $customer->load('projects'), 'projects' => $this->myProjects($request->user())]);
    }

    public function update(Request $request, User $customer)
    {
        $developer = $request->user();
        $this->ensureMine($developer, $customer);
        UserRules::prepare($request);
        $data = $request->validate(UserRules::rules($customer->id));

        DB::transaction(function () use ($data, $request, $developer, $customer) {
            $fields = collect($data)->except(['projects', 'avatar']);
            if (empty($data['password'])) {
                $fields->forget('password');
            }
            $customer->update($fields->all());
            $this->saveAvatar($request, $customer);

            // Only touch the developer's own projects; other developers' projects stay as they are.
            $mine = $developer->accessibleProjectIds();
            $selected = $this->allowedProjectIds($developer, $data['projects'] ?? []);
            $customer->projects()->detach(array_diff($mine, $selected));
            $customer->projects()->syncWithoutDetaching($selected);
        });

        return redirect()->route('customers.index')->with('success', __('app.saved'));
    }

    /** Removes the customer from the developer's projects. Deletes them if nothing is left. */
    public function destroy(Request $request, User $customer)
    {
        $developer = $request->user();
        $this->ensureMine($developer, $customer);

        $customer->projects()->detach($developer->accessibleProjectIds());
        if ($customer->projects()->count() === 0 && $customer->created_by === $developer->id) {
            $customer->delete();
        }

        return redirect()->route('customers.index')->with('success', __('app.deleted'));
    }

    // ---------------------------------------------------------------------

    private function ensureMine(User $developer, User $customer): void
    {
        abort_unless(User::query()->customersOf($developer)->whereKey($customer->id)->exists(), 403);
    }

    private function myProjects(User $developer)
    {
        return Project::query()->visibleTo($developer)->orderBy('name')->get();
    }

    private function allowedProjectIds(User $developer, array $ids): array
    {
        return array_values(array_intersect(array_map('intval', $ids), $developer->accessibleProjectIds()));
    }
}
