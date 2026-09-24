@extends('layouts.app')
@section('title', $sprint->exists ? __('sprints.edit') : __('sprints.new'))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-lightning-charge text-brand"></i> {{ $sprint->exists ? __('sprints.edit') : __('sprints.new') }}</h1>
        <a href="{{ route('sprints.index') }}" data-return-link class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ $sprint->exists ? route('sprints.update', $sprint) : route('sprints.store') }}" class="card card-accent" style="max-width: 760px">
        @csrf
        @if ($sprint->exists) @method('PUT') @endif
        <div class="card-body row g-3">
            <div class="col-md-8">
                <label class="form-label required">{{ __('sprints.fields.project_id') }}</label>
                <select name="project_id" class="form-select" required>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((string) old('project_id', $sprint->project_id) === (string) $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('sprints.fields.number') }}</label>
                <input type="number" min="1" name="number" value="{{ old('number', $sprint->number) }}" class="form-control ltr-input @error('number') is-invalid @enderror">
                <div class="form-text">{{ __('sprints.number_hint') }}</div>
            </div>
            <div class="col-md-8">
                <label class="form-label">{{ __('sprints.fields.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $sprint->name) }}" maxlength="150" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label required">{{ __('sprints.fields.status') }}</label>
                <select name="status" class="form-select">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $sprint->status?->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('sprints.fields.start_date') }}</label>
                <x-date-input name="start_date" :value="$sprint->start_date" />
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('sprints.fields.end_date') }}</label>
                <x-date-input name="end_date" :value="$sprint->end_date" />
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('sprints.fields.goal') }}</label>
                <textarea name="goal" rows="3" maxlength="500" class="form-control">{{ old('goal', $sprint->goal) }}</textarea>
            </div>
        </div>
        <div class="card-footer bg-transparent">
            <button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button>
        </div>
    </form>
@endsection
