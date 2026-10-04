@extends('layouts.guest')
@section('title', __('auth.otp_title'))

@section('content')
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            @include('partials.auth-brand')

            <h1 class="h5 fw-bold mb-2"><i class="bi bi-phone text-brand"></i> {{ __('auth.otp_title') }}</h1>
            <p class="text-muted small mb-3">{{ __('auth.otp_intro', ['mobile' => $mobile]) }}</p>

            <form method="POST" action="{{ route('password.otp.verify') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="code">{{ __('auth.otp_label') }}</label>
                    <input id="code" type="text" name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="10"
                           @class(['form-control form-control-lg ltr-input text-center otp-input', 'is-invalid' => $errors->has('code')])>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-check2-circle"></i> {{ __('auth.otp_verify') }}</button>
            </form>

            <div class="text-center mt-4">
                @if ($canResend)
                    <div class="small text-muted mb-2" data-countdown-wrap @if ($wait === 0) hidden @endif>
                        {{ __('auth.otp_resend_in') }} <span class="fw-bold ltr-input d-inline-block" data-countdown="{{ $wait }}">{{ sprintf('%d:%02d', intdiv($wait, 60), $wait % 60) }}</span>
                    </div>
                    <form method="POST" action="{{ route('password.otp.resend') }}">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" data-countdown-button @disabled($wait > 0)>
                            @if ($nextByCall)
                                <i class="bi bi-telephone-inbound"></i> {{ __('auth.otp_call') }}
                            @else
                                <i class="bi bi-arrow-repeat"></i> {{ __('auth.otp_resend') }}
                            @endif
                        </button>
                    </form>
                    <div class="form-text">{{ __('auth.otp_same_code') }}</div>
                @else
                    <div class="small text-muted">{{ __('auth.otp_no_more') }}</div>
                @endif
                <div class="mt-3">
                    <a href="{{ route('password.request') }}" class="small"><i class="bi bi-arrow-counterclockwise"></i> {{ __('auth.start_over') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
