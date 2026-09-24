@extends('layouts.app')
@section('title', __('sprints.title'))

@php use App\Support\Dates; @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-lightning-charge text-brand"></i> {{ __('sprints.title') }}</h1>
            <div class="sub">{{ $projectContext->current()?->name ?? __('app.all_projects') }} · {{ __('app.results', ['count' => $sprints->total()]) }}</div>
        </div>
        @if (auth()->user()->isDeveloper())
            <a href="{{ route('sprints.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('sprints.new') }}</a>
        @endif
    </div>

    <div class="card">
        <form method="GET" class="grid-toolbar">
            <select name="status" class="form-select form-select-sm" style="max-width: 200px" onchange="this.form.submit()">
                <option value="">{{ __('sprints.fields.status') }}: {{ __('app.all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <div class="ms-auto"><x-grid-columns :grid="$grid" /></div>
        </form>
        <div class="table-responsive">
            <table class="table table-grid" data-grid="sprints">
                <thead>
                <tr>
                    @foreach ($grid->columns as $key => $label)
                        <th data-col="{{ $key }}" class="{{ $grid->cls($key) }}">{{ $label }}</th>
                    @endforeach
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($sprints as $sprint)
                    @php($pct = $sprint->tickets_count ? round($sprint->done_count / $sprint->tickets_count * 100) : 0)
                    <tr>
                        <td data-col="number" class="{{ $grid->cls('number') }} t-number">{{ $sprint->number }}</td>
                        <td data-col="name" class="{{ $grid->cls('name') }} fw-semibold">{{ $sprint->name ?: __('sprints.sprint_n', ['n' => $sprint->number]) }}</td>
                        <td data-col="project" class="{{ $grid->cls('project') }}">{{ $sprint->project->name }}</td>
                        <td data-col="status" class="{{ $grid->cls('status') }}"><span class="badge text-bg-{{ $sprint->status->color() }}">{{ $sprint->status->label() }}</span></td>
                        <td data-col="dates" class="{{ $grid->cls('dates') }} small text-nowrap">{{ Dates::format($sprint->start_date) }} — {{ Dates::format($sprint->end_date) }}</td>
                        <td data-col="tickets" class="{{ $grid->cls('tickets') }}" style="min-width: 140px">
                            <a href="{{ route('tickets.index', ['project_id' => $sprint->project_id, 'sprint_id' => $sprint->id]) }}" class="small">{{ $sprint->done_count }} / {{ $sprint->tickets_count }}</a>
                            <div class="progress" style="height:.35rem"><div class="progress-bar" style="width: {{ $pct }}%; background: var(--brand-gradient)"></div></div>
                        </td>
                        <td data-col="points" class="{{ $grid->cls('points') }}">{{ (int) $sprint->points_done }} / {{ (int) $sprint->points_total }}</td>
                        <td data-col="goal" class="{{ $grid->cls('goal') }} small">{{ $sprint->goal }}</td>
                        <td class="text-end text-nowrap">
                            @can('manageSprints', $sprint->project)
                                <a href="{{ route('sprints.edit', $sprint) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('sprints.destroy', $sprint) }}" class="d-inline" data-confirm="{{ __('app.confirm_delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($grid->columns) + 1 }}" class="empty-state"><i class="bi bi-lightning"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($sprints->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $sprints->links() }}</div>
        @endif
    </div>
@endsection
