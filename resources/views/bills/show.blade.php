@extends('layouts.app')
@section('title', __('bills.bill').' #'.$bill->number)

@php
    use App\Models\Payment;
    use App\Support\Bills;
    use App\Support\Dates;
    use App\Support\Money;
    $user = auth()->user();
    $currency = __('app.currency.'.($bill->project->currency ?: 'IRT'));
    $issuer = $bill->creator;
@endphp

@section('content')
    <div class="page-head d-print-none">
        <div>
            <h1><i class="bi bi-receipt text-brand"></i> {{ __('bills.bill') }} #{{ $bill->number }}</h1>
            <div class="sub">{{ $bill->project->name }} · {{ $bill->customer->name }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($user->isCustomer() && $bill->status !== 'paid')
                <a href="{{ route('payments.create', ['bill' => $bill->number]) }}" class="btn btn-success"><i class="bi bi-wallet2"></i> {{ __('transactions.new_voucher') }}</a>
            @endif
            @if ($user->isDeveloper())
                <a href="{{ route('payments.create', ['bill' => $bill->number]) }}" class="btn btn-outline-success"><i class="bi bi-plus-lg"></i> {{ __('transactions.new_payment') }}</a>
            @endif
            <button type="button" class="btn btn-light" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('bills.print') }}</button>
            <a href="{{ route('bills.index') }}" data-return-link class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="card card-accent mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="small text-muted">{{ __('bills.fields.number') }}</div>
                    <div class="fs-5 fw-bold">#{{ $bill->number }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">{{ __('bills.fields.issued_on') }}</div>
                    <div class="fw-semibold">{{ Dates::format($bill->issued_on) }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">{{ __('bills.fields.due_on') }}</div>
                    <div class="fw-semibold">{{ $bill->due_on ? Dates::format($bill->due_on) : '—' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">{{ __('bills.fields.status') }}</div>
                    <span class="status-chip" style="--c: {{ Bills::color($bill->status) }}">{{ __('bills.statuses.'.$bill->status) }}</span>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">{{ __('bills.fields.customer') }}</div>
                    <div>{{ $bill->customer->name }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">{{ __('bills.fields.project') }}</div>
                    <div>{{ $bill->project->name }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">{{ __('bills.issued_by') }}</div>
                    <div>{{ $issuer?->name }}</div>
                </div>
                @if ($bill->description)
                    <div class="col-12">
                        <div class="small text-muted">{{ __('bills.fields.description') }}</div>
                        <div>{{ $bill->description }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-grid mb-0">
                <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>{{ __('bills.fields.item_title') }}</th>
                    <th>{{ __('tickets.fields.number') }}</th>
                    <th class="text-end">{{ __('bills.fields.amount') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($bill->items as $i => $item)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            {{ $item->title }}
                            @if ($item->details)<div class="small text-muted" style="white-space: pre-line">{{ $item->details }}</div>@endif
                        </td>
                        <td class="text-nowrap">
                            @if ($item->ticket && ! $item->ticket->trashed())
                                <a href="{{ route('tickets.show', $item->ticket) }}" class="t-number">#{{ $item->ticket->number }}</a>
                            @endif
                        </td>
                        <td class="text-end text-nowrap fw-semibold">{{ Money::format($item->amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="3">{{ __('bills.fields.total') }}</td>
                    <td class="text-end text-nowrap">{{ Money::format($bill->total, $bill->project->currency ?: 'IRT') }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="text-success">{{ __('bills.fields.paid') }}</td>
                    <td class="text-end text-nowrap text-success">{{ Money::format($bill->paid) }}</td>
                </tr>
                @if ($bill->total > $bill->paid)
                    <tr>
                        <td colspan="3" class="text-danger">{{ __('bills.fields.unpaid') }}</td>
                        <td class="text-end text-nowrap text-danger">{{ Money::format($bill->total - $bill->paid) }}</td>
                    </tr>
                @endif
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row g-3">
        @if ($issuer?->hasBankInfo())
            <div class="col-md-6">
                <div class="card h-100 bank-card">
                    <div class="card-header"><i class="bi bi-bank text-brand"></i> {{ __('bills.how_to_pay') }}</div>
                    <div class="card-body">
                        @include('partials.bank-info', ['bank' => $issuer])
                        @if ($debt > 0)
                            <div class="mt-2">{{ __('bills.debt_now') }}: <b class="text-danger">{{ Money::format($debt) }} {{ $currency }}</b></div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-wallet2 text-brand"></i> {{ __('bills.payments_of_bill') }}</div>
                <div class="card-body">
                    @forelse ($bill->payments as $payment)
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="status-chip" style="--c: {{ Payment::statusColor($payment->status) }}">{{ __('transactions.statuses.'.$payment->status) }}</span>
                            <span class="fw-semibold">{{ Money::format($payment->amount) }}</span>
                            <span class="small text-muted">{{ Dates::format($payment->paid_on) }} {{ $payment->paid_time }}</span>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('bills.no_payments') }}</div>
                    @endforelse
                    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('bills.paid_rule') }}</div>
                </div>
            </div>
        </div>
    </div>

    @can('delete', $bill)
        <form method="POST" action="{{ route('bills.destroy', $bill) }}" class="mt-3 d-print-none" data-confirm="{{ __('bills.confirm_delete') }}">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> {{ __('bills.delete') }}</button>
        </form>
    @endcan
@endsection
