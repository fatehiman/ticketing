@extends('layouts.app')
@section('title', __('bills.new'))

@php
    $err = fn ($key) => $errors->has($key) ? ' is-invalid' : '';
    $state = [
        'projects' => $projects,
        'project' => (string) old('project_id', $projectId),
        'customer' => (string) old('customer_id', $customerId),
        'tickets' => array_map('strval', (array) old('tickets', [])),
        'items' => array_values((array) old('items', [])),
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1><i class="bi bi-file-earmark-plus text-brand"></i> {{ __('bills.new') }}</h1>
            <div class="sub">{{ __('bills.new_hint') }}</div>
        </div>
        <a href="{{ route('bills.index') }}" data-return-link class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <script type="application/json" id="bill-data">@json($state)</script>

    <form method="POST" action="{{ route('bills.store') }}" data-bill-form data-ticket-url="{{ url('tickets') }}">
        @csrf
        <div class="card card-accent mb-3">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="project_id">{{ __('bills.fields.project') }}</label>
                    <select id="project_id" name="project_id" class="form-select{{ $err('project_id') }}" required data-bill-project>
                        <option value="">{{ __('bills.choose_project') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="customer_id">{{ __('bills.fields.customer') }}</label>
                    <select id="customer_id" name="customer_id" class="form-select{{ $err('customer_id') }}" required data-bill-customer>
                        <option value="">{{ __('bills.choose_customer') }}</option>
                    </select>
                    <div class="form-text d-none text-danger" data-no-customer>{{ __('bills.no_customer') }}</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="issued_on">{{ __('bills.fields.issued_on') }}</label>
                    <x-date-input id="issued_on" name="issued_on" :value="now()" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="due_on">{{ __('bills.fields.due_on') }} <span class="text-muted small">({{ __('app.optional') }})</span></label>
                    <x-date-input id="due_on" name="due_on" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="description">{{ __('bills.fields.description') }}</label>
                    <input type="text" id="description" name="description" value="{{ old('description') }}" maxlength="1000"
                           placeholder="{{ __('bills.description_hint') }}" class="form-control{{ $err('description') }}">
                </div>
            </div>
        </div>

        {{-- Done tickets with a cost that are not on another bill --}}
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-ticket-perforated text-brand"></i> {{ __('bills.tickets_title') }}
                <span class="small text-muted">— {{ __('bills.tickets_hint') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-grid mb-0">
                    <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" class="form-check-input" data-bill-check-all title="{{ __('app.all') }}"></th>
                        <th>{{ __('tickets.fields.number') }}</th>
                        <th>{{ __('tickets.fields.title') }}</th>
                        <th>{{ __('transactions.fields.date') }}</th>
                        <th>{{ __('bills.fields.amount') }}</th>
                    </tr>
                    </thead>
                    <tbody data-bill-tickets></tbody>
                </table>
            </div>
            <div class="card-body py-2 small text-muted d-none" data-bill-no-tickets>{{ __('bills.no_tickets') }}</div>
        </div>

        {{-- Manual items: saved as done tickets of the project --}}
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-pencil-square text-brand"></i> {{ __('bills.items_title') }}
                <span class="small text-muted">— {{ __('bills.items_hint') }}</span>
            </div>
            <div class="card-body">
                <div data-bill-items></div>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bill-add><i class="bi bi-plus-lg"></i> {{ __('bills.add_item') }}</button>
                @error('items')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
            <template data-bill-item-template>
                <div class="row g-2 align-items-start bill-item mb-2 pb-2 border-bottom">
                    <div class="col-md-5">
                        <input type="text" data-name="title" class="form-control" maxlength="250" placeholder="{{ __('bills.fields.item_title') }}">
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="text" data-name="amount" class="form-control ltr-input" inputmode="numeric" data-money="int" placeholder="{{ __('bills.fields.amount') }}">
                            <span class="input-group-text small" data-currency></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea data-name="details" rows="1" class="form-control" maxlength="2000" placeholder="{{ __('bills.fields.item_details') }}"></textarea>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-light btn-sm text-danger" data-bill-remove title="{{ __('app.remove') }}"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </template>
        </div>

        <div class="card card-accent">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="fs-5">{{ __('bills.fields.total') }}: <b class="text-brand" data-bill-total>0</b> <span class="small text-muted" data-currency></span></div>
                <div class="ms-md-auto d-flex flex-wrap align-items-center gap-3">
                    <span>{{ __('bills.notify_label') }}</span>
                    <label class="form-check mb-0">
                        <input type="checkbox" class="form-check-input" name="notify_sms" value="1" data-notify="mobile" @checked(old('notify_sms', true))>
                        <span class="form-check-label"><i class="bi bi-chat-dots"></i> {{ __('bills.by_sms') }}</span>
                    </label>
                    <label class="form-check mb-0">
                        <input type="checkbox" class="form-check-input" name="notify_email" value="1" data-notify="email" @checked(old('notify_email', true))>
                        <span class="form-check-label"><i class="bi bi-envelope"></i> {{ __('bills.by_email') }}</span>
                    </label>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('bills.issue') }}</button>
                <a href="{{ route('bills.index') }}" data-return-link class="btn btn-light">{{ __('app.cancel') }}</a>
            </div>
        </div>
    </form>
@endsection
