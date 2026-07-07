@extends(request()->routeIs('platform.*') ? 'platform.layout' : 'layouts.tenant-app', request()->routeIs('platform.*') ? [] : [
    'pageTitle' => 'Menu Categories',
    'activeNav' => 'menu-categories',
    'bodyClass' => 'pos-theme-light',
])

@if (request()->routeIs('platform.*'))
    @section('title', 'Menu Categories')
@endif

@section('content')
<div class="pos-page">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Menu management',
        'title' => 'Categories',
        'subtitle' => 'Organize your menu into sections for the waiter POS.',
        'showHome' => ! request()->routeIs('platform.*'),
        'actions' => view('partials.admin-header-action', [
            'href' => request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.create', $tenant) : route('tenant.menu.categories.create'),
            'label' => 'New category',
        ])->render(),
    ])

    @include('partials.admin-flash')

    @if ($categories->isEmpty())
        <section class="pos-empty">
            <p class="mb-4">No categories yet. Create your first category before adding menu items.</p>
            <a href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.create', $tenant) : route('tenant.menu.categories.create') }}" class="pos-btn-primary">Create category</a>
        </section>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($categories as $category)
                <article class="pos-card p-5 transition hover:shadow-card-md">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ $category->name }}</h2>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Sort {{ $category->sort_order }}</p>
                        </div>
                        <span class="{{ $category->is_active ? 'pos-badge-success' : 'pos-badge-neutral' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span>
                    </div>
                    @if ($category->description)
                        <p class="mt-3 text-sm text-slate-600 line-clamp-2">{{ $category->description }}</p>
                    @endif
                    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        <a href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.edit', $category) : route('tenant.menu.categories.edit', $category) }}" class="pos-btn-secondary text-xs">Edit</a>
                        <form method="POST" data-confirm="Delete this category?" data-loading-text="Deleting…" action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.destroy', $category) : route('tenant.menu.categories.destroy', $category) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="pos-btn-danger text-xs">Delete</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
