@extends('layouts.app')
@section('title', __('projects.edit'))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-pencil-square text-brand"></i> {{ __('projects.edit') }}: {{ $project->name }}</h1>
        <div class="d-flex gap-2">
            @can('delete', $project)
                <form method="POST" action="{{ route('projects.destroy', $project) }}" data-confirm="{{ __('app.confirm_delete') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('app.delete') }}</button>
                </form>
            @endcan
            <a href="{{ route('projects.show', $project) }}" class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>
    <form method="POST" action="{{ route('projects.update', $project) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('projects._form')
        <div class="mt-3"><button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button></div>
    </form>
@endsection
