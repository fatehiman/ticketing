@extends('layouts.app')
@section('title', __('bills.title'))

@php
    use App\Support\Bills;
    use App\Support\Dates;
    use App\Support\Money;
    $user = auth()->user();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-receipt-cutoff text-brand"></i> {{ __('bills.title') }}</h1>
            <div class="sub">{{ $user->isCustomer() ? __('bills.subtitle_customer') : __('bills.subtitle_staff') }} · {{ __('app.results', ['count' => $rows->total()]) }}</div>
        </div>
        <div class="d-flex gap-2">
            @can('create', App\Models\Bill::class)
                <a href="{{ route('bills.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('bills.new') }}</a>
            @endcan
            @if ($user->isCustomer())
                <a href="{{ route('payments.create') }}" class="btn btn-success"><i class="bi bi-wallet2"></i> {{ __('transactions.new_voucher') }}</a>
            @endif
        </div>
    </div>

    @if ($debt !== null)
        <div @class(['alert border-0 shadow-sm d-flex align-items-center gap-2', 'alert-danger' => $debt > 0, 'alert-success' => $debt <= 0])>
            <i class="bi bi-bank fs-5"></i>
            <div>
                @if ($debt > 0)
                    {{ __('bills.debt_now') }}: <b>{{ Money::format($debt) }}</b>
                @else
                    {{ __('bills.no_debt') }}
                @endif
            </div>
        </div>
    @endif

    <form method="GET" action="{{ route('bills.index') }}" class="card filter-card mb-3" data-clean-submit>
        <div class="card-body pb-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">{{ __('bills.fields.status') }}</label>
                    <select name="status" class="form-select" data-autosubmit>
                        <option value="">{{ __('app.all') }}</option>
                        @foreach (Bills::STATUSES as $s)
                            <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ __('bills.statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('bills.fields.project') }}</label>
                    <select name="project_id" class="form-select" data-autosubmit>
                        <option value="">{{ __('app.all_projects') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected($filters['project_id'] === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($user->isStaff())
                    <div class="col-md-4">
                        <label class="form-label">{{ __('bills.fields.customer') }}</label>
                        <select name="customer_id" class="form-select" data-autosubmit>
                            <option value="">{{ __('transactions.all_customers') }}</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected($filters['customer_id'] === $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-1">
                    <a href="{{ route('bills.index') }}" class="btn btn-light w-100" title="{{ __('app.reset') }}"><i class="bi bi-x-circle"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-grid mb-0">
                <thead>
                <tr>
                    <th>{{ __('bills.fields.number') }}</th>
                    <th>{{ __('bills.fields.issued_on') }}</th>
                    @if ($user->isStaff())<th>{{ __('bills.fields.customer') }}</th>@endif
                    <th>{{ __('bills.fields.project') }}</th>
                    <th>{{ __('bills.fields.total') }}</th>
                    <th>{{ __('bills.fields.paid') }}</th>
                    <th>{{ __('bills.fields.status') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $bill)
                    <tr>
                        <td><a href="{{ route('bills.show', $bill) }}" class="t-number">#{{ $bill->number }}</a></td>
                        <td class="text-nowrap">
                            {{ Dates::format($bill->issued_on) }}
                            @if ($bill->due_on && $bill->status !== 'paid')
                                <div @class(['small', 'text-danger' => $bill->due_on->isPast(), 'text-muted' => ! $bill->due_on->isPast()])>{{ __('bills.fields.due_on') }}: {{ Dates::format($bill->due_on) }}</div>
                            @endif
                        </td>
                        @if ($user->isStaff())<td class="text-nowrap small">{{ $bill->customer?->name }}</td>@endif
                        <td class="text-nowrap small">{{ $bill->project?->name }}</td>
                        <td class="text-nowrap fw-semibold">{{ Money::format($bill->total) }}</td>
                        <td class="text-nowrap text-success">{{ Money::format($bill->paid) }}</td>
                        <td><span class="status-chip" style="--c: {{ Bills::color($bill->status) }}">{{ __('bills.statuses.'.$bill->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
                @if ($rows->total())
                    <tfoot>
                    <tr>
                        <td colspan="{{ $user->isStaff() ? 4 : 3 }}"><i class="bi bi-calculator"></i> {{ __('app.grand_total') }}</td>
                        <td>{{ Money::format($totals['total']) }}</td>
                        <td>{{ Money::format($totals['paid']) }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $rows->links() }}</div>
        @endif
    </div>
    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('bills.paid_rule') }}</div>
@endsection
