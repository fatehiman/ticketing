@extends('mail.layout')
@section('subject', __('mail.bill.subject', ['number' => $bill->number, 'project' => $bill->project->name]))

@php
    use App\Support\Money;
    $rtl = app()->getLocale() === 'fa';
    $align = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    $issuer = $bill->creator;
@endphp

@section('content')
    <p style="margin:0 0 12px;font-size:17px;font-weight:bold;color:#5b21b6">{{ __('mail.hello', ['name' => $bill->customer->name]) }}</p>
    <p style="margin:0 0 18px">{{ __('mail.bill.line1', ['project' => $bill->project->name]) }}</p>

    {{-- Bill summary chips --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px">
        <tr>
            <td style="background:#ede9fe;border-radius:12px;padding:12px 14px;text-align:center;width:33%">
                <div style="font-size:12px;color:#6d28d9">{{ __('bills.fields.number') }}</div>
                <div style="font-size:18px;font-weight:bold;color:#4c1d95">#{{ $bill->number }}</div>
            </td>
            <td style="width:8px"></td>
            <td style="background:#e0f2fe;border-radius:12px;padding:12px 14px;text-align:center;width:33%">
                <div style="font-size:12px;color:#0369a1">{{ __('bills.fields.issued_on') }}</div>
                <div style="font-size:16px;font-weight:bold;color:#075985;direction:ltr">{{ $issuedOn }}</div>
            </td>
            <td style="width:8px"></td>
            <td style="background:#dcfce7;border-radius:12px;padding:12px 14px;text-align:center;width:33%">
                <div style="font-size:12px;color:#15803d">{{ __('bills.fields.total') }}</div>
                <div style="font-size:16px;font-weight:bold;color:#14532d">{{ Money::format($bill->total) }} <span style="font-size:12px">{{ $currency }}</span></div>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0;border:1px solid #e9d5ff;border-radius:12px;overflow:hidden;margin:0 0 18px;font-size:14px">
        <tr style="background:#7c3aed;color:#ffffff">
            <th style="padding:10px 12px;text-align:{{ $align }}">{{ __('bills.fields.item_title') }}</th>
            <th style="padding:10px 12px;text-align:{{ $end }};white-space:nowrap">{{ __('bills.fields.amount') }}</th>
        </tr>
        @foreach ($bill->items as $i => $item)
            <tr style="background:{{ $i % 2 ? '#faf5ff' : '#ffffff' }}">
                <td style="padding:9px 12px;border-top:1px solid #f3e8ff;text-align:{{ $align }}">
                    {{ $item->title }}
                    @if ($item->details)<div style="font-size:12px;color:#6b7280">{{ $item->details }}</div>@endif
                </td>
                <td style="padding:9px 12px;border-top:1px solid #f3e8ff;text-align:{{ $end }};white-space:nowrap;font-weight:bold">{{ $item->amount ? Money::format($item->amount) : '—' }}</td>
            </tr>
        @endforeach
        <tr style="background:#f5f3ff">
            <td style="padding:11px 12px;border-top:2px solid #c4b5fd;font-weight:bold;text-align:{{ $align }}">{{ __('bills.fields.total') }}</td>
            <td style="padding:11px 12px;border-top:2px solid #c4b5fd;font-weight:bold;color:#5b21b6;text-align:{{ $end }};white-space:nowrap">{{ Money::format($bill->total) }} {{ $currency }}</td>
        </tr>
    </table>

    @if ($dueOn)
        <p style="margin:0 0 10px">⏰ {{ __('bills.fields.due_on') }}: <b style="direction:ltr;display:inline-block">{{ $dueOn }}</b></p>
    @endif
    @if ($bill->description)
        <p style="margin:0 0 14px;color:#374151">{{ $bill->description }}</p>
    @endif
    @if ($debt > 0)
        <div style="background:#fff1f2;border-radius:12px;padding:12px 14px;margin:0 0 18px;color:#9f1239">
            💳 {{ __('mail.bill.debt') }}: <b>{{ Money::format($debt) }} {{ $currency }}</b>
        </div>
    @endif

    {{-- How to pay --}}
    @if ($issuer?->hasBankInfo())
        <div style="background:linear-gradient(135deg,#ecfeff,#f0fdf4);background-color:#ecfeff;border:1px solid #a5f3fc;border-radius:12px;padding:14px 16px;margin:0 0 20px">
            <div style="font-weight:bold;color:#0e7490;margin-bottom:6px">🏦 {{ __('bills.how_to_pay') }}</div>
            @if ($issuer->card_number)<div>{{ __('users.fields.card_number') }}: <b style="direction:ltr;display:inline-block">{{ $issuer->card_number }}</b></div>@endif
            @if ($issuer->iban)<div>{{ __('users.fields.iban') }}: <b style="direction:ltr;display:inline-block">{{ $issuer->iban }}</b></div>@endif
            @if ($issuer->account_holder)<div>{{ __('users.fields.account_holder') }}: <b>{{ $issuer->account_holder }}</b></div>@endif
            <div style="font-size:13px;color:#475569;margin-top:6px">{{ __('mail.bill.after_pay') }}</div>
        </div>
    @endif

    <p style="margin:0;text-align:center">
        <a href="{{ $url }}" style="display:inline-block;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 30px;border-radius:12px;font-size:16px">🧾 {{ __('mail.bill.button') }}</a>
    </p>
@endsection
