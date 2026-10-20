@extends('layouts.app')
@section('title', __('settings.title'))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-gear text-brand"></i> {{ __('settings.title') }}</h1>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')
        <div class="card" style="max-width: 640px">
            <div class="card-header"><i class="bi bi-paperclip text-brand"></i> {{ __('settings.attachments') }}</div>
            <div class="card-body">
                <label class="form-label" for="attachment_max_mb">{{ __('settings.attachment_max_mb') }}</label>
                <div class="input-group" style="max-width: 220px">
                    <input type="number" id="attachment_max_mb" name="attachment_max_mb" min="1" max="{{ $serverMaxMb }}" required
                           value="{{ old('attachment_max_mb', $attachmentMaxMb) }}"
                           @class(['form-control', 'is-invalid' => $errors->has('attachment_max_mb')])>
                    <span class="input-group-text">MB</span>
                </div>
                @error('attachment_max_mb')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('settings.attachment_max_hint', ['max' => $serverMaxMb]) }}</div>
            </div>
        </div>
        <div class="mt-3"><button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button></div>
    </form>
@endsection
