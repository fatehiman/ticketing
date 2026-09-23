@extends('layouts.guest')
@section('title', $code)

@section('content')
    <div class="card auth-card shadow-lg border-0 text-center">
        <div class="card-body p-5">
            <div class="display-3 fw-bold text-brand mb-2">{{ $code }}</div>
            <p class="text-muted mb-4">{{ $message }}</p>
            <a href="{{ url('/') }}" class="btn btn-gradient"><i class="bi bi-house"></i> {{ __('app.errors.home') }}</a>
        </div>
    </div>
@endsection
