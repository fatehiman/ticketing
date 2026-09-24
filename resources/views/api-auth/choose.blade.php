@extends('layouts.guest')
@section('title', __('api.title'))

@section('content')
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            <h1 class="h5 fw-bold mb-2"><i class="bi bi-robot text-brand"></i> {{ __('api.title') }}</h1>
            <p class="text-muted small mb-3">{{ __('api.choose_intro') }}</p>
            @if ($auth->name)
                <p class="small mb-3"><i class="bi bi-tag"></i> {{ __('api.bot_name') }}: <b>{{ $auth->name }}</b></p>
            @endif

            <form method="POST" action="{{ route('api-auth.store', $auth->public_id) }}">
                @csrf
                <div class="list-group mb-3">
                    @foreach ($projects as $project)
                        <label class="list-group-item d-flex gap-2 align-items-center">
                            <input class="form-check-input m-0" type="radio" name="project" value="{{ $project->id }}" @checked($loop->first)>
                            <span><b>{{ $project->name }}</b> <span class="text-muted small ltr-input d-inline-block">{{ $project->code }}</span></span>
                        </label>
                    @endforeach
                    @unless ($auth->require_project)
                        <label class="list-group-item d-flex gap-2 align-items-center">
                            <input class="form-check-input m-0" type="radio" name="project" value="none">
                            <span class="text-muted">{{ __('api.no_fixed_project') }}</span>
                        </label>
                    @endunless
                </div>
                @error('project')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-check2-circle"></i> {{ __('api.confirm') }}</button>
            </form>
        </div>
    </div>
@endsection
