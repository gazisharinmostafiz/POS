<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tong POS Platform</title>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white antialiased">
    <main id="app" class="flex min-h-screen items-center justify-center px-6">
        <section class="text-center">
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-cyan-300">Base application stack</p>
            <h1 class="text-4xl font-bold tracking-tight sm:text-6xl">Tong POS Platform</h1>
            <p class="mx-auto mt-6 max-w-xl text-base leading-7 text-slate-300">
                Laravel, Vue 3, Tailwind CSS, PostgreSQL, Redis, and Docker are ready for platform development.
            </p>
        </section>
    </main>
</body>
</html>
