<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white antialiased">
    <main class="flex min-h-screen items-center justify-center px-6 text-center">
        <section>
            <p class="text-sm font-semibold uppercase tracking-widest text-red-300">403</p>
            <h1 class="mt-3 text-4xl font-bold">Access denied</h1>
            <p class="mt-4 text-slate-300">You do not have permission to access this page.</p>
        </section>
    </main>
</body>
</html>
