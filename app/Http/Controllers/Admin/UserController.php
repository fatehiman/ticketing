<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Concerns\SavesAvatar;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\Grid;
use App\Support\UserRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin: manages every user. Developers and customers can be assigned to projects; admins cannot. */
class UserController extends Controller
{
    use SavesAvatar;

    public function index(Request $request)
    {
        $users = User::query()
            ->with('projects:id,name,code')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)->orWhere('mobile', 'like', $term));
            })
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'developer' THEN 1 ELSE 2 END")->orderBy('first_name')
            ->paginate(25)->withQueryString();

        $grid = Grid::make('users', [
            'avatar' => __('users.fields.avatar'),
            'name' => __('users.fields.name'),
            'role' => __('users.fields.role'),
            'email' => __('users.fields.email'),
            'mobile' => __('users.fields.mobile'),
            'projects' => __('users.fields.projects'),
            'status' => __('users.fields.is_active'),
            'last_login' => __('users.fields.last_login_at'),
            'created_at' => __('users.fields.created_at'),
        ], hidden: ['created_at'], locked: ['name']);

        return view('admin.users.index', compact('users', 'grid') + ['roles' => Role::cases()]);
    }

    public function create(Request $request)
    {
        $role = Role::tryFrom((string) $request->query('role')) ?? Role::Customer;

        return view('admin.users.form', [
            'user' => new User(['role' => $role, 'is_active' => true, 'locale' => 'fa', 'calendar' => 'jalali']),
            'roles' => Role::cases(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        UserRules::prepare($request);
        $data = $request->validate(UserRules::rules() + ['role' => ['required', Rule::enum(Role::class)]]);

        DB::transaction(function () use ($data, $request) {
            $user = User::create(collect($data)->except(['projects', 'avatar'])->all() + ['created_by' => $request->user()->id]);
            $this->saveAvatar($request, $user);
            if ($user->role !== Role::Admin) {
                $user->projects()->sync($data['projects'] ?? []);
            }
        });

        return redirect()->route('admin.users.index')->with('success', __('app.saved'));
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user->load('projects'),
            'roles' => Role::cases(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        UserRules::prepare($request);
        $data = $request->validate(UserRules::rules($user->id) + ['role' => ['required', Rule::enum(Role::class)]]);

        // An admin cannot lock themselves out.
        if ($user->is($request->user())) {
            $data['role'] = Role::Admin->value;
            $data['is_active'] = true;
        }

        DB::transaction(function () use ($data, $request, $user) {
            $fields = collect($data)->except(['projects', 'avatar']);
            if (empty($data['password'])) {
                $fields->forget('password');
            }
            $user->update($fields->all());
            $this->saveAvatar($request, $user);
            $user->projects()->sync($user->role === Role::Admin ? [] : ($data['projects'] ?? []));
        });

        return redirect()->route('admin.users.index')->with('success', __('app.saved'));
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, __('users.cannot_delete_self'));
        $user->delete(); // soft delete: tickets keep their reporter

        return redirect()->route('admin.users.index')->with('success', __('app.deleted'));
    }
}
