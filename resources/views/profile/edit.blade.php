@extends('layouts.app')
@section('title', __('app.profile'))

@php
    use App\Support\Dates;
    $admin = $user->isAdmin();
@endphp

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-person-circle text-brand"></i> {{ __('app.profile') }}</h1>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card card-accent text-center mb-3">
                <div class="card-body">
                    <x-avatar :user="$user" class="avatar-lg mb-2" />
                    <div class="fw-bold fs-5">{{ $user->name }}</div>
                    <span class="badge text-bg-{{ $user->role->color() }}">{{ $user->role->label() }}</span>
                    <hr>
                    <div class="small text-muted mb-2">{{ __('users.photo') }}</div>
                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="d-flex gap-2 justify-content-center">
                        @csrf
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" required>
                        <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-upload"></i> {{ __('app.upload') }}</button>
                    </form>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.remove') }}" class="mt-2" data-confirm="{{ __('app.confirm_delete') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i> {{ __('users.remove_photo') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-key text-brand"></i> {{ __('users.change_password') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <label class="form-label">{{ __('users.fields.current_password') }}</label>
                            <input type="password" name="current_password" class="form-control ltr-input" required autocomplete="current-password">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('users.fields.password') }}</label>
                            <input type="password" name="password" class="form-control ltr-input" required autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('users.fields.password_confirmation') }}</label>
                            <input type="password" name="password_confirmation" class="form-control ltr-input" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('app.save') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <div class="card card-accent mb-3">
                    <div class="card-header"><i class="bi bi-person-vcard text-brand"></i> {{ __('users.account') }}</div>
                    <div class="card-body">
                        @unless ($admin)
                            <div class="alert alert-light border small"><i class="bi bi-lock"></i> {{ __('users.readonly_note') }}</div>
                        @endunless
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('users.fields.first_name') }}</label>
                                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="form-control" @readonly(! $admin) @disabled(! $admin) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('users.fields.last_name') }}</label>
                                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" class="form-control" @readonly(! $admin) @disabled(! $admin) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('users.fields.email') }}</label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control ltr-input" @readonly(! $admin) @disabled(! $admin) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('users.fields.mobile') }}</label>
                                <input type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" class="form-control ltr-input" @readonly(! $admin) @disabled(! $admin)>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-accent">
                    <div class="card-header"><i class="bi bi-palette text-brand"></i> {{ __('users.preferences') }}</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.language') }}</label>
                                <select name="locale" class="form-select">
                                    @foreach (__('app.languages') as $code => $label)
                                        <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.calendar') }}</label>
                                <select name="calendar" class="form-select">
                                    @foreach (__('app.calendars') as $code => $label)
                                        <option value="{{ $code }}" @selected(old('calendar', $user->calendar) === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ Dates::format(now()) }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('app.theme') }}</label>
                                <input type="text" class="form-control" value="{{ __('app.themes.'.($user->locale === 'fa' ? 'rtl' : 'ltr')) }}" disabled>
                                <div class="form-text">{{ __('app.theme_note') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <button class="btn btn-gradient px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
