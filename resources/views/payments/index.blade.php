@extends('layouts.app')
@section('title', __('transactions.vouchers'))

@php
    use App\Models\Payment;
    use App\Support\Dates;
    use App\Support\Money;
    $user = auth()->user();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-wallet2 text-brand"></i> {{ __('transactions.vouchers') }}</h1>
            <div class="sub">{{ $user->isCustomer() ? __('transactions.vouchers_customer') : __('transactions.vouchers_staff') }} · {{ __('app.results', ['count' => $payments->total()]) }}</div>
        </div>
        @can('create', Payment::class)
            <a href="{{ route('payments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ $user->isCustomer() ? __('transactions.new_voucher') : __('transactions.new_payment') }}</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('payments.index') }}" class="card filter-card mb-3" data-clean-submit>
        <div class="card-body pb-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">{{ __('transactions.fields.status') }}</label>
                    <select name="status" class="form-select" data-autosubmit>
                        <option value="">{{ __('app.all') }}</option>
                        @foreach (Payment::STATUSES as $s)
                            <option value="{{ $s }}" @selected($status === $s)>{{ __('transactions.statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($user->isStaff())
                    <div class="col-md-4">
                        <label class="form-label">{{ __('transactions.fields.customer') }}</label>
                        <select name="customer_id" class="form-select" data-autosubmit>
                            <option value="">{{ __('transactions.all_customers') }}</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected($customerId === $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-1">
                    <a href="{{ route('payments.index') }}" class="btn btn-light w-100" title="{{ __('app.reset') }}"><i class="bi bi-x-circle"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-grid mb-0">
                <thead>
                <tr>
                    <th>{{ __('transactions.fields.date') }}</th>
                    @if ($user->isStaff())<th>{{ __('transactions.fields.customer') }}</th>@endif
                    <th>{{ __('transactions.fields.amount') }}</th>
                    <th>{{ __('transactions.fields.reference_no') }}</th>
                    <th>{{ __('transactions.fields.bill') }}</th>
                    <th>{{ __('transactions.fields.project') }}</th>
                    <th>{{ __('transactions.fields.status') }}</th>
                    <th>{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($payments as $payment)
                    <tr @class(['table-warning' => $payment->isPending() && $user->isDeveloper()])>
                        <td class="text-nowrap">
                            {{ Dates::format($payment->paid_on) }} <span class="small text-muted ltr-input d-inline-block">{{ $payment->paid_time }}</span>
                            <div class="small text-muted">{{ __('transactions.registered_by', ['name' => $payment->creator?->name]) }}</div>
                        </td>
                        @if ($user->isStaff())<td class="text-nowrap small">{{ $payment->customer?->name }}</td>@endif
                        <td class="text-nowrap fw-semibold">{{ Money::format($payment->amount) }}</td>
                        <td class="text-nowrap small"><span class="ltr-input d-inline-block">{{ $payment->reference_no ?: '—' }}</span></td>
                        <td class="text-nowrap small">
                            @if ($payment->bill && ! $payment->bill->trashed())
                                <a href="{{ route('bills.show', $payment->bill) }}" class="t-number">#{{ $payment->bill->number }}</a>
                            @else — @endif
                        </td>
                        <td class="text-nowrap small">{{ $payment->project?->name ?? __('transactions.no_project') }}</td>
                        <td>
                            <span class="status-chip" style="--c: {{ Payment::statusColor($payment->status) }}">{{ __('transactions.statuses.'.$payment->status) }}</span>
                            @if ($payment->description)<div class="small text-muted text-truncate" style="max-width: 220px" title="{{ $payment->description }}">{{ $payment->description }}</div>@endif
                        </td>
                        <td class="text-nowrap">
                            @can('review', $payment)
                                @if ($payment->status !== Payment::ACCEPTED)
                                    <form method="POST" action="{{ route('payments.review', $payment) }}" class="d-inline">
                                        @csrf <input type="hidden" name="status" value="accepted">
                                        <button class="btn btn-sm btn-success" title="{{ __('transactions.accept') }}"><i class="bi bi-check-lg"></i> {{ __('transactions.accept') }}</button>
                                    </form>
                                @endif
                                @if ($payment->status !== Payment::DECLINED)
                                    <form method="POST" action="{{ route('payments.review', $payment) }}" class="d-inline" data-confirm="{{ __('transactions.confirm_decline') }}">
                                        @csrf <input type="hidden" name="status" value="declined">
                                        <button class="btn btn-sm btn-outline-danger" title="{{ __('transactions.decline') }}"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @endif
                            @endcan
                            @can('update', $payment)
                                <a href="{{ route('payments.edit', $payment) }}" class="btn btn-sm btn-light" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('delete', $payment)
                                <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="d-inline" data-confirm="{{ __('app.confirm_delete') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state"><i class="bi bi-inbox"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $payments->links() }}</div>
        @endif
    </div>
    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('transactions.vouchers_rule') }}</div>
@endsection
