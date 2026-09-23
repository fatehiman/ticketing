@extends('layouts.app')
@section('title', __('app.dashboard'))

@php
    use App\Support\Dates;
    $user = auth()->user();
    $openStatuses = collect($statuses)->reject->isClosed()->map->value->all();
    $maxStatus = max(1, $byStatus->max() ?? 1);
    $maxPrio = max(1, $byPriority->max() ?? 1);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ __('app.dash.welcome', ['name' => $user->first_name]) }} 👋</h1>
            <div class="sub">{{ __('app.dash.subtitle') }}</div>
        </div>
        <a href="{{ route('tickets.create') }}" class="btn btn-gradient d-sm-none"><i class="bi bi-plus-lg"></i> {{ __('app.new_ticket') }}</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2 col-md-4">
            <a href="{{ route('tickets.index', ['status' => $openStatuses]) }}" class="stat-card grad-1 d-block">
                <div class="stat-value">{{ $totals['open'] }}</div><div class="stat-label">{{ __('app.dash.open') }}</div><i class="bi bi-inbox"></i>
            </a>
        </div>
        <div class="col-6 col-xl-2 col-md-4">
            <a href="{{ route('tickets.index', ['status' => ['in_progress']]) }}" class="stat-card grad-2 d-block">
                <div class="stat-value">{{ $totals['in_progress'] }}</div><div class="stat-label">{{ __('app.dash.in_progress') }}</div><i class="bi bi-play-circle"></i>
            </a>
        </div>
        <div class="col-6 col-xl-2 col-md-4">
            <a href="{{ route('tickets.index', ['status' => ['done']]) }}" class="stat-card grad-3 d-block">
                <div class="stat-value">{{ $totals['done'] }}</div><div class="stat-label">{{ __('app.dash.done') }}</div><i class="bi bi-check2-circle"></i>
            </a>
        </div>
        @if ($totals['mine'] !== null)
            <div class="col-6 col-xl-2 col-md-4">
                <a href="{{ route('tickets.index', ['assignee_id' => 'me']) }}" class="stat-card grad-6 d-block">
                    <div class="stat-value">{{ $totals['mine'] }}</div><div class="stat-label">{{ __('app.dash.mine') }}</div><i class="bi bi-person-check"></i>
                </a>
            </div>
        @endif
        <div class="col-6 col-xl-2 col-md-4">
            <div class="stat-card grad-5">
                <div class="stat-value">{{ $totals['overdue'] }}</div><div class="stat-label">{{ __('app.dash.overdue') }}</div><i class="bi bi-alarm"></i>
            </div>
        </div>
        <div class="col-6 col-xl-2 col-md-4">
            <div class="stat-card grad-4">
                <div class="stat-value">{{ $projectCount }}</div><div class="stat-label">{{ __('app.dash.active_projects') }}</div><i class="bi bi-kanban"></i>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card card-accent h-100">
                <div class="card-header"><i class="bi bi-bar-chart text-brand"></i> {{ __('app.dash.by_status') }}</div>
                <div class="card-body">
                    @foreach ($statuses as $status)
                        @php($c = (int) ($byStatus[$status->value] ?? 0))
                        <a class="bar-row text-reset" href="{{ route('tickets.index', ['status' => [$status->value]]) }}" style="--c: {{ $status->color() }}">
                            <span class="bar-label"><i class="bi {{ $status->icon() }}" style="color: {{ $status->color() }}"></i> {{ $status->label() }}</span>
                            <span class="bar"><span style="width: {{ $c / $maxStatus * 100 }}%"></span></span>
                            <span class="bar-value">{{ $c }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-accent h-100">
                <div class="card-header"><i class="bi bi-flag text-brand"></i> {{ __('app.dash.by_priority') }}</div>
                <div class="card-body">
                    @foreach ($priorities as $priority)
                        @php($c = (int) ($byPriority[$priority->value] ?? 0))
                        <a class="bar-row text-reset" href="{{ route('tickets.index', ['priority' => [$priority->value], 'status' => $openStatuses]) }}" style="--c: {{ $priority->color() }}">
                            <span class="bar-label"><x-priority :priority="$priority" /></span>
                            <span class="bar"><span style="width: {{ $c / $maxPrio * 100 }}%"></span></span>
                            <span class="bar-value">{{ $c }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card card-accent h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history text-brand"></i> {{ __('app.dash.recent') }}</span>
                    <a href="{{ route('tickets.index') }}" class="small">{{ __('tickets.menu.all') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-grid">
                        <tbody>
                        @forelse ($recent as $ticket)
                            <tr>
                                <td class="t-number">#{{ $ticket->number }}</td>
                                <td class="min-w-0">
                                    <a class="t-title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                    <div class="small text-muted">{{ $ticket->project->name }}</div>
                                </td>
                                <td><x-status :status="$ticket->status" /></td>
                                <td><x-priority :priority="$ticket->priority" icon-only /></td>
                                <td class="text-muted small text-nowrap">{{ Dates::ago($ticket->updated_at) }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty-state"><i class="bi bi-inbox"></i>{{ __('app.no_results') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-accent h-100">
                <div class="card-header"><i class="bi bi-lightning-charge text-brand"></i> {{ __('app.dash.active_sprints') }}</div>
                <div class="card-body">
                    @forelse ($activeSprints as $sprint)
                        @php($pct = $sprint->tickets_count ? round($sprint->done_count / $sprint->tickets_count * 100) : 0)
                        <a href="{{ route('tickets.index', ['sprint_id' => $sprint->id, 'project_id' => $sprint->project_id]) }}" class="d-block text-reset mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold">{{ $sprint->label() }}</span>
                                <span class="text-muted">{{ $sprint->done_count }}/{{ $sprint->tickets_count }}</span>
                            </div>
                            <div class="text-muted" style="font-size:.75rem">{{ $sprint->project->name }} · {{ Dates::format($sprint->end_date) }}</div>
                            <div class="progress mt-1" style="height:.5rem">
                                <div class="progress-bar" style="width: {{ $pct }}%; background: var(--brand-gradient)"></div>
                            </div>
                        </a>
                    @empty
                        <div class="empty-state py-4"><i class="bi bi-lightning"></i>{{ __('app.dash.no_sprints') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
