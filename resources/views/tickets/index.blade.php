@extends('layouts.app')
@section('title', __('tickets.title'))

@php
    use App\Enums\StoryPoint;
    use App\Support\Dates;
    use App\Support\Duration;
    use App\Support\Money;
    $user = auth()->user();
    $activeMenu = collect($ticketMenus)->firstWhere('active', true);
    $f = fn ($key, $default = null) => $filters[$key] ?? $default;
    $checked = fn ($key, $value) => in_array($value, $filters[$key] ?? [], true);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>
                @if ($activeMenu)
                    <i class="bi {{ $activeMenu['color'] ? 'bi-circle-fill' : $activeMenu['icon'] }}" @if ($activeMenu['color']) style="color: {{ $activeMenu['color'] }}; font-size: .8em" @endif></i>
                    {{ $activeMenu['name'] }}
                @else
                    <i class="bi bi-search"></i> {{ __('tickets.title') }}
                @endif
            </h1>
            <div class="sub">
                {{ $projectContext->current()?->name ?? __('app.all_projects') }} · {{ __('app.results', ['count' => $tickets->total()]) }}
            </div>
        </div>
        @can('create', App\Models\Ticket::class)
            <a href="{{ route('tickets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('tickets.new') }}</a>
        @endcan
    </div>

    {{-- Filter box --}}
    <form method="GET" action="{{ route('tickets.index') }}" id="ticket-filter" data-menu-url="{{ route('ticket-menus.store') }}" class="card filter-card mb-3">
        <div class="card-body pb-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">{{ __('app.search') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" value="{{ $f('q') }}" class="form-control" placeholder="{{ __('tickets.filter.keyword') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('tickets.fields.project_id') }}</label>
                    <select name="project_id" class="form-select">
                        <option value="">{{ __('tickets.filter.project_hint') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) $f('project_id') === (string) $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('tickets.fields.assignee_id') }}</label>
                    <select name="assignee_id" class="form-select">
                        <option value="">{{ __('tickets.filter.any') }}</option>
                        @if ($user->isStaff())<option value="me" @selected($f('assignee_id') === 'me')>{{ __('tickets.filter.me') }}</option>@endif
                        <option value="none" @selected($f('assignee_id') === 'none')>{{ __('tickets.filter.nobody') }}</option>
                        @foreach ($developers as $dev)
                            <option value="{{ $dev->id }}" @selected((string) $f('assignee_id') === (string) $dev->id)>{{ $dev->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label d-block">{{ __('tickets.fields.status') }}</label>
                    @foreach ($statuses as $status)
                        <label class="chip-check" style="--c: {{ $status->color() }}">
                            <input type="checkbox" name="status[]" value="{{ $status->value }}" @checked($checked('status', $status->value))>
                            <span><i class="bi {{ $status->icon() }}"></i>{{ $status->label() }}</span>
                        </label>
                    @endforeach
                    <label class="chip-check" style="--c: #dc2626">
                        <input type="checkbox" name="awaiting" value="me" @checked($f('awaiting') === 'me')>
                        <span><i class="bi bi-reply-fill"></i>{{ __('tickets.menu.awaiting') }}</span>
                    </label>
                </div>
            </div>

            <div class="collapse {{ $advancedOpen ? 'show' : '' }}" id="more-filters">
                <hr class="my-2">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label d-block">{{ __('tickets.fields.priority') }}</label>
                        @foreach ($priorities as $priority)
                            <label class="chip-check" style="--c: {{ $priority->color() }}">
                                <input type="checkbox" name="priority[]" value="{{ $priority->value }}" @checked($checked('priority', $priority->value))>
                                <span><i class="bi {{ $priority->icon() }}"></i>{{ $priority->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">{{ __('tickets.fields.type') }}</label>
                        @foreach ($types as $type)
                            <label class="chip-check">
                                <input type="checkbox" name="type[]" value="{{ $type->value }}" @checked($checked('type', $type->value))>
                                <span><i class="bi {{ $type->icon() }}"></i>{{ $type->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">{{ __('tickets.filter.number') }}</label>
                        <input type="text" name="number" value="{{ $f('number') }}" class="form-control ltr-input" inputmode="numeric">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">{{ __('tickets.fields.sprint_id') }}</label>
                        <select name="sprint_id" class="form-select">
                            <option value="">{{ __('tickets.filter.any') }}</option>
                            <option value="none" @selected($f('sprint_id') === 'none')>{{ __('tickets.no_sprint') }}</option>
                            @foreach ($sprints as $sprint)
                                <option value="{{ $sprint->id }}" @selected((string) $f('sprint_id') === (string) $sprint->id)>{{ $sprint->project->code }} · {{ $sprint->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('tickets.fields.reporter_id') }}</label>
                        <select name="reporter_id" class="form-select">
                            <option value="">{{ __('tickets.filter.any') }}</option>
                            <option value="me" @selected($f('reporter_id') === 'me')>{{ __('tickets.filter.me') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((string) $f('reporter_id') === (string) $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"></div>
                    @foreach (['created', 'updated', 'due'] as $prefix)
                        <div class="col-md-4">
                            <label class="form-label">{{ __('tickets.filter.'.$prefix) }}</label>
                            <div class="input-group">
                                <span class="input-group-text small">{{ __('app.from') }}</span>
                                <x-date-input :name="$prefix.'_from'" :value="$f($prefix.'_from')" />
                                <span class="input-group-text small">{{ __('app.to') }}</span>
                                <x-date-input :name="$prefix.'_to'" :value="$f($prefix.'_to')" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap align-items-center gap-2">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-funnel"></i> {{ __('app.search') }}</button>
            <div class="form-check create-menu-check" title="{{ __('tickets.filter.create_menu_hint') }}" data-bs-toggle="tooltip">
                <input class="form-check-input" type="checkbox" id="create_menu" name="create_menu" value="1">
                <label class="form-check-label small fw-semibold" for="create_menu"><i class="bi bi-folder-plus"></i> {{ __('tickets.filter.create_menu') }}</label>
            </div>
            <a href="{{ route('tickets.index') }}" class="btn btn-light"><i class="bi bi-x-circle"></i> {{ __('app.reset') }}</a>
            <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#more-filters">
                <i class="bi bi-sliders"></i> {{ __('app.more_filters') }}
            </button>
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="dir" value="{{ request('dir') }}">
            <div class="ms-auto d-flex align-items-center gap-2">
                <label class="small text-muted text-nowrap" for="per_page">{{ __('app.per_page') }}</label>
                <select name="per_page" id="per_page" class="form-select form-select-sm" data-autosubmit style="width:auto">
                    @foreach ([10, 25, 50, 100] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- Grid --}}
    <div class="card">
        <div class="grid-toolbar">
            <span class="fw-semibold">{{ __('app.results', ['count' => $tickets->total()]) }}</span>
            <div class="ms-auto"><x-grid-columns :grid="$grid" /></div>
        </div>
        <div class="table-responsive">
            <table class="table table-grid" data-grid="tickets">
                <thead>
                <tr>
                    @php($sortable = ['number' => 'number', 'title' => 'title', 'status' => 'status', 'priority' => 'priority', 'story_points' => 'story_points', 'due_date' => 'due_date', 'created_at' => 'created_at', 'updated_at' => 'updated_at'])
                    @foreach ($grid->columns as $key => $label)
                        <th data-col="{{ $key }}" class="{{ $grid->cls($key) }}">
                            @isset($sortable[$key])
                                <x-sort-link :column="$sortable[$key]" :label="$label" :sort="$sort" :dir="$dir" />
                            @else
                                {{ $label }}
                            @endisset
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($tickets as $ticket)
                    @php($overdue = $ticket->due_date && $ticket->due_date->isPast() && ! $ticket->status->isClosed())
                    <tr>
                        <td data-col="number" class="{{ $grid->cls('number') }}"><a href="{{ route('tickets.show', $ticket) }}" class="t-number">#{{ $ticket->number }}</a></td>
                        <td data-col="title" class="{{ $grid->cls('title') }}" style="min-width: 240px">
                            <a href="{{ route('tickets.show', $ticket) }}" class="t-title">{{ $ticket->title }}</a>
                            @if ($ticket->isAwaiting(auth()->user()))
                                <i class="bi bi-circle-fill awaiting-dot ms-1" title="{{ __('tickets.followup.awaiting_badge') }}"></i>
                            @endif
                        </td>
                        <td data-col="project" class="{{ $grid->cls('project') }} text-nowrap">{{ $ticket->project->name }}</td>
                        <td data-col="type" class="{{ $grid->cls('type') }} text-nowrap"><i class="bi {{ $ticket->type->icon() }}"></i> {{ $ticket->type->label() }}</td>
                        <td data-col="status" class="{{ $grid->cls('status') }}"><x-status :status="$ticket->status" /></td>
                        <td data-col="priority" class="{{ $grid->cls('priority') }}"><x-priority :priority="$ticket->priority" /></td>
                        <td data-col="sprint" class="{{ $grid->cls('sprint') }} text-nowrap">{{ $ticket->sprint?->label() }}</td>
                        <td data-col="assignee" class="{{ $grid->cls('assignee') }} text-nowrap">
                            @if ($ticket->assignee)
                                <x-avatar :user="$ticket->assignee" class="avatar-sm" /> <span class="small">{{ $ticket->assignee->name }}</span>
                            @else
                                <span class="text-muted small">{{ __('tickets.unassigned') }}</span>
                            @endif
                        </td>
                        <td data-col="reporter" class="{{ $grid->cls('reporter') }} text-nowrap small">{{ $ticket->reporter?->name }}</td>
                        <td data-col="story_points" class="{{ $grid->cls('story_points') }} text-nowrap small" title="{{ StoryPoint::labelFor($ticket->story_points) }}">{{ $ticket->story_points }}</td>
                        <td data-col="done_story_points" class="{{ $grid->cls('done_story_points') }}">{{ $ticket->done_story_points }}</td>
                        <td data-col="estimated_time" class="{{ $grid->cls('estimated_time') }} ltr-input">{{ Duration::format($ticket->estimated_minutes) }}</td>
                        <td data-col="logged_time" class="{{ $grid->cls('logged_time') }} ltr-input">{{ Duration::format($ticket->logged_minutes) }}</td>
                        <td data-col="estimated_cost" class="{{ $grid->cls('estimated_cost') }} text-nowrap">{{ Money::format($ticket->estimated_cost) }}</td>
                        <td data-col="cost" class="{{ $grid->cls('cost') }} text-nowrap">{{ Money::format($ticket->cost) }}</td>
                        <td data-col="due_date" class="{{ $grid->cls('due_date') }} text-nowrap {{ $overdue ? 'text-danger fw-semibold' : '' }}">
                            {{ Dates::format($ticket->due_date) }} @if ($overdue)<i class="bi bi-alarm" title="{{ __('tickets.overdue') }}"></i>@endif
                        </td>
                        <td data-col="attachments" class="{{ $grid->cls('attachments') }}">@if ($ticket->attachments_count)<i class="bi bi-paperclip"></i> {{ $ticket->attachments_count }}@endif</td>
                        <td data-col="created_at" class="{{ $grid->cls('created_at') }} text-nowrap small">{{ Dates::dateTime($ticket->created_at) }}</td>
                        <td data-col="updated_at" class="{{ $grid->cls('updated_at') }} text-nowrap small" title="{{ Dates::dateTime($ticket->updated_at) }}">{{ Dates::ago($ticket->updated_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($grid->columns) }}" class="empty-state"><i class="bi bi-inbox"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
                @include('partials.grid-totals', ['grid' => $grid])
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $tickets->links() }}</div>
        @endif
    </div>
@endsection
