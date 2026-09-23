@extends('layouts.app')
@section('title', __('users.customers_title'))

@php use App\Support\Dates; @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-people text-brand"></i> {{ __('users.customers_title') }}</h1>
            <div class="sub">{{ __('app.results', ['count' => $customers->total()]) }}</div>
        </div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('users.new_customer') }}</a>
    </div>

    <div class="card">
        <form method="GET" class="grid-toolbar">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 260px" placeholder="{{ __('app.search') }}…">
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            <div class="ms-auto"><x-grid-columns :grid="$grid" /></div>
        </form>
        <div class="table-responsive">
            <table class="table table-grid" data-grid="customers">
                <thead>
                <tr>
                    @foreach ($grid->columns as $key => $label)
                        <th data-col="{{ $key }}" class="{{ $grid->cls($key) }}">{{ $label }}</th>
                    @endforeach
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($customers as $c)
                    <tr>
                        <td data-col="avatar" class="{{ $grid->cls('avatar') }}"><x-avatar :user="$c" class="avatar-sm" /></td>
                        <td data-col="name" class="{{ $grid->cls('name') }} fw-semibold">{{ $c->name }}</td>
                        <td data-col="email" class="{{ $grid->cls('email') }} small">{{ $c->email }}</td>
                        <td data-col="mobile" class="{{ $grid->cls('mobile') }} small ltr-input">{{ $c->mobile }}</td>
                        <td data-col="projects" class="{{ $grid->cls('projects') }}">
                            @foreach ($c->projects as $p)<span class="badge bg-brand-soft text-brand me-1">{{ $p->name }}</span>@endforeach
                        </td>
                        <td data-col="status" class="{{ $grid->cls('status') }}">
                            @if ($c->is_active)<span class="badge text-bg-success">{{ __('app.active') }}</span>@else<span class="badge text-bg-secondary">{{ __('app.inactive') }}</span>@endif
                        </td>
                        <td data-col="last_login" class="{{ $grid->cls('last_login') }} small">{{ $c->last_login_at ? Dates::ago($c->last_login_at) : __('users.never') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('customers.edit', $c) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('customers.destroy', $c) }}" class="d-inline" data-confirm="{{ __('users.delete_customer_confirm') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger"><i class="bi bi-person-dash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($grid->columns) + 1 }}" class="empty-state"><i class="bi bi-people"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $customers->links() }}</div>
        @endif
    </div>
@endsection
