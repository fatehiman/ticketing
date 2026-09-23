@extends('layouts.app')
@section('title', $customer->exists ? __('users.edit_customer') : __('users.new_customer'))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-people text-brand"></i> {{ $customer->exists ? __('users.edit_customer').': '.$customer->name : __('users.new_customer') }}</h1>
        <a href="{{ route('customers.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($customer->exists) @method('PUT') @endif
        @include('partials.user-fields', ['subject' => $customer, 'projectsHint' => __('users.projects_hint_developer')])
        <div class="mt-3"><button class="btn btn-gradient px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button></div>
    </form>
@endsection
