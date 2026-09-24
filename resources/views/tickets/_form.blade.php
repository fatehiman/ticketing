@php
    use App\Support\Duration;
    use App\Support\Money;
    $user = auth()->user();
    $staff = $user->isStaff();
    $val = fn ($key, $default = null) => old($key, $default);
    $err = fn ($key) => $errors->has($key) ? ' is-invalid' : '';
    $currency = $ticket->project?->currency;
@endphp

@push('head')
    <script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
@endpush

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-accent">
            <div class="card-header"><i class="bi bi-card-text text-brand"></i> {{ __('tickets.details') }}</div>
            <div class="card-body">
                @unless ($staff)
                    <div class="alert alert-info border-0 small"><i class="bi bi-info-circle"></i> {{ __('tickets.customer_note') }}</div>
                @endunless
                <div class="mb-3">
                    <label class="form-label required" for="title">{{ __('tickets.fields.title') }}</label>
                    <input type="text" id="title" name="title" maxlength="255" required value="{{ $val('title', $ticket->title) }}" class="form-control form-control-lg{{ $err('title') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="content">{{ __('tickets.fields.content') }}</label>
                    <textarea id="content" name="content" data-editor rows="12" class="form-control" placeholder="{{ __('tickets.content_placeholder') }}">{{ $val('content', $ticket->content) }}</textarea>
                </div>
                <div>
                    <label class="form-label" for="attachments"><i class="bi bi-paperclip"></i> {{ __('tickets.fields.attachments') }}</label>
                    <input type="file" id="attachments" name="attachments[]" multiple class="form-control{{ $err('attachments') }}{{ $err('attachments.*') }}"
                           accept=".{{ implode(',.', \App\Http\Controllers\TicketController::FILE_TYPES) }}">
                    <div class="form-text">{{ __('tickets.attachments_hint') }}</div>
                    @if ($ticket->exists && $ticket->attachments->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @foreach ($ticket->attachments as $file)
                                <div class="attachment-item">
                                    <i class="bi {{ $file->icon() }}"></i>
                                    <a href="{{ route('attachments.show', $file) }}" class="small text-truncate" style="max-width: 180px">{{ $file->original_name }}</a>
                                    <button type="submit" form="del-att-{{ $file->id }}" class="btn btn-sm btn-link text-danger p-0" title="{{ __('app.delete') }}"><i class="bi bi-x-lg"></i></button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-accent mb-3">
            <div class="card-header"><i class="bi bi-sliders text-brand"></i> {{ __('tickets.fields.status') }} & {{ __('tickets.fields.priority') }}</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required" for="project_id">{{ __('tickets.fields.project_id') }}</label>
                    <select id="project_id" name="project_id" required data-project-select class="form-select{{ $err('project_id') }}">
                        @if ($projects->count() > 1)<option value="">—</option>@endif
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) $val('project_id', $ticket->project_id) === (string) $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6 mb-2">
                        <label class="form-label required" for="type">{{ __('tickets.fields.type') }}</label>
                        <select id="type" name="type" class="form-select">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected($val('type', $ticket->type?->value) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label required" for="priority">{{ __('tickets.fields.priority') }}</label>
                        <select id="priority" name="priority" class="form-select">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected($val('priority', $ticket->priority?->value) === $priority->value) style="color: {{ $priority->color() }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if ($staff)
                    <div class="mb-2">
                        <label class="form-label required" for="status">{{ __('tickets.fields.status') }}</label>
                        <select id="status" name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($val('status', $ticket->status?->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="assignee_id">{{ __('tickets.fields.assignee_id') }}</label>
                        <select id="assignee_id" name="assignee_id" data-follows-project class="form-select{{ $err('assignee_id') }}">
                            <option value="">{{ __('tickets.unassigned') }}</option>
                            @foreach ($developers as $dev)
                                <option value="{{ $dev->id }}" data-projects="{{ $dev->projects->pluck('id')->implode(',') }}" @selected((string) $val('assignee_id', $ticket->assignee_id) === (string) $dev->id)>{{ $dev->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="sprint_id">{{ __('tickets.fields.sprint_id') }}</label>
                        <select id="sprint_id" name="sprint_id" data-follows-project class="form-select{{ $err('sprint_id') }}">
                            <option value="">{{ __('tickets.no_sprint') }}</option>
                            @foreach ($sprints as $sprint)
                                <option value="{{ $sprint->id }}" data-projects="{{ $sprint->project_id }}" @selected((string) $val('sprint_id', $ticket->sprint_id) === (string) $sprint->id)>{{ $sprint->label() }} ({{ $sprint->status->label() }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        @if ($staff)
            <div class="card card-accent">
                <div class="card-header"><i class="bi bi-calculator text-brand"></i> {{ __('tickets.planning') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 mb-1">
                            <label class="form-label" for="story_points">{{ __('tickets.fields.story_points') }}</label>
                            <select id="story_points" name="story_points" class="form-select">
                                <option value="">—</option>
                                @foreach ($storyPoints as $sp)
                                    <option value="{{ $sp->value }}" @selected((string) $val('story_points', $ticket->story_points) === (string) $sp->value)>{{ $sp->fullLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 mb-1">
                            <label class="form-label" for="done_story_points">{{ __('tickets.fields.done_story_points') }}</label>
                            <input type="number" min="0" max="999" id="done_story_points" name="done_story_points" value="{{ $val('done_story_points', $ticket->done_story_points) }}" class="form-control ltr-input{{ $err('done_story_points') }}">
                        </div>
                        <div class="col-6 mb-1">
                            <label class="form-label" for="due_date">{{ __('tickets.fields.due_date') }}</label>
                            <x-date-input name="due_date" :value="$ticket->due_date" id="due_date" />
                        </div>
                        <div class="col-6 mb-1">
                            <label class="form-label" for="estimated_time">{{ __('tickets.fields.estimated_minutes') }}</label>
                            <input type="text" id="estimated_time" name="estimated_time" data-duration placeholder="00:00" value="{{ $val('estimated_time', Duration::format($ticket->estimated_minutes)) }}" class="form-control ltr-input{{ $err('estimated_time') }}">
                        </div>
                        <div class="col-6 mb-1">
                            <label class="form-label" for="logged_time">{{ __('tickets.fields.logged_minutes') }}</label>
                            <input type="text" id="logged_time" name="logged_time" data-duration placeholder="00:00" value="{{ $val('logged_time', Duration::format($ticket->logged_minutes)) }}" class="form-control ltr-input{{ $err('logged_time') }}">
                        </div>
                        <div class="col-12"><div class="form-text mt-0 mb-2">{{ __('tickets.time_hint') }}</div></div>
                        <div class="col-6">
                            <label class="form-label" for="estimated_cost">{{ __('tickets.fields.estimated_cost') }}</label>
                            <input type="text" id="estimated_cost" name="estimated_cost" data-money inputmode="decimal" value="{{ $val('estimated_cost', $ticket->estimated_cost) }}" class="form-control ltr-input{{ $err('estimated_cost') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="cost">{{ __('tickets.fields.cost') }}</label>
                            <input type="text" id="cost" name="cost" data-money inputmode="decimal" value="{{ $val('cost', $ticket->cost) }}" class="form-control ltr-input{{ $err('cost') }}">
                        </div>
                        @if ($currency)
                            <div class="col-12"><div class="form-text">{{ __('projects.fields.currency') }}: {{ __('app.currency.'.$currency) }}</div></div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
