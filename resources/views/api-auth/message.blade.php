@extends('layouts.guest')
@section('title', __('api.title'))

@section('content')
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="mb-3">
                @if ($success)
                    <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
                @else
                    <i class="bi bi-x-octagon-fill text-danger" style="font-size:3.5rem"></i>
                @endif
            </div>
            <h1 class="h5 fw-bold mb-3">{{ __('api.title') }}</h1>
            <p class="mb-4">{{ $text }}</p>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary"><i class="bi bi-house"></i> {{ __('app.dashboard') }}</a>
        </div>
    </div>
@endsection
