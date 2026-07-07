@extends(request()->routeIs('platform.*') ? 'platform.layout' : 'layouts.tenant-app', request()->routeIs('platform.*') ? [] : [
    'pageTitle' => 'Create Category',
    'activeNav' => 'menu-categories',
    'bodyClass' => 'pos-theme-light',
])

@if (request()->routeIs('platform.*'))
    @section('title', 'Create Category')
@endif

@section('content')
<div class="pos-page max-w-2xl">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Menu management',
        'title' => 'Create category',
        'showHome' => ! request()->routeIs('platform.*'),
        'backUrl' => request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.index', $tenant) : route('tenant.menu.categories.index'),
        'backLabel' => 'All categories',
    ])
    @include('partials.admin-flash')
    <form method="POST" data-loading-text="Saving…" class="pos-card p-6" action="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.store', $tenant) : route('tenant.menu.categories.store') }}">
        @include('tenant.menu.categories.form')
    </form>
</div>
@endsection
