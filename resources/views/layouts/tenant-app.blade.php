<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'PosLAB' }} — {{ current_tenant()?->name ?? config('app.name') }}</title>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="{{ $bodyClass ?? 'pos-theme-light' }}">
    <div class="pos-app-shell">
        @include('partials.tenant-sidebar', ['activeNav' => $activeNav ?? null])

        <div class="pos-app-main">
            @yield('content')
        </div>

        @include('partials.tenant-mobile-nav', ['activeNav' => $activeNav ?? null])
    </div>

    @stack('after-body')
</body>
</html>
