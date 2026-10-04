@extends('layouts.guest')
@section('title', __('auth.new_password_title'))

@section('content')
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            @include('partials.auth-brand')

            <h1 class="h5 fw-bold mb-3"><i class="bi bi-key text-brand"></i> {{ __('auth.new_password_title') }}</h1>

            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($token)
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label class="form-label" for="email">{{ __('users.fields.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required readonly class="form-control ltr-input">
                    </div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="password">{{ __('auth.new_password') }}</label>
                    <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                           @class(['form-control ltr-input', 'is-invalid' => $errors->has('password')])>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">{{ __('auth.new_password_confirm') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control ltr-input">
                </div>
                <button class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-check2"></i> {{ __('auth.new_password_save') }}</button>
            </form>

            <div class="text-center mt-3">
                <a href="{{ route('login') }}" class="small">{{ __('auth.back_to_login') }}</a>
            </div>
        </div>
    </div>
@endsection
