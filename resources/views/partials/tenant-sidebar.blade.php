@php
    $tenant = current_tenant();
    $user = auth()->user();
    $activeNav = $activeNav ?? null;

    $opsLinks = [
        ['key' => 'waiter', 'label' => 'Waiter POS', 'route' => 'areas.waiter', 'can' => 'access-waiter-pos', 'icon' => 'M3 10h18M3 14h18M10 3v18'],
        ['key' => 'kitchen', 'label' => 'Kitchen', 'route' => 'areas.kitchen', 'can' => 'access-kitchen-screen', 'icon' => 'M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6l2.1 2.1M5.6 18.4l2.1-2.1m8.6-8.6l2.1-2.1'],
        ['key' => 'counter', 'label' => 'Counter', 'route' => 'areas.counter', 'can' => 'access-counter-screen', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
    ];

    $adminLinks = [
        ['key' => 'admin', 'label' => 'Dashboard', 'route' => 'areas.tenant-admin', 'can' => 'access-tenant-admin-area', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
        ['key' => 'menu-categories', 'label' => 'Menu categories', 'route' => 'tenant.menu.categories.index', 'can' => 'access-tenant-admin-area', 'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
        ['key' => 'menu-items', 'label' => 'Menu items', 'route' => 'tenant.menu.items.index', 'can' => 'access-tenant-admin-area', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['key' => 'settings', 'label' => 'Settings', 'route' => 'tenant.settings.restaurant.edit', 'can' => 'access-tenant-admin-area', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ['key' => 'payment-providers', 'label' => 'Payment gateways', 'route' => 'tenant.settings.payment-providers.index', 'can' => 'access-tenant-admin-area', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['key' => 'users', 'label' => 'Team', 'route' => 'tenant.users.index', 'can' => 'access-tenant-admin-area', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['key' => 'printers', 'label' => 'Printers', 'route' => 'tenant.printers.index', 'can' => 'access-tenant-admin-area', 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
    ];
@endphp

<aside class="pos-sidebar">
    <div class="flex h-16 items-center gap-3 border-b border-slate-200/80 px-5 dark:border-slate-800">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-black text-white shadow-glow">P</div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $tenant?->name ?? 'PosLAB' }}</p>
            <p class="truncate text-xs text-slate-500">{{ current_branch()?->name ?? 'Main branch' }}</p>
        </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto p-3 pos-scroll">
        <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Operations</p>
        @foreach ($opsLinks as $link)
            @can($link['can'])
                <a href="{{ route($link['route']) }}" class="pos-nav-link {{ $activeNav === $link['key'] ? 'pos-nav-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
                    {{ $link['label'] }}
                </a>
            @endcan
        @endforeach

        @can('access-tenant-admin-area')
            <p class="mb-2 mt-6 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Management</p>
            @foreach ($adminLinks as $link)
                <a href="{{ route($link['route']) }}" class="pos-nav-link {{ $activeNav === $link['key'] ? 'pos-nav-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
                    {{ $link['label'] }}
                </a>
            @endforeach
        @endcan
    </nav>

    <div class="border-t border-slate-200/80 p-4 dark:border-slate-800">
        <div class="mb-3 flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-900/60">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</p>
                <p class="truncate text-xs capitalize text-slate-500">{{ str_replace('_', ' ', $user->role ?? '') }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="pos-btn-secondary w-full text-xs">Sign out</button>
        </form>
    </div>
</aside>
