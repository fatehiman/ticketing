@extends('layouts.app')
@section('title', __('tickets.new'))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-plus-circle text-brand"></i> {{ __('tickets.new') }}</h1>
        <a href="{{ url()->previous() }}" class="btn btn-light"><i class="bi bi-arrow-{{ app()->getLocale() === 'fa' ? 'right' : 'left' }}"></i> {{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data">
        @csrf
        @include('tickets._form')
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-gradient px-4"><i class="bi bi-send"></i> {{ __('app.create') }}</button>
            <a href="{{ route('tickets.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
