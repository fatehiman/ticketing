@extends('mail.layout')
@section('subject', __('mail.reset.subject'))

@section('content')
    @php($site = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url'))
    <p style="margin:0 0 12px;font-size:17px;font-weight:bold;color:#5b21b6">{{ __('mail.hello', ['name' => $user->name]) }}</p>
    <p style="margin:0 0 12px">{{ __('mail.reset.line1', ['site' => $site]) }}</p>
    <p style="margin:0 0 22px">{{ __('mail.reset.line2', ['minutes' => $minutes]) }}</p>
    <p style="margin:0 0 22px;text-align:center">
        <a href="{{ $url }}" style="display:inline-block;background:#7c3aed;color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 30px;border-radius:12px;font-size:16px">🔑 {{ __('mail.reset.button') }}</a>
    </p>
    <p style="margin:0 0 8px;font-size:13px;color:#6b7280">{{ __('mail.reset.copy') }}</p>
    <p style="margin:0 0 18px;font-size:12px;direction:ltr;text-align:left;word-break:break-all"><a href="{{ $url }}" style="color:#2563eb">{{ $url }}</a></p>
    <div style="background:#fef3c7;border-radius:10px;padding:10px 14px;font-size:13px;color:#92400e">⚠️ {{ __('mail.reset.ignore') }}</div>
@endsection
