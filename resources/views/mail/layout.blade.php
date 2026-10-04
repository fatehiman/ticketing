{{-- Colourful HTML mail layout. Mail clients ignore <style> blocks often, so styles are inline. --}}
@php
    $rtl = app()->getLocale() === 'fa';
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $font = $rtl ? "Tahoma, 'Vazirmatn', Arial, sans-serif" : "'Segoe UI', Arial, sans-serif";
    $site = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('subject')</title>
</head>
<body style="margin:0;padding:0;background:#f3f0ff;font-family:{{ $font }};direction:{{ $dir }};text-align:{{ $align }};color:#1f2937">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#ede9fe 0%,#e0f2fe 50%,#fce7f3 100%);padding:28px 12px">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 30px rgba(91,33,182,.15)">
                <tr>
                    <td style="background:linear-gradient(135deg,#7c3aed 0%,#2563eb 55%,#0d9488 100%);background-color:#6d28d9;padding:26px 28px;color:#ffffff;text-align:{{ $align }}">
                        <div style="font-size:24px;font-weight:bold">🎫 {{ __('app.name') }}</div>
                        <div style="font-size:14px;opacity:.9;margin-top:4px">{{ __('app.tagline') }} · {{ $site }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;font-size:15px;line-height:1.9;text-align:{{ $align }}">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 28px;font-size:12px;color:#6b7280;text-align:{{ $align }}">
                        {{ __('mail.footer', ['site' => $site]) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
