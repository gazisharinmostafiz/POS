@php
    $activeNav = $activeNav ?? null;

    $mobileLinks = collect([
        ['key' => 'waiter', 'label' => 'Waiter', 'route' => 'areas.waiter', 'can' => 'access-waiter-pos', 'icon' => 'M3 10h18M3 14h18M10 3v18'],
        ['key' => 'kitchen', 'label' => 'Kitchen', 'route' => 'areas.kitchen', 'can' => 'access-kitchen-screen', 'icon' => 'M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6l2.1 2.1M5.6 18.4l2.1-2.1m8.6-8.6l2.1-2.1'],
        ['key' => 'counter', 'label' => 'Counter', 'route' => 'areas.counter', 'can' => 'access-counter-screen', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        ['key' => 'admin', 'label' => 'Admin', 'route' => 'areas.tenant-admin', 'can' => 'access-tenant-admin-area', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
    ])->filter(fn ($link) => auth()->user()?->can($link['can']));
@endphp

@if ($mobileLinks->isNotEmpty())
    <nav class="pos-mobile-nav" aria-label="Mobile navigation">
        @foreach ($mobileLinks as $link)
            <a href="{{ route($link['route']) }}" class="pos-mobile-nav-link {{ $activeNav === $link['key'] ? 'pos-mobile-nav-link-active' : '' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
@endif
