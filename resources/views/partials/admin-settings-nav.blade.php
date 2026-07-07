@php
    $active = $active ?? 'general';
@endphp

<nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-4">
    <a href="{{ route('tenant.settings.restaurant.edit') }}" class="pos-btn-tab {{ $active === 'general' ? 'pos-btn-tab-active' : 'pos-btn-tab-inactive' }}">General & billing</a>
    <a href="{{ route('tenant.settings.payment-providers.index') }}" class="pos-btn-tab {{ $active === 'payments' ? 'pos-btn-tab-active' : 'pos-btn-tab-inactive' }}">Payment gateways</a>
</nav>
