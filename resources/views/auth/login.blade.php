<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Tong POS Platform</title>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white antialiased">
    <main class="grid min-h-screen lg:grid-cols-[1fr_480px]">
        <section class="flex items-center px-6 py-10 sm:px-10 lg:px-16">
            <div class="max-w-2xl">
                <p class="text-sm font-bold uppercase tracking-[0.3em] text-cyan-300">PosLAB</p>
                <h1 class="mt-5 text-4xl font-black tracking-tight sm:text-5xl">Secure restaurant operations platform</h1>
                <p class="mt-5 max-w-xl text-lg leading-8 text-slate-300">
                    Sign in to manage orders, kitchen flow, billing, settings, reporting, backups, and platform operations.
                </p>
                <div class="mt-8 grid gap-3 text-sm text-slate-300 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-800 bg-slate-900 p-4">Tenant scoped</div>
                    <div class="rounded-lg border border-slate-800 bg-slate-900 p-4">Role protected</div>
                    <div class="rounded-lg border border-slate-800 bg-slate-900 p-4">Realtime ready</div>
                </div>
            </div>
        </section>

        <section class="flex items-center justify-center border-t border-slate-800 bg-slate-900 px-6 py-10 lg:border-l lg:border-t-0">
            <form method="POST" action="{{ route('login.store') }}" data-loading-text="Signing in..." class="w-full max-w-sm rounded-lg border border-slate-800 bg-slate-950 p-6 shadow-2xl">
                @csrf
                <div class="mb-6">
                    <h2 class="text-2xl font-black">Sign in</h2>
                    <p class="mt-1 text-sm text-slate-400">Use your PosLAB account credentials.</p>
                </div>

                @if ($errors->any())
                    <div class="mb-4 rounded border border-red-800 bg-red-950 px-4 py-3 text-sm font-bold text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <label class="block text-sm font-bold" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="pos-field-dark mt-2">

                <label class="mt-4 block text-sm font-bold" for="password">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="pos-field-dark mt-2">

                <label class="mt-4 flex items-center gap-2 text-sm text-slate-300">
                    <input name="remember" type="checkbox" value="1" class="rounded border-slate-700 bg-slate-950">
                    Keep me signed in on this device
                </label>

                <button type="submit" class="pos-button mt-6 w-full bg-cyan-400 text-slate-950">Log in</button>
                <p class="mt-4 text-center text-xs text-slate-500">Version {{ config('app.version') }}</p>
            </form>
        </section>
    </main>
</body>
</html>
