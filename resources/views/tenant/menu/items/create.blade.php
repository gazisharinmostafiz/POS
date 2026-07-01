<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Menu Item</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-6">
        <header class="mb-6">
            <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Menu management</p>
            <h1 class="text-3xl font-black">Create menu item</h1>
        </header>
        <form method="POST" enctype="multipart/form-data" data-loading-text="Saving..." class="pos-card p-5" action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.store', $tenant) : route('tenant.menu.items.store') }}">
            @include('tenant.menu.items.form')
        </form>
    </main>
</body>
</html>
