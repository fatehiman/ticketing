@extends('layouts.guest')
@section('title', __('auth.sign_in'))

@section('content')
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar" style="--s:48px;border-radius:14px"><i class="bi bi-ticket-perforated fs-4"></i></span>
                    <div>
                        <div class="fw-bold fs-5">{{ __('app.name') }}</div>
                        <div class="text-muted small">{{ __('app.tagline') }}</div>
                    </div>
                </div>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('locale', 'fa') }}" @class(['btn', 'btn-primary' => app()->getLocale() === 'fa', 'btn-outline-secondary' => app()->getLocale() !== 'fa'])>فا</a>
                    <a href="{{ route('locale', 'en') }}" @class(['btn', 'btn-primary' => app()->getLocale() === 'en', 'btn-outline-secondary' => app()->getLocale() !== 'en'])>EN</a>
                </div>
            </div>

            <h1 class="h5 fw-bold mb-3">{{ __('auth.sign_in_title') }}</h1>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="login">{{ __('auth.login') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username"
                               @class(['form-control ltr-input', 'is-invalid' => $errors->has('login')])>
                    </div>
                    @error('login')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">{{ __('auth.password_label') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input id="password" type="password" name="password" required autocomplete="current-password" class="form-control ltr-input">
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                    <label class="form-check-label" for="remember">{{ __('auth.remember') }}</label>
                </div>
                <button class="btn btn-gradient w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-{{ app()->getLocale() === 'fa' ? 'left' : 'right' }}"></i> {{ __('auth.sign_in') }}
                </button>
            </form>
        </div>
    </div>
@endsection
