@extends('layouts.app')
@section('title', __('projects.new'))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-plus-circle text-brand"></i> {{ __('projects.new') }}</h1>
        <a href="{{ route('projects.index') }}" data-return-link class="btn btn-light">{{ __('app.back') }}</a>
    </div>
    <form method="POST" action="{{ route('projects.store') }}" enctype="multipart/form-data">
        @csrf
        @include('projects._form')
        <div class="mt-3"><button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.create') }}</button></div>
    </form>
@endsection
