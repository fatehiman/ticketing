@extends('layouts.app')
@section('title', $payment->exists ? __('transactions.edit_payment') : __('transactions.new_payment'))

@php
    $err = fn ($key) => $errors->has($key) ? ' is-invalid' : '';
    $amount = old('amount', $payment->amount !== null ? number_format($payment->amount) : '');
@endphp

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-wallet2 text-brand"></i> {{ $payment->exists ? __('transactions.edit_payment') : __('transactions.new_payment') }}</h1>
        <a href="{{ route('transactions.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store') }}" class="card card-accent" style="max-width: 760px">
        @csrf
        @if ($payment->exists) @method('PUT') @endif
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="customer_id">{{ __('transactions.fields.customer') }}</label>
                <select id="customer_id" name="customer_id" class="form-select{{ $err('customer_id') }}" required data-customer-select>
                    <option value="">{{ __('transactions.choose_customer') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" data-projects="{{ $customer->projects->pluck('id')->implode(',') }}"
                                @selected((string) old('customer_id', $payment->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="project_id">{{ __('transactions.fields.project') }} <span class="text-muted small">({{ __('app.optional') }})</span></label>
                <select id="project_id" name="project_id" class="form-select{{ $err('project_id') }}" data-follows-customer>
                    <option value="">{{ __('transactions.no_project') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" data-project="{{ $project->id }}"
                                @selected((string) old('project_id', $payment->project_id) === (string) $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="amount">{{ __('transactions.fields.amount') }}</label>
                <input type="text" id="amount" name="amount" value="{{ $amount }}" required inputmode="numeric"
                       data-money="int" class="form-control ltr-input{{ $err('amount') }}">
                <div class="form-text">{{ __('transactions.amount_hint') }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="paid_on">{{ __('transactions.fields.date') }}</label>
                <x-date-input id="paid_on" name="paid_on" :value="$payment->paid_on" required />
            </div>
            <div class="col-12">
                <label class="form-label" for="description">{{ __('transactions.fields.description') }}</label>
                <textarea id="description" name="description" rows="3" maxlength="500" class="form-control{{ $err('description') }}">{{ old('description', $payment->description) }}</textarea>
            </div>
        </div>
        <div class="card-footer bg-transparent d-flex gap-2">
            <button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button>
            <a href="{{ route('transactions.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>

    @if ($payment->exists)
        <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="mt-3" data-confirm="{{ __('app.confirm_delete') }}">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> {{ __('transactions.delete_payment') }}</button>
        </form>
    @endif
@endsection
