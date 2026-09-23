@extends('layouts.app')
@section('title', __('projects.title'))

@php
    use App\Support\Dates;
    use App\Support\Money;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-kanban text-brand"></i> {{ __('projects.title') }}</h1>
            <div class="sub">{{ __('app.results', ['count' => $projects->total()]) }}</div>
        </div>
        @can('create', App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="btn btn-gradient"><i class="bi bi-plus-lg"></i> {{ __('projects.new') }}</a>
        @endcan
    </div>

    <div class="card">
        <form method="GET" class="grid-toolbar">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 260px" placeholder="{{ __('app.search') }}…">
            <select name="status" class="form-select form-select-sm" style="max-width: 180px" onchange="this.form.submit()">
                <option value="">{{ __('projects.fields.status') }}: {{ __('app.all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            <div class="ms-auto"><x-grid-columns :grid="$grid" /></div>
        </form>
        <div class="table-responsive">
            <table class="table table-grid" data-grid="projects">
                <thead>
                <tr>
                    @foreach ($grid->columns as $key => $label)
                        <th data-col="{{ $key }}" class="{{ $grid->cls($key) }}">{{ $label }}</th>
                    @endforeach
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td data-col="logo" class="{{ $grid->cls('logo') }}">
                            @if ($project->logoUrl())
                                <img src="{{ $project->logoUrl() }}" alt="" class="project-logo">
                            @else
                                <span class="project-logo">{{ mb_substr($project->code, 0, 2) }}</span>
                            @endif
                        </td>
                        <td data-col="name" class="{{ $grid->cls('name') }}">
                            <a href="{{ route('projects.show', $project) }}" class="t-title">{{ $project->name }}</a>
                            @if ($project->description)<div class="small text-muted text-truncate" style="max-width: 320px">{{ $project->description }}</div>@endif
                        </td>
                        <td data-col="code" class="{{ $grid->cls('code') }}"><code>{{ $project->code }}</code></td>
                        <td data-col="status" class="{{ $grid->cls('status') }}"><span class="badge rounded-pill text-bg-{{ $project->status->color() }}">{{ $project->status->label() }}</span></td>
                        <td data-col="dates" class="{{ $grid->cls('dates') }} small text-nowrap">{{ Dates::format($project->start_date) }} — {{ Dates::format($project->end_date) }}</td>
                        <td data-col="budget" class="{{ $grid->cls('budget') }} text-nowrap">{{ Money::format($project->budget, $project->currency) }}</td>
                        <td data-col="developers" class="{{ $grid->cls('developers') }}">{{ $project->developers_count }}</td>
                        <td data-col="customers" class="{{ $grid->cls('customers') }}">{{ $project->customers_count }}</td>
                        <td data-col="tickets" class="{{ $grid->cls('tickets') }}">
                            <a href="{{ route('tickets.index', ['project_id' => $project->id]) }}"><span class="fw-semibold">{{ $project->open_tickets_count }}</span> / {{ $project->tickets_count }}</a>
                        </td>
                        <td data-col="contact" class="{{ $grid->cls('contact') }} small">
                            {{ $project->contact_person }}
                            @if ($project->phone1)<div class="ltr-input">{{ $project->phone1 }}</div>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('update', $project)
                                <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-light" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                            <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-light" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($grid->columns) + 1 }}" class="empty-state"><i class="bi bi-kanban"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($projects->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $projects->links() }}</div>
        @endif
    </div>
@endsection
