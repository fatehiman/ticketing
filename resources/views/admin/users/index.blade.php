@extends('layouts.app')
@section('title', __('users.title'))

@php use App\Support\Dates; @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-person-gear text-brand"></i> {{ __('users.title') }}</h1>
            <div class="sub">{{ __('app.results', ['count' => $users->total()]) }}</div>
        </div>
        <div class="dropdown">
            <button class="btn btn-gradient dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> {{ __('users.new') }}</button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                @foreach ($roles as $role)
                    <li><a class="dropdown-item" href="{{ route('admin.users.create', ['role' => $role->value]) }}"><span class="badge text-bg-{{ $role->color() }}">&nbsp;</span> {{ $role->label() }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="grid-toolbar">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 260px" placeholder="{{ __('app.search') }}…">
            <select name="role" class="form-select form-select-sm" style="max-width: 180px" onchange="this.form.submit()">
                <option value="">{{ __('users.fields.role') }}: {{ __('app.all') }}</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            <div class="ms-auto"><x-grid-columns :grid="$grid" /></div>
        </form>
        <div class="table-responsive">
            <table class="table table-grid" data-grid="users">
                <thead>
                <tr>
                    @foreach ($grid->columns as $key => $label)
                        <th data-col="{{ $key }}" class="{{ $grid->cls($key) }}">{{ $label }}</th>
                    @endforeach
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td data-col="avatar" class="{{ $grid->cls('avatar') }}"><x-avatar :user="$u" class="avatar-sm" /></td>
                        <td data-col="name" class="{{ $grid->cls('name') }} fw-semibold">{{ $u->name }}</td>
                        <td data-col="role" class="{{ $grid->cls('role') }}"><span class="badge text-bg-{{ $u->role->color() }}">{{ $u->role->label() }}</span></td>
                        <td data-col="email" class="{{ $grid->cls('email') }} small">{{ $u->email }}</td>
                        <td data-col="mobile" class="{{ $grid->cls('mobile') }} small ltr-input">{{ $u->mobile }}</td>
                        <td data-col="projects" class="{{ $grid->cls('projects') }}">
                            @foreach ($u->projects as $p)<span class="badge bg-brand-soft text-brand me-1">{{ $p->code }}</span>@endforeach
                        </td>
                        <td data-col="status" class="{{ $grid->cls('status') }}">
                            @if ($u->is_active)<span class="badge text-bg-success">{{ __('app.active') }}</span>@else<span class="badge text-bg-secondary">{{ __('app.inactive') }}</span>@endif
                        </td>
                        <td data-col="last_login" class="{{ $grid->cls('last_login') }} small text-nowrap">{{ $u->last_login_at ? Dates::ago($u->last_login_at) : __('users.never') }}</td>
                        <td data-col="created_at" class="{{ $grid->cls('created_at') }} small text-nowrap">{{ Dates::format($u->created_at) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="d-inline" data-confirm="{{ __('app.confirm_delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($grid->columns) + 1 }}" class="empty-state"><i class="bi bi-people"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
