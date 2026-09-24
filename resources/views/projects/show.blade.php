@extends('layouts.app')
@section('title', $project->name)

@php
    use App\Support\Dates;
    use App\Support\Money;
    $total = $byStatus->sum();
@endphp

@section('content')
    <div class="page-head">
        <div class="d-flex align-items-center gap-3">
            @if ($project->logoUrl())
                <img src="{{ $project->logoUrl() }}" alt="" class="project-logo" style="width:56px;height:56px">
            @else
                <span class="project-logo" style="width:56px;height:56px;font-size:1.1rem">{{ mb_substr($project->code, 0, 2) }}</span>
            @endif
            <div>
                <h1>{{ $project->name }} <span class="badge rounded-pill text-bg-{{ $project->status->color() }} fs-6 align-middle">{{ $project->status->label() }}</span></h1>
                <div class="sub"><code>{{ $project->code }}</code> · {{ $project->description }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tickets.index', ['project_id' => $project->id]) }}" class="btn btn-outline-primary"><i class="bi bi-list-task"></i> {{ __('projects.view_tickets') }}</a>
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ($statuses as $i => $status)
            <div class="col-6 col-md-3 col-xl">
                <a href="{{ route('tickets.index', ['project_id' => $project->id, 'status' => [$status->value]]) }}" class="card d-block p-3 text-reset h-100">
                    <div class="small text-muted"><i class="bi {{ $status->icon() }}" style="color: {{ $status->color() }}"></i> {{ $status->label() }}</div>
                    <div class="fs-3 fw-bold" style="color: {{ $status->color() }}">{{ (int) ($byStatus[$status->value] ?? 0) }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card card-accent mb-3">
                <div class="card-header"><i class="bi bi-info-circle text-brand"></i> {{ __('projects.general') }}</div>
                <div class="card-body">
                    <dl class="meta-list row mb-0">
                        <dt class="col-sm-4">{{ __('projects.fields.dates') }}</dt>
                        <dd class="col-sm-8">{{ Dates::format($project->start_date) ?: '—' }} — {{ Dates::format($project->end_date) ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('projects.fields.budget') }}</dt>
                        <dd class="col-sm-8">{{ Money::format($project->budget, $project->currency) ?: '—' }}</dd>
                        @if (auth()->user()->isStaff())
                            <dt class="col-sm-4">{{ __('projects.spent') }}</dt>
                            <dd class="col-sm-8">{{ Money::format($spent, $project->currency) ?: '0' }}</dd>
                            @if ($project->budget)
                                <dt class="col-sm-4">{{ __('projects.remaining') }}</dt>
                                <dd class="col-sm-8 {{ $project->budget - $spent < 0 ? 'text-danger' : 'text-success' }}">{{ Money::format($project->budget - $spent, $project->currency) }}</dd>
                            @endif
                        @endif
                        <dt class="col-sm-4">{{ __('projects.fields.contact_person') }}</dt>
                        <dd class="col-sm-8">{{ $project->contact_person ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('projects.fields.phone1') }} / {{ __('projects.fields.phone2') }}</dt>
                        <dd class="col-sm-8 ltr-input">{{ collect([$project->phone1, $project->phone2])->filter()->implode(' · ') ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('projects.fields.email') }}</dt>
                        <dd class="col-sm-8">@if ($project->email)<a href="mailto:{{ $project->email }}">{{ $project->email }}</a>@else — @endif</dd>
                        <dt class="col-sm-4">{{ __('projects.fields.website') }}</dt>
                        <dd class="col-sm-8">@if ($project->website)<a href="{{ $project->website }}" target="_blank" rel="noopener">{{ $project->website }}</a>@else — @endif</dd>
                        <dt class="col-sm-4">{{ __('projects.fields.address') }}</dt>
                        <dd class="col-sm-8">{{ $project->address ?: '—' }}</dd>
                        @if ($project->notes)
                            <dt class="col-sm-4">{{ __('projects.fields.notes') }}</dt>
                            <dd class="col-sm-8 fw-normal" style="white-space: pre-line">{{ $project->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if (auth()->user()->isStaff())
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi bi-lightning-charge text-brand"></i> {{ __('app.sprints') }}</span>
                        @can('manageSprints', $project)<a href="{{ route('sprints.create') }}" class="small">+ {{ __('sprints.new') }}</a>@endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table table-grid">
                            <tbody>
                            @forelse ($project->sprints as $sprint)
                                <tr>
                                    <td class="fw-semibold">{{ $sprint->label() }}</td>
                                    <td><span class="badge text-bg-{{ $sprint->status->color() }}">{{ $sprint->status->label() }}</span></td>
                                    <td class="small">{{ Dates::format($sprint->start_date) }} — {{ Dates::format($sprint->end_date) }}</td>
                                    <td><a href="{{ route('tickets.index', ['project_id' => $project->id, 'sprint_id' => $sprint->id]) }}">{{ $sprint->tickets_count }} <i class="bi bi-ticket"></i></a></td>
                                </tr>
                            @empty
                                <tr><td class="text-muted small">{{ __('app.no_results') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card card-accent">
                <div class="card-header"><i class="bi bi-people text-brand"></i> {{ __('projects.members') }}</div>
                <div class="card-body">
                    <div class="small text-muted fw-semibold mb-2">{{ __('projects.fields.developers') }}</div>
                    @forelse ($project->developers as $member)
                        <div class="d-flex align-items-center gap-2 mb-2"><x-avatar :user="$member" class="avatar-sm" /> <span class="small">{{ $member->name }}</span></div>
                    @empty
                        <div class="text-muted small mb-2">{{ __('projects.no_members') }}</div>
                    @endforelse
                    <hr>
                    <div class="small text-muted fw-semibold mb-2">{{ __('projects.fields.customers') }}</div>
                    @forelse ($project->customers as $member)
                        <div class="d-flex align-items-center gap-2 mb-2"><x-avatar :user="$member" class="avatar-sm" /> <span class="small">{{ $member->name }}</span></div>
                    @empty
                        <div class="text-muted small">{{ __('projects.no_members') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
