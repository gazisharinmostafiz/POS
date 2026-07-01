<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu Items</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-6">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Menu management</p>
                <h1 class="text-3xl font-black">Menu items</h1>
            </div>
            <a class="pos-button bg-slate-950 text-white" href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.create', $tenant) : route('tenant.menu.items.create') }}">Create item</a>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded bg-emerald-100 px-4 py-3 font-bold text-emerald-900">{{ session('status') }}</div>
        @endif

        @if ($items->isEmpty())
            <section class="pos-empty">No menu items yet. Create an item after adding at least one category.</section>
        @else
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($items as $item)
                    <article class="pos-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-black">{{ $item->name }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $item->category->name }} · {{ number_format((float) $item->price, 2) }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $item->is_available ? 'bg-emerald-100 text-emerald-900' : 'bg-orange-100 text-orange-900' }}">{{ $item->is_available ? 'Available' : 'Unavailable' }}</span>
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $item->is_active ? 'bg-cyan-100 text-cyan-900' : 'bg-slate-200 text-slate-700' }}">{{ $item->is_active ? 'Visible' : 'Hidden' }}</span>
                            </div>
                        </div>
                        @if ($item->description)
                            <p class="mt-3 text-sm text-slate-600">{{ $item->description }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a class="pos-button bg-slate-100 text-slate-900" href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.edit', $item) : route('tenant.menu.items.edit', $item) }}">Edit</a>
                            <form method="POST" data-loading-text="Saving..." action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.availability', $item) : route('tenant.menu.items.availability', $item) }}">
                                @csrf
                                @method('PATCH')
                                <button class="pos-button bg-slate-100 text-slate-900" type="submit">{{ $item->is_available ? 'Mark unavailable' : 'Mark available' }}</button>
                            </form>
                            <form method="POST" data-loading-text="Saving..." action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.visibility', $item) : route('tenant.menu.items.visibility', $item) }}">
                                @csrf
                                @method('PATCH')
                                <button class="pos-button bg-slate-100 text-slate-900" type="submit">{{ $item->is_active ? 'Hide' : 'Unhide' }}</button>
                            </form>
                            <form method="POST" data-confirm="Delete this menu item? Existing order snapshots are not changed." data-loading-text="Deleting..." action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.destroy', $item) : route('tenant.menu.items.destroy', $item) }}">
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
