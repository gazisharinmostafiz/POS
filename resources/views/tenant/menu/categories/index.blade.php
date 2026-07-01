<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu Categories</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-6">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Menu management</p>
                <h1 class="text-3xl font-black">Categories</h1>
            </div>
            <a class="pos-button bg-slate-950 text-white" href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.create', $tenant) : route('tenant.menu.categories.create') }}">Create category</a>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded bg-emerald-100 px-4 py-3 font-bold text-emerald-900">{{ session('status') }}</div>
        @endif

        @if ($categories->isEmpty())
            <section class="pos-empty">No categories yet. Create the first category before adding menu items.</section>
        @else
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($categories as $category)
                    <article class="pos-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-black">{{ $category->name }}</h2>
                                <p class="mt-1 text-sm text-slate-500">Sort order {{ $category->sort_order }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-sm font-bold {{ $category->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-700' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span>
                        </div>
                        @if ($category->description)
                            <p class="mt-3 text-sm text-slate-600">{{ $category->description }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a class="pos-button bg-slate-100 text-slate-900" href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.edit', $category) : route('tenant.menu.categories.edit', $category) }}">Edit</a>
                            <form method="POST" data-confirm="Delete this category? This cannot be undone." data-loading-text="Deleting..." action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.destroy', $category) : route('tenant.menu.categories.destroy', $category) }}">
                                @csrf
                                @method('DELETE')
                                <button class="pos-button bg-red-100 text-red-800" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </main>
</body>
</html>
