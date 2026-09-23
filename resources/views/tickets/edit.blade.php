@extends('layouts.app')
@section('title', __('tickets.edit', ['number' => $ticket->number]))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-pencil-square text-brand"></i> {{ __('tickets.edit', ['number' => $ticket->number]) }}</h1>
        <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-light"><i class="bi bi-arrow-{{ app()->getLocale() === 'fa' ? 'right' : 'left' }}"></i> {{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('tickets.update', $ticket) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('tickets._form')
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button>
            <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>

    {{-- Attachment delete forms live outside the main form (forms cannot be nested). --}}
    @foreach ($ticket->attachments as $file)
        <form method="POST" action="{{ route('attachments.destroy', $file) }}" id="del-att-{{ $file->id }}" data-confirm="{{ __('app.confirm_delete') }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endsection
