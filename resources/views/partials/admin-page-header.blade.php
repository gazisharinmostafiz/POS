@php
    $eyebrow = $eyebrow ?? 'Management';
    $title = $title ?? 'Admin';
    $subtitle = $subtitle ?? null;
    $backUrl = $backUrl ?? (request()->routeIs('platform.*') ? url()->previous() : route('areas.tenant-admin'));
    $backLabel = $backLabel ?? (request()->routeIs('platform.*') ? 'Back' : 'Dashboard');
    $showHome = $showHome ?? ! request()->routeIs('platform.*');
@endphp

<header class="pos-page-header">
    <div>
        <p class="pos-page-eyebrow">{{ $eyebrow }}</p>
        <h1 class="pos-page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="pos-page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($showHome)
            <a href="{{ route('areas.tenant-admin') }}" class="pos-btn-secondary">Dashboard</a>
        @endif
        <a href="{{ $backUrl }}" class="pos-btn-ghost">{{ $backLabel }}</a>
        @isset($actions)
            {!! $actions !!}
        @endisset
    </div>
</header>
