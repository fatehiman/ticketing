@extends('layouts.guest')
@section('title', __('auth.forgot_title'))

@section('content')
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            @include('partials.auth-brand')

            <h1 class="h5 fw-bold mb-3"><i class="bi bi-shield-lock text-brand"></i> {{ __('auth.forgot_title') }}</h1>

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="login">{{ __('auth.forgot_login') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username"
                               placeholder="name@example.com / 0912…"
                               @class(['form-control ltr-input', 'is-invalid' => $errors->has('login')])>
                    </div>
                    @error('login')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('auth.forgot_hint') }}</div>
                </div>
                <button class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-send"></i> {{ __('auth.forgot_send') }}</button>
            </form>

            <div class="text-center mt-3">
                <a href="{{ route('login') }}" class="small"><i class="bi bi-arrow-{{ app()->getLocale() === 'fa' ? 'right' : 'left' }}"></i> {{ __('auth.back_to_login') }}</a>
            </div>
        </div>
    </div>
@endsection
