<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Platform Owner') - Tong POS Platform</title>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen">
        <header class="border-b border-slate-800 bg-slate-900">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <a href="{{ route('platform.overview') }}" class="text-lg font-semibold">Tong POS Platform</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-slate-300 hover:text-white" type="submit">Log out</button>
                </form>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl gap-6 px-6 py-8 md:grid-cols-[220px_1fr]">
            <nav class="space-y-2 text-sm">
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.overview') }}">Overview</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.vendors.index') }}">Vendors</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.health') }}">System health</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.subscriptions') }}">Subscriptions</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.backups.index') }}">Backups</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.releases.index') }}">Release notes</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.maintenance.show') }}">Maintenance</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.migrations.index') }}">Migration</a>
                <a class="block rounded px-3 py-2 hover:bg-slate-900" href="{{ route('platform.support-access-logs') }}">Support access</a>
            </nav>

            <main>
                @if (session('status'))
                    <div class="mb-6 rounded border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
        <footer class="mx-auto max-w-7xl px-6 pb-6 text-xs text-slate-500">
            Version {{ config('app.version') }}
        </footer>
    </div>
</body>
</html>
