@extends('layouts.app')
@section('title', __('transactions.title'))

@php
    use App\Support\Dates;
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $user = auth()->user();
    $f = fn ($key, $default = null) => $filters[$key] ?? $default;
    $checked = fn ($key, $value) => in_array((string) $value, $filters[$key] ?? [], true);
    $remainingClass = fn ($v) => $v > 0 ? 'text-danger' : ($v < 0 ? 'text-success' : '');
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-cash-coin text-brand"></i> {{ __('transactions.title') }}</h1>
            <div class="sub">{{ $user->isStaff() ? __('transactions.subtitle_staff') : __('transactions.subtitle_customer') }} · {{ __('app.results', ['count' => $rows->total()]) }}</div>
        </div>
        @can('create', App\Models\Payment::class)
            <a href="{{ route('payments.create', array_filter(['customer_id' => $f('customer_id')])) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('transactions.new_payment') }}</a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('transactions.index') }}" class="card filter-card mb-3" data-clean-submit>
        <div class="card-body pb-2">
            <div class="row g-2 align-items-end">
                @if ($user->isStaff())
                    <div class="col-md-4">
                        <label class="form-label">{{ __('transactions.fields.customer') }}</label>
                        <select name="customer_id" class="form-select">
                            <option value="">{{ __('transactions.all_customers') }}</option>
                            @foreach ($customerOptions as $customer)
                                <option value="{{ $customer->id }}" @selected((string) $f('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="{{ $user->isStaff() ? 'col-md-4' : 'col-md-8' }}">
                    <label class="form-label">{{ __('transactions.fields.description') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" value="{{ $f('q') }}" class="form-control" placeholder="{{ __('transactions.filter.keyword') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">{{ __('transactions.fields.kind') }}</label>
                    <label class="chip-check" style="--c: #16a34a">
                        <input type="checkbox" name="kind[]" value="payment" @checked($checked('kind', 'payment'))>
                        <span><i class="bi bi-arrow-down-circle"></i>{{ __('transactions.kinds.payment') }}</span>
                    </label>
                    <label class="chip-check" style="--c: #e11d48">
                        <input type="checkbox" name="kind[]" value="cost" @checked($checked('kind', 'cost'))>
                        <span><i class="bi bi-receipt"></i>{{ __('transactions.kinds.cost') }}</span>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('transactions.filter.date') }}</label>
                    <div class="input-group">
                        <span class="input-group-text small">{{ __('app.from') }}</span>
                        <x-date-input name="date_from" :value="$f('date_from')" />
                        <span class="input-group-text small">{{ __('app.to') }}</span>
                        <x-date-input name="date_to" :value="$f('date_to')" />
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('transactions.filter.amount') }}</label>
                    <div class="input-group">
                        <span class="input-group-text small">{{ __('app.from') }}</span>
                        <input type="text" name="amount_from" value="{{ $f('amount_from') }}" class="form-control ltr-input" inputmode="numeric" data-money="int">
                        <span class="input-group-text small">{{ __('app.to') }}</span>
                        <input type="text" name="amount_to" value="{{ $f('amount_to') }}" class="form-control ltr-input" inputmode="numeric" data-money="int">
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label d-block">{{ __('transactions.fields.project') }}</label>
                    @foreach ($projectOptions as $project)
                        <label class="chip-check">
                            <input type="checkbox" name="project[]" value="{{ $project->id }}" @checked($checked('project', $project->id))>
                            <span><i class="bi bi-kanban"></i>{{ $project->name }}</span>
                        </label>
                    @endforeach
                    <label class="chip-check" style="--c: #64748b">
                        <input type="checkbox" name="project[]" value="none" @checked($checked('project', 'none'))>
                        <span><i class="bi bi-dash-circle"></i>{{ __('transactions.no_project') }}</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap align-items-center gap-2">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-funnel"></i> {{ __('app.search') }}</button>
            <a href="{{ route('transactions.index') }}" class="btn btn-light"><i class="bi bi-x-circle"></i> {{ __('app.reset') }}</a>
            <span class="small text-muted"><i class="bi bi-info-circle"></i> {{ __('transactions.cost_rule') }}</span>
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="dir" value="{{ request('dir') }}">
            <div class="ms-auto d-flex align-items-center gap-2">
                <label class="small text-muted text-nowrap" for="per_page">{{ __('app.per_page') }}</label>
                <select name="per_page" id="per_page" class="form-select form-select-sm" data-autosubmit style="width:auto">
                    @foreach ([10, 25, 50, 100] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- Ledger --}}
    <div class="card">
        <div class="grid-toolbar">
            <span class="fw-semibold">{{ __('app.results', ['count' => $rows->total()]) }}</span>
            <div class="ms-auto"><x-grid-columns :grid="$grid" /></div>
        </div>
        <div class="table-responsive">
            <table class="table table-grid" data-grid="transactions">
                <thead>
                <tr>
                    @php($sortable = ['date' => 'tx_date', 'payment' => 'amount', 'cost' => 'amount'])
                    @foreach ($grid->columns as $key => $label)
                        <th data-col="{{ $key }}" class="{{ $grid->cls($key) }}">
                            @isset($sortable[$key])
                                <x-sort-link :column="$sortable[$key]" :label="$label" :sort="$sort" :dir="$dir" />
                            @else
                                {{ $label }}
                            @endisset
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php($isPayment = $row->kind === 'payment')
                    @php($project = $row->project_id ? $rowProjects->get($row->project_id) : null)
                    <tr>
                        <td data-col="date" class="{{ $grid->cls('date') }} text-nowrap">{{ Dates::format(Carbon::parse($row->tx_date)) }}</td>
                        <td data-col="kind" class="{{ $grid->cls('kind') }}">
                            @if ($isPayment)
                                <span class="status-chip" style="--c: #16a34a"><i class="bi bi-arrow-down-circle"></i>{{ __('transactions.kinds.payment') }}</span>
                            @else
                                <span class="status-chip" style="--c: #e11d48"><i class="bi bi-receipt"></i>{{ __('transactions.kinds.cost') }}</span>
                            @endif
                        </td>
                        @isset($grid->columns['customer'])
                            <td data-col="customer" class="{{ $grid->cls('customer') }} text-nowrap small">
                                {{ $isPayment ? $customers->get($row->customer_id)?->name : '' }}
                            </td>
                        @endisset
                        <td data-col="project" class="{{ $grid->cls('project') }} text-nowrap small">
                            @if ($project){{ $project->name }}@else<span class="text-muted">{{ __('transactions.no_project') }}</span>@endif
                        </td>
                        <td data-col="description" class="{{ $grid->cls('description') }}" style="min-width: 220px">
                            @if ($isPayment)
                                {{ $row->description }}
                            @else
                                <a href="{{ route('tickets.show', $row->ticket_number) }}" class="t-number">#{{ $row->ticket_number }}</a>
                                <a href="{{ route('tickets.show', $row->ticket_number) }}" class="t-title">{{ $row->description }}</a>
                            @endif
                        </td>
                        <td data-col="payment" class="{{ $grid->cls('payment') }} text-nowrap text-success fw-semibold">{{ $isPayment ? Money::format($row->amount) : '' }}</td>
                        <td data-col="cost" class="{{ $grid->cls('cost') }} text-nowrap text-danger fw-semibold">{{ $isPayment ? '' : Money::format($row->amount) }}</td>
                        @isset($grid->columns['actions'])
                            <td data-col="actions" class="{{ $grid->cls('actions') }} text-nowrap">
                                @if ($isPayment && $editable->has($row->id))
                                    <a href="{{ route('payments.edit', $row->id) }}" class="btn btn-sm btn-light" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                @endif
                            </td>
                        @endisset
                    </tr>
                @empty
                    <tr><td colspan="{{ count($grid->columns) }}" class="empty-state"><i class="bi bi-inbox"></i>{{ __('app.no_results') }}</td></tr>
                @endforelse
                </tbody>
                @include('partials.grid-totals', ['grid' => $grid])
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-transparent pt-3">{{ $rows->links() }}</div>
        @endif
    </div>

    {{-- Totals of all filtered records (not only this page) --}}
    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <div class="stat-card grad-3">
                <div class="stat-value">{{ Money::format($totals['payments']) }}</div>
                <div class="stat-label">{{ __('transactions.total_payments') }}</div><i class="bi bi-arrow-down-circle"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card grad-5">
                <div class="stat-value">{{ Money::format($totals['costs']) }}</div>
                <div class="stat-label">{{ __('transactions.total_costs') }}</div><i class="bi bi-receipt"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card grad-6">
                <div class="stat-value"><span class="num">{{ Money::format($totals['remaining']) }}</span></div>
                <div class="stat-label">{{ __('transactions.remaining') }}</div><i class="bi bi-bank"></i>
            </div>
        </div>
        <div class="col-12 small text-muted"><i class="bi bi-info-circle"></i> {{ __('transactions.remaining_hint') }}</div>
    </div>

    {{-- Summary per project, when the result covers more than one project --}}
    @if ($byProject)
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-diagram-3 text-brand"></i> {{ __('transactions.by_project') }}</div>
            <div class="table-responsive">
                <table class="table table-grid">
                    <thead>
                    <tr>
                        <th>{{ __('transactions.fields.project') }}</th>
                        <th>{{ __('transactions.total_payments') }}</th>
                        <th>{{ __('transactions.total_costs') }}</th>
                        <th>{{ __('transactions.remaining') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($byProject as $line)
                        <tr>
                            <td class="fw-semibold">
                                @if ($line['project']){{ $line['project']->name }} <span class="text-muted small">{{ __('app.currency.'.$line['project']->currency) }}</span>
                                @else<span class="text-muted">{{ __('transactions.no_project') }}</span>@endif
                            </td>
                            <td class="text-success">{{ Money::format($line['payments']) }}</td>
                            <td class="text-danger">{{ Money::format($line['costs']) }}</td>
                            <td class="fw-semibold {{ $remainingClass($line['remaining']) }}"><span class="num">{{ Money::format($line['remaining']) }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <td><i class="bi bi-calculator"></i> {{ __('app.grand_total') }}</td>
                        <td>{{ Money::format($totals['payments']) }}</td>
                        <td>{{ Money::format($totals['costs']) }}</td>
                        <td><span class="num">{{ Money::format($totals['remaining']) }}</span></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
@endsection
