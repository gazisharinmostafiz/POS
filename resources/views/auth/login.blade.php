<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — PosLAB</title>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white antialiased">
    <main class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
        <section class="pos-login-hero relative hidden flex-col justify-between p-10 lg:flex xl:p-16">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 text-lg font-black backdrop-blur">P</div>
                    <span class="text-lg font-bold tracking-tight">PosLAB</span>
                </div>
                <h1 class="mt-16 max-w-lg text-4xl font-bold leading-tight tracking-tight xl:text-5xl">
                    The modern restaurant operating system
                </h1>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-slate-300">
                    Run front-of-house, kitchen, billing, and analytics from one platform — built for speed, clarity, and scale.
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                    <p class="text-2xl font-bold text-accent-400">POS</p>
                    <p class="mt-2 text-sm text-slate-400">Tablet-ready ordering for waiters</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                    <p class="text-2xl font-bold text-brand-400">KDS</p>
                    <p class="mt-2 text-sm text-slate-400">Kitchen display with live tickets</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                    <p class="text-2xl font-bold text-emerald-400">Live</p>
                    <p class="mt-2 text-sm text-slate-400">Real-time sales & order sync</p>
                </div>
            </div>
        </section>

        <section class="flex items-center justify-center px-6 py-12 lg:px-12">
            <form method="POST" action="{{ route('login.store') }}" data-loading-text="Signing in…" class="pos-login-card">
                @csrf
                <div class="mb-8 lg:hidden">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-lg font-black">P</div>
                    <h2 class="mt-4 text-2xl font-bold">PosLAB</h2>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold">Welcome back</h2>
                    <p class="mt-2 text-sm text-slate-400">Sign in to your restaurant workspace.</p>
                </div>

                @if ($errors->any())
                    <div class="pos-alert-error mb-6">{{ $errors->first() }}</div>
                @endif

                <label class="pos-label text-slate-300" for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="pos-field-dark mt-2">

                <label class="pos-label mt-5 text-slate-300" for="password">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="pos-field-dark mt-2">

                <label class="mt-5 flex items-center gap-2 text-sm text-slate-400">
                    <input name="remember" type="checkbox" value="1" class="rounded border-slate-600 bg-slate-900 text-brand-500">
                    Keep me signed in
                </label>

                <button type="submit" class="pos-btn-primary pos-btn-touch mt-8 w-full">Sign in</button>

                <p class="mt-6 text-center text-xs text-slate-500">PosLAB v{{ config('app.version') }}</p>
            </form>
        </section>
    </main>
</body>
</html>
