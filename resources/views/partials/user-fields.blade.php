{{-- Shared fields for the admin user form and the developer customer form. Needs $subject, $projects. --}}
@php
    $err = fn ($key) => $errors->has($key) ? ' is-invalid' : '';
    $selectedProjects = array_map('intval', (array) old('projects', $subject->exists ? $subject->projects->pluck('id')->all() : []));
@endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-accent mb-3">
            <div class="card-header"><i class="bi bi-person-vcard text-brand"></i> {{ __('users.account') }}</div>
            <div class="card-body row g-3">
                @if ($showRole ?? false)
                    <div class="col-12">
                        <label class="form-label required">{{ __('users.fields.role') }}</label>
                        <div class="d-flex flex-wrap gap-2" id="role-picker">
                            @foreach (\App\Enums\Role::cases() as $role)
                                <input type="radio" class="btn-check" name="role" id="role-{{ $role->value }}" value="{{ $role->value }}"
                                       @checked(old('role', $subject->role?->value) === $role->value) @disabled($subject->is(auth()->user()) && $role !== \App\Enums\Role::Admin)>
                                <label class="btn btn-outline-{{ $role->color() }}" for="role-{{ $role->value }}">{{ $role->label() }}</label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label required">{{ __('users.fields.first_name') }}</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $subject->first_name) }}" required maxlength="100" class="form-control{{ $err('first_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">{{ __('users.fields.last_name') }}</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $subject->last_name) }}" required maxlength="100" class="form-control{{ $err('last_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">{{ __('users.fields.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $subject->email) }}" required class="form-control ltr-input{{ $err('email') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('users.fields.mobile') }}</label>
                    <input type="text" name="mobile" value="{{ old('mobile', $subject->mobile) }}" placeholder="09121234567" class="form-control ltr-input{{ $err('mobile') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label {{ $subject->exists ? '' : 'required' }}">{{ __('users.fields.password') }}</label>
                    <input type="password" name="password" autocomplete="new-password" @required(! $subject->exists) class="form-control ltr-input{{ $err('password') }}">
                    @if ($subject->exists)<div class="form-text">{{ __('users.password_keep') }}</div>@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('users.fields.password_confirmation') }}</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="form-control ltr-input">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('users.fields.locale') }}</label>
                    <select name="locale" class="form-select">
                        @foreach (__('app.languages') as $code => $label)
                            <option value="{{ $code }}" @selected(old('locale', $subject->locale) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('users.fields.calendar') }}</label>
                    <select name="calendar" class="form-select">
                        @foreach (__('app.calendars') as $code => $label)
                            <option value="{{ $code }}" @selected(old('calendar', $subject->calendar) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $subject->is_active))>
                        <label class="form-check-label" for="is_active">{{ __('users.fields.is_active') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-accent mb-3">
            <div class="card-header"><i class="bi bi-image text-brand"></i> {{ __('users.photo') }}</div>
            <div class="card-body text-center">
                @if ($subject->exists)<x-avatar :user="$subject" class="avatar-lg mb-2" />@endif
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm{{ $err('avatar') }}">
                @if ($subject->avatar_path)
                    <label class="form-check small mt-2 d-inline-block"><input type="checkbox" name="remove_avatar" value="1" class="form-check-input"> {{ __('users.remove_photo') }}</label>
                @endif
            </div>
        </div>
        <div class="card card-accent" id="projects-card">
            <div class="card-header"><i class="bi bi-kanban text-brand"></i> {{ __('users.fields.projects') }}</div>
            <div class="card-body">
                <div class="form-text mt-0 mb-2" id="projects-hint">{{ $projectsHint }}</div>
                <div style="max-height: 280px; overflow-y: auto">
                    @forelse ($projects as $project)
                        <label class="form-check mb-1">
                            <input type="checkbox" class="form-check-input" name="projects[]" value="{{ $project->id }}" @checked(in_array($project->id, $selectedProjects, true))>
                            <span class="form-check-label small">{{ $project->name }} <code class="small">{{ $project->code }}</code></span>
                        </label>
                    @empty
                        <span class="text-muted small">{{ __('app.no_results') }}</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
