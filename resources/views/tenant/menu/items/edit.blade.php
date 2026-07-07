@extends(request()->routeIs('platform.*') ? 'platform.layout' : 'layouts.tenant-app', request()->routeIs('platform.*') ? [] : [
    'pageTitle' => 'Edit Menu Item',
    'activeNav' => 'menu-items',
    'bodyClass' => 'pos-theme-light',
])

@if (request()->routeIs('platform.*'))
    @section('title', 'Edit Menu Item')
@endif

@section('content')
<div class="pos-page max-w-2xl">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Menu management',
        'title' => 'Edit menu item',
        'showHome' => ! request()->routeIs('platform.*'),
        'backUrl' => request()->routeIs('platform.*') ? route('platform.vendors.menu.items.index', $tenant) : route('tenant.menu.items.index'),
        'backLabel' => 'All items',
    ])
    @include('partials.admin-flash')
    <form method="POST" enctype="multipart/form-data" data-loading-text="Saving…" class="pos-card p-6" action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.update', $item) : route('tenant.menu.items.update', $item) }}">
        @method('PUT')
        @include('tenant.menu.items.form')
    </form>
</div>
@endsection
