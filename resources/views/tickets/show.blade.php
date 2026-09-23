@extends('layouts.app')
@section('title', '#'.$ticket->number.' '.$ticket->title)

@php
    use App\Enums\StoryPoint;
    use App\Support\Dates;
    use App\Support\Duration;
    use App\Support\Money;
    use App\Support\RevisionPresenter;
    $user = auth()->user();
    $currency = $ticket->project->currency;
    $overdue = $ticket->due_date && $ticket->due_date->isPast() && ! $ticket->status->isClosed();
@endphp

@section('content')
    <div class="page-head align-items-start">
        <div class="min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="t-number fs-5">#{{ $ticket->number }}</span>
                <x-status :status="$ticket->status" />
                <x-priority :priority="$ticket->priority" />
                <span class="small text-muted"><i class="bi {{ $ticket->type->icon() }}"></i> {{ $ticket->type->label() }}</span>
            </div>
            <h1 class="text-break">{{ $ticket->title }}</h1>
            <div class="sub"><i class="bi bi-kanban"></i> {{ $ticket->project->name }} · {{ Dates::dateTime($ticket->created_at) }}</div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @can('changeStatus', $ticket)
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-arrow-repeat"></i> {{ __('tickets.change_status') }}</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        @foreach ($statuses as $status)
                            <li>
                                <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $status->value }}">
                                    <button class="dropdown-item d-flex align-items-center gap-2" @disabled($ticket->status === $status)>
                                        <i class="bi {{ $status->icon() }}" style="color: {{ $status->color() }}"></i> {{ $status->label() }}
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elsecan('cancel', $ticket)
                <form method="POST" action="{{ route('tickets.status', $ticket) }}" data-confirm="{{ __('tickets.confirm_cancel') }}">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <button class="btn btn-outline-secondary"><i class="bi bi-slash-circle"></i> {{ __('tickets.cancel_ticket') }}</button>
                </form>
            @endcan
            @can('update', $ticket)
                <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
            @endcan
            @can('delete', $ticket)
                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" data-confirm="{{ __('app.confirm_delete') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            @endcan
        </div>
    </div>

    @if ($user->isCustomer() && $ticket->reporter_id === $user->id && $ticket->status !== \App\Enums\TicketStatus::PendingReview)
        <div class="alert alert-light border small"><i class="bi bi-lock"></i> {{ __('tickets.locked_note') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card card-accent mb-3">
                <div class="card-body ticket-content">
                    @if ($ticket->content)
                        {!! $ticket->content !!}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-paperclip text-brand"></i> {{ __('tickets.fields.attachments') }} ({{ $ticket->attachments->count() }})</div>
                <div class="card-body">
                    @forelse ($ticket->attachments as $file)
                        <div class="attachment-item mb-2">
                            @if ($file->isImage())
                                <a href="{{ route('attachments.show', [$file, 'inline' => 1]) }}" target="_blank">
                                    <img src="{{ route('attachments.show', [$file, 'inline' => 1]) }}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px">
                                </a>
                            @else
                                <i class="bi {{ $file->icon() }}"></i>
                            @endif
                            <div class="min-w-0 flex-grow-1">
                                <a href="{{ route('attachments.show', $file) }}" class="fw-semibold small text-break">{{ $file->original_name }}</a>
                                <div class="text-muted" style="font-size:.75rem">{{ $file->humanSize() }} · {{ $file->user?->name }} · {{ Dates::dateTime($file->created_at) }}</div>
                            </div>
                            <a href="{{ route('attachments.show', $file) }}" class="btn btn-sm btn-light" title="{{ __('app.download') }}"><i class="bi bi-download"></i></a>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('tickets.no_attachments') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card mb-3" id="comments">
                <div class="card-header"><i class="bi bi-chat-dots text-brand"></i> {{ __('tickets.comments') }} ({{ $ticket->comments->count() }})</div>
                <div class="card-body">
                    @forelse ($ticket->comments as $comment)
                        <div class="d-flex gap-2 mb-3">
                            <x-avatar :user="$comment->user" class="avatar-sm" />
                            <div class="flex-grow-1 min-w-0">
                                <div class="small mb-1"><span class="fw-semibold">{{ $comment->user?->name }}</span>
                                    <span class="text-muted" title="{{ Dates::dateTime($comment->created_at) }}">· {{ Dates::ago($comment->created_at) }}</span></div>
                                <div class="comment">{{ $comment->body }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small mb-3">{{ __('tickets.no_comments') }}</div>
                    @endforelse
                    @can('comment', $ticket)
                        <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}">
                            @csrf
                            <textarea name="body" rows="3" class="form-control mb-2" placeholder="{{ __('tickets.write_comment') }}" required maxlength="10000"></textarea>
                            <button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> {{ __('tickets.add_comment') }}</button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-clock-history text-brand"></i> {{ __('tickets.history') }}</div>
                <div class="card-body">
                    <ul class="timeline">
                        @forelse ($ticket->revisions as $rev)
                            <li>
                                <div class="small"><span class="fw-semibold">{{ $rev->user?->name ?? __('app.unknown') }}</span>
                                    {{ __('tickets.actions.'.$rev->action) }}
                                    <span class="text-muted" title="{{ Dates::dateTime($rev->created_at) }}">· {{ Dates::dateTime($rev->created_at) }}</span>
                                </div>
                                @foreach ($rev->changes ?? [] as $field => $change)
                                    <div class="change-row">
                                        {{ RevisionPresenter::field($field) }}:
                                        @if ($field === 'content')
                                            <em>{{ __('tickets.actions.updated') }}</em>
                                        @elseif ($field === 'attachments')
                                            @if ($change['old'])<del>{{ $change['old'] }}</del>@endif
                                            @if ($change['new'])<ins>+ {{ $change['new'] }}</ins>@endif
                                        @else
                                            <del>{{ RevisionPresenter::value($field, $change['old']) }}</del>
                                            <i class="bi bi-arrow-{{ app()->getLocale() === 'fa' ? 'left' : 'right' }}-short"></i>
                                            <ins>{{ RevisionPresenter::value($field, $change['new']) }}</ins>
                                        @endif
                                    </div>
                                @endforeach
                            </li>
                        @empty
                            <li class="text-muted small">{{ __('tickets.no_history') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-accent mb-3">
                <div class="card-header"><i class="bi bi-people text-brand"></i> {{ __('tickets.people') }}</div>
                <div class="card-body">
                    <dl class="meta-list mb-0">
                        <dt>{{ __('tickets.fields.assignee_id') }}</dt>
                        <dd>
                            @if ($ticket->assignee)
                                <x-avatar :user="$ticket->assignee" class="avatar-sm" /> {{ $ticket->assignee->name }}
                            @else
                                <span class="text-muted">{{ __('tickets.unassigned') }}</span>
                            @endif
                        </dd>
                        <dt>{{ __('tickets.fields.reporter_id') }}</dt>
                        <dd><x-avatar :user="$ticket->reporter" class="avatar-sm" /> {{ $ticket->reporter?->name }}
                            <span class="badge text-bg-light">{{ $ticket->reporter?->role->label() }}</span></dd>
                        <dt>{{ __('tickets.last_update') }}</dt>
                        <dd class="mb-0">{{ $ticket->editor?->name }} · <span class="text-muted small">{{ Dates::dateTime($ticket->updated_at) }}</span></dd>
                    </dl>
                </div>
            </div>
            <div class="card card-accent">
                <div class="card-header"><i class="bi bi-calculator text-brand"></i> {{ __('tickets.planning') }}</div>
                <div class="card-body">
                    <dl class="meta-list row mb-0">
                        <dt class="col-6">{{ __('tickets.fields.sprint_id') }}</dt>
                        <dd class="col-6">{{ $ticket->sprint?->label() ?? '—' }}</dd>
                        <dt class="col-6">{{ __('tickets.fields.story_points') }}</dt>
                        <dd class="col-6">{{ StoryPoint::labelFor($ticket->story_points) ?? '—' }}</dd>
                        <dt class="col-6">{{ __('tickets.fields.done_story_points') }}</dt>
                        <dd class="col-6">{{ $ticket->done_story_points ?? '—' }}</dd>
                        <dt class="col-6">{{ __('tickets.fields.estimated_minutes') }}</dt>
                        <dd class="col-6 ltr-input">{{ Duration::format($ticket->estimated_minutes) ?: '—' }}</dd>
                        <dt class="col-6">{{ __('tickets.fields.logged_minutes') }}</dt>
                        <dd class="col-6 ltr-input">{{ Duration::format($ticket->logged_minutes) ?: '—' }}</dd>
                        <dt class="col-6">{{ __('tickets.fields.estimated_cost') }}</dt>
                        <dd class="col-6">{{ Money::format($ticket->estimated_cost, $currency) ?: '—' }}</dd>
                        <dt class="col-6">{{ __('tickets.fields.cost') }}</dt>
                        <dd class="col-6">{{ Money::format($ticket->cost, $currency) ?: '—' }}</dd>
                        <dt class="col-6">{{ __('tickets.fields.due_date') }}</dt>
                        <dd class="col-6 {{ $overdue ? 'text-danger' : '' }}">{{ Dates::format($ticket->due_date) ?: '—' }} @if ($overdue)<i class="bi bi-alarm"></i>@endif</dd>
                        @if ($ticket->resolved_at)
                            <dt class="col-6">{{ __('tickets.fields.resolved_at') }}</dt>
                            <dd class="col-6">{{ Dates::dateTime($ticket->resolved_at) }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
