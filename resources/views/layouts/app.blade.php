@php($dir = app()->getLocale() === 'fa' ? 'rtl' : 'ltr')
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ __('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>🎫</text></svg>">
    @stack('head')
    @vite(["resources/css/theme-{$dir}.css", 'resources/js/app.js'])
    <script>window.APP_I18N = @json(['menu_name_prompt' => __('tickets.filter.menu_name_prompt')]);</script>
</head>
{{-- data-page / data-list-referrer: see App\Support\ReturnTo (back to the list page after saving a form) --}}
<body data-page="{{ \App\Support\ReturnTo::kind(Route::currentRouteName()) }}" data-list-referrer="{{ \App\Support\ReturnTo::listReferrer(request()) }}">
    @include('partials.sidebar')
    <div class="sidebar-backdrop"></div>

    <div class="main">
        @include('partials.topbar')
        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>

    @include('partials.menu-modal')
    @stack('scripts')
</body>
</html>
