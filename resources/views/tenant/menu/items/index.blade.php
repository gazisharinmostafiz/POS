@extends(request()->routeIs('platform.*') ? 'platform.layout' : 'layouts.tenant-app', request()->routeIs('platform.*') ? [] : [
    'pageTitle' => 'Menu Items',
    'activeNav' => 'menu-items',
    'bodyClass' => 'pos-theme-light',
])

@if (request()->routeIs('platform.*'))
    @section('title', 'Menu Items')
@endif

@section('content')
<div class="pos-page">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Menu management',
        'title' => 'Menu items',
        'subtitle' => 'Products shown on the waiter POS with pricing and availability.',
        'showHome' => ! request()->routeIs('platform.*'),
        'actions' => view('partials.admin-header-action', [
            'href' => request()->routeIs('platform.*') ? route('platform.vendors.menu.items.create', $tenant) : route('tenant.menu.items.create'),
            'label' => 'New item',
        ])->render(),
    ])

    @include('partials.admin-flash')

    @if ($items->isEmpty())
        <section class="pos-empty">
            <p class="mb-4">No menu items yet. Add categories first, then create items.</p>
            <a href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.create', $tenant) : route('tenant.menu.items.create') }}" class="pos-btn-primary">Create item</a>
        </section>
    @else
        <div class="pos-table-wrap">
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $item->name }}</p>
                                @if ($item->description)
                                    <p class="text-xs text-slate-500 line-clamp-1">{{ $item->description }}</p>
                                @endif
                            </td>
                            <td>{{ $item->category->name }}</td>
                            <td class="font-semibold">{{ number_format((float) $item->price, 2) }}</td>
                            <td>
                                <span class="{{ $item->is_available ? 'pos-badge-success' : 'pos-badge-warning' }}">{{ $item->is_available ? 'Available' : 'Unavailable' }}</span>
                                <span class="ml-1 {{ $item->is_active ? 'pos-badge-neutral' : 'pos-badge-danger' }}">{{ $item->is_active ? 'Visible' : 'Hidden' }}</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.edit', $item) : route('tenant.menu.items.edit', $item) }}" class="pos-btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                    <form method="POST" data-loading-text="Saving…" action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.availability', $item) : route('tenant.menu.items.availability', $item) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="pos-btn-secondary px-3 py-1.5 text-xs">{{ $item->is_available ? 'Unavailable' : 'Available' }}</button>
                                    </form>
                                    <form method="POST" data-loading-text="Saving…" action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.visibility', $item) : route('tenant.menu.items.visibility', $item) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="pos-btn-secondary px-3 py-1.5 text-xs">{{ $item->is_active ? 'Hide' : 'Show' }}</button>
                                    </form>
                                    <form method="POST" data-confirm="Delete this menu item?" data-loading-text="Deleting…" action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.destroy', $item) : route('tenant.menu.items.destroy', $item) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="pos-btn-danger px-3 py-1.5 text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
