@extends('layouts.app')

@php
    use App\Models\Payment;
    use App\Support\Money;
    $user = auth()->user();
    $customerForm = $user->isCustomer();
    $title = $payment->exists
        ? ($customerForm ? __('transactions.edit_voucher') : __('transactions.edit_payment'))
        : ($customerForm ? __('transactions.new_voucher') : __('transactions.new_payment'));
    $err = fn ($key) => $errors->has($key) ? ' is-invalid' : '';
    $amount = old('amount', $payment->amount !== null ? number_format($payment->amount) : '');
    $back = $customerForm ? route('payments.index') : route('transactions.index');
@endphp

@section('title', $title)

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-wallet2 text-brand"></i> {{ $title }}</h1>
        <a href="{{ $back }}" data-return-link class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store') }}" class="card card-accent">
                @csrf
                @if ($payment->exists) @method('PUT') @endif
                <div class="card-body row g-3">
                    @if ($customerForm)
                        <div class="col-12">
                            <div @class(['alert border-0 mb-0', 'alert-danger' => $debt > 0, 'alert-success' => $debt <= 0])>
                                <i class="bi bi-bank"></i>
                                @if ($debt > 0)
                                    {{ __('bills.debt_now') }}: <b>{{ Money::format($debt) }}</b>.
                                    <span class="small">{{ __('transactions.amount_debt_hint') }}</span>
                                @else
                                    {{ __('bills.no_debt') }}
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="col-md-6">
                            <label class="form-label required" for="customer_id">{{ __('transactions.fields.customer') }}</label>
                            <select id="customer_id" name="customer_id" class="form-select{{ $err('customer_id') }}" required data-customer-select>
                                <option value="">{{ __('transactions.choose_customer') }}</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" data-projects="{{ $customer->projects->pluck('id')->implode(',') }}"
                                            @selected((string) old('customer_id', $payment->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            @if ($debt > 0)
                                <div class="form-text">{{ __('bills.debt_now') }}: <b>{{ Money::format($debt) }}</b></div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="status">{{ __('transactions.fields.status') }}</label>
                            <select id="status" name="status" class="form-select{{ $err('status') }}" required>
                                @foreach (Payment::STATUSES as $s)
                                    <option value="{{ $s }}" @selected(old('status', $payment->status) === $s)>{{ __('transactions.statuses.'.$s) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label" for="bill_id">{{ __('transactions.fields.bill') }} <span class="text-muted small">({{ __('app.optional') }})</span></label>
                        <select id="bill_id" name="bill_id" class="form-select{{ $err('bill_id') }}">
                            <option value="">{{ __('transactions.no_bill') }}</option>
                            @foreach ($bills as $b)
                                <option value="{{ $b->id }}" @selected((string) old('bill_id', $payment->bill_id) === (string) $b->id)>
                                    #{{ $b->number }} · {{ $b->project?->name }} · {{ Money::format($b->total) }}@unless ($customerForm) · {{ $b->customer?->name }}@endunless
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="project_id">{{ __('transactions.fields.project') }} <span class="text-muted small">({{ __('app.optional') }})</span></label>
                        <select id="project_id" name="project_id" class="form-select{{ $err('project_id') }}" @unless ($customerForm) data-follows-customer @endunless>
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
                        <div class="form-text">{{ $customerForm ? __('transactions.amount_free_hint') : __('transactions.amount_hint') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label @if ($customerForm) required @endif" for="reference_no">{{ __('transactions.fields.reference_no') }}</label>
                        <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $payment->reference_no) }}" maxlength="60" inputmode="numeric"
                               @required($customerForm) class="form-control ltr-input{{ $err('reference_no') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="paid_on">{{ __('transactions.fields.pay_date') }}</label>
                        <x-date-input id="paid_on" name="paid_on" :value="$payment->paid_on" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label @if ($customerForm) required @endif" for="paid_time">{{ __('transactions.fields.time') }}</label>
                        <input type="time" id="paid_time" name="paid_time" value="{{ old('paid_time', $payment->paid_time) }}" @required($customerForm)
                               class="form-control ltr-input{{ $err('paid_time') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">{{ __('transactions.fields.description') }}</label>
                        <textarea id="description" name="description" rows="2" maxlength="500" class="form-control{{ $err('description') }}">{{ old('description', $payment->description) }}</textarea>
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button>
                    <a href="{{ $back }}" data-return-link class="btn btn-light">{{ __('app.cancel') }}</a>
                </div>
            </form>

            @if ($payment->exists)
                @can('delete', $payment)
                    <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="mt-3" data-confirm="{{ __('app.confirm_delete') }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> {{ __('transactions.delete_payment') }}</button>
                    </form>
                @endcan
            @endif
        </div>

        @if ($customerForm)
            <div class="col-lg-4">
                <div class="card bank-card">
                    <div class="card-header"><i class="bi bi-bank text-brand"></i> {{ __('bills.how_to_pay') }}</div>
                    <div class="card-body">
                        @forelse ($banks as $bank)
                            <div @class(['pb-2 mb-2 border-bottom' => ! $loop->last])>
                                @include('partials.bank-info', ['bank' => $bank])
                            </div>
                        @empty
                            <div class="text-muted small">{{ __('bills.no_bank_info') }}</div>
                        @endforelse
                        <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('transactions.voucher_steps') }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
