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
    $awaitingMe = $ticket->isAwaiting($user);
@endphp

@can('reply', $ticket)
    @push('head')
        <script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    @endpush
@endcan

@section('content')
    <div class="page-head align-items-start">
        <div class="min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="t-number fs-5">#{{ $ticket->number }}</span>
                <x-status :status="$ticket->status" />
                <x-priority :priority="$ticket->priority" />
                <span class="small text-muted"><i class="bi {{ $ticket->type->icon() }}"></i> {{ $ticket->type->label() }}</span>
                @if ($awaitingMe)
                    <a href="#followups" class="badge text-bg-danger text-decoration-none"><i class="bi bi-reply-fill"></i> {{ __('tickets.followup.awaiting_badge') }}</a>
                @endif
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

            <div class="card mb-3" id="followups">
                <div class="card-header"><i class="bi bi-chat-left-text text-brand"></i> {{ __('tickets.followup.title') }} ({{ $ticket->followups->count() }})</div>
                <div class="card-body">
                    @if ($awaitingMe)
                        <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2 py-2" role="status">
                            <i class="bi bi-reply-fill"></i>
                            <span class="flex-grow-1 small">{{ __('tickets.followup.awaiting_you') }}</span>
                            <form method="POST" action="{{ route('tickets.read', $ticket) }}">
                                @csrf
                                <button class="btn btn-sm btn-light"><i class="bi bi-check2-all"></i> {{ __('tickets.followup.mark_read') }}</button>
                            </form>
                        </div>
                    @elseif ($ticket->awaiting_reply)
                        <div class="small text-muted mb-3"><i class="bi bi-hourglass-split"></i>
                            {{ __('tickets.followup.awaiting_other.'.$ticket->awaiting_reply) }}
                            @if ($ticket->awaiting_since) · {{ Dates::ago($ticket->awaiting_since) }} @endif
                        </div>
                    @endif

                    @forelse ($ticket->followups as $followup)
                        @php($staffSide = (bool) $followup->user?->isStaff())
                        <div @class(['followup mb-3', 'followup-staff' => $staffSide, 'followup-customer' => ! $staffSide]) id="followup-{{ $followup->id }}">
                            <div class="followup-head">
                                <x-avatar :user="$followup->user" class="avatar-sm" />
                                <span class="fw-semibold">{{ $followup->user?->name }}</span>
                                <span class="badge text-bg-light">{{ $followup->user?->role->label() }}</span>
                                @if ($staffSide && ! $followup->awaits_reply && $user->isStaff())
                                    <span class="badge text-bg-light" title="{{ __('tickets.followup.no_reply_needed') }}"><i class="bi bi-bell-slash"></i></span>
                                @endif
                                <span class="text-muted ms-auto" title="{{ Dates::ago($followup->created_at) }}">{{ Dates::dateTime($followup->created_at) }}</span>
                            </div>
                            @if ($followup->body)
                                <div class="followup-body ticket-content">{!! $followup->body !!}</div>
                            @endif
                            @if ($followup->attachments->isNotEmpty())
                                <div @class(['d-flex flex-wrap gap-2 px-3 pb-3', 'pt-3' => ! $followup->body])>
                                    @foreach ($followup->attachments as $file)
                                        <a href="{{ $file->isImage() ? route('attachments.show', [$file, 'inline' => 1]) : route('attachments.show', $file) }}" @if ($file->isImage()) target="_blank" @endif
                                           class="attachment-item text-decoration-none py-1 small">
                                            <i class="bi {{ $file->icon() }}" style="font-size:1.1rem"></i>
                                            <span class="text-break">{{ $file->original_name }}</span>
                                            <span class="text-muted">{{ $file->humanSize() }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small mb-3">{{ __('tickets.followup.none') }}</div>
                    @endforelse

                    @can('reply', $ticket)
                        <form method="POST" action="{{ route('tickets.followups.store', $ticket) }}" enctype="multipart/form-data" class="mt-3">
                            @csrf
                            <label class="form-label fw-semibold" for="followup-body"><i class="bi bi-reply"></i> {{ __('tickets.followup.new') }}</label>
                            <textarea id="followup-body" name="body" data-editor rows="6" class="form-control">{{ old('body') }}</textarea>
                            <div class="mt-2">
                                <input type="file" name="attachments[]" multiple class="form-control form-control-sm"
                                       accept=".{{ implode(',.', \App\Http\Controllers\TicketController::FILE_TYPES) }}">
                                <div class="form-text">{{ __('tickets.attachments_hint') }}</div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                <button class="btn btn-primary"><i class="bi bi-send"></i> {{ __('tickets.followup.send') }}</button>
                                @if ($user->isStaff())
                                    <div class="form-check mb-0">
                                        <input type="hidden" name="awaits_reply" value="0">
                                        <input class="form-check-input" type="checkbox" name="awaits_reply" value="1" id="awaits_reply" @checked(old('awaits_reply', '1') === '1')>
                                        <label class="form-check-label small" for="awaits_reply">{{ __('tickets.followup.awaits_customer') }}</label>
                                    </div>
                                @endif
                            </div>
                        </form>
                    @elseif ($ticket->status->isClosed() && $user->isCustomer())
                        <div class="text-muted small"><i class="bi bi-lock"></i> {{ __('tickets.followup.closed_note') }}</div>
                    @endif
                </div>
            </div>

            @if ($ticket->status->isClosed())
                @php($comment = $ticket->comment)
                <div class="card mb-3" id="rating">
                    <div class="card-header"><i class="bi bi-star text-brand"></i> {{ __('tickets.rating.title') }}</div>
                    <div class="card-body">
                        @if ($comment)
                            <div class="d-flex gap-2">
                                <x-avatar :user="$comment->user" class="avatar-sm" />
                                <div class="flex-grow-1 min-w-0">
                                    <div class="small mb-1"><span class="fw-semibold">{{ $comment->user?->name }}</span>
                                        <span class="text-muted">· {{ Dates::dateTime($comment->updated_at) }}</span></div>
                                    <div class="stars fs-5" title="{{ $comment->rating }}/5">
                                        @for ($i = 1; $i <= 5; $i++)<i @class(['bi', 'bi-star-fill' => $i <= $comment->rating, 'bi-star off' => $i > $comment->rating])></i>@endfor
                                    </div>
                                    @if ($comment->body)<div class="comment mt-2">{{ $comment->body }}</div>@endif
                                </div>
                            </div>
                        @elsecannot('comment', $ticket)
                            <div class="text-muted small">{{ __('tickets.rating.none') }}</div>
                        @endif

                        @can('comment', $ticket)
                            <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" @class(['border-top pt-3 mt-3' => $comment])>
                                @csrf
                                <div class="small text-muted mb-1">{{ $comment ? __('tickets.rating.change') : __('tickets.rating.ask') }}</div>
                                <div class="star-input mb-2" role="radiogroup" aria-label="{{ __('tickets.rating.title') }}">
                                    @for ($i = 5; $i >= 1; $i--)
                                        <input type="radio" name="rating" value="{{ $i }}" id="star-{{ $i }}" required @checked((int) old('rating', $comment?->rating) === $i)>
                                        <label for="star-{{ $i }}" title="{{ $i }}/5"><i class="bi bi-star-fill"></i></label>
                                    @endfor
                                </div>
                                <textarea name="body" rows="2" class="form-control mb-2" maxlength="5000" placeholder="{{ __('tickets.rating.body_placeholder') }}">{{ old('body', $comment?->body) }}</textarea>
                                <button class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> {{ __('tickets.rating.submit') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endif

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
