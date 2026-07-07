@extends('layouts.tenant-app', [
    'pageTitle' => $providerAccount->exists ? 'Edit Gateway' : 'Add Gateway',
    'activeNav' => 'payment-providers',
    'bodyClass' => 'pos-theme-light',
])

@section('content')
@php
    $credentials = $providerAccount->encrypted_credentials ?? [];
    $selectedProvider = old('provider', $providerAccount->provider ?? 'external_card');
@endphp

<div class="pos-page max-w-3xl">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Configuration',
        'title' => $providerAccount->exists ? 'Edit payment gateway' : 'Add payment gateway',
        'subtitle' => 'Configure provider credentials and terminal details.',
        'backUrl' => route('tenant.settings.payment-providers.index'),
        'backLabel' => 'All gateways',
    ])

    @include('partials.admin-flash')

    <form method="POST" action="{{ $submitRoute }}" data-loading-text="Saving…" class="pos-card p-6">
        @csrf
        @if ($method !== 'POST') @method($method) @endif

        <div class="grid gap-4 md:grid-cols-2">
            <label class="block md:col-span-2">
                <span class="pos-label">Provider</span>
                <select name="provider" class="pos-field mt-1.5" @disabled($providerAccount->exists)>
                    @foreach ($providerOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedProvider === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($providerAccount->exists)
                    <input type="hidden" name="provider" value="{{ $providerAccount->provider }}">
                @endif
            </label>

            <label class="block">
                <span class="pos-label">Account reference</span>
                <input name="account_reference" value="{{ old('account_reference', $providerAccount->account_reference) }}" class="pos-field mt-1.5" placeholder="merchant-account-id">
            </label>

            <label class="block">
                <span class="pos-label">Terminal ID</span>
                <input name="terminal_id" value="{{ old('terminal_id', $providerAccount->terminal_id) }}" class="pos-field mt-1.5" placeholder="Optional">
            </label>

            <label class="block">
                <span class="pos-label">Environment</span>
                <select name="mode" class="pos-field mt-1.5">
                    <option value="test" @selected(old('mode', $providerAccount->settings['mode'] ?? 'test') === 'test')>Test</option>
                    <option value="live" @selected(old('mode', $providerAccount->settings['mode'] ?? 'test') === 'live')>Live</option>
                </select>
            </label>

            <div class="pos-toggle-row md:col-span-2">
                <div>
                    <p class="pos-toggle-label">Enable gateway</p>
                    <p class="pos-toggle-hint">Disabled gateways cannot be used at the counter.</p>
                </div>
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $providerAccount->is_active ?? true)) class="h-5 w-5 rounded border-slate-300 text-brand-600">
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-slate-200 bg-surface-subtle p-4">
            <h3 class="pos-section-title mb-3">API credentials</h3>
            <p class="pos-toggle-hint mb-4">Required for Teya and Worldpay. Leave API key blank when editing to keep the existing secret.</p>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block md:col-span-2">
                    <span class="pos-label">API key</span>
                    <input name="api_key" type="password" autocomplete="new-password" class="pos-field mt-1.5" placeholder="{{ $providerAccount->exists ? '••••••••' : 'Required for Teya / Worldpay' }}">
                </label>
                <label class="block md:col-span-2">
                    <span class="pos-label">Base URL</span>
                    <input name="base_url" type="url" value="{{ old('base_url', $credentials['base_url'] ?? '') }}" class="pos-field mt-1.5" placeholder="https://api.provider.test">
                </label>
                <label class="block">
                    <span class="pos-label">Product (Worldpay)</span>
                    <input name="product" value="{{ old('product', $credentials['product'] ?? '') }}" class="pos-field mt-1.5" placeholder="terminal">
                </label>
                <label class="block">
                    <span class="pos-label">Timeout (seconds)</span>
                    <input name="timeout" type="number" min="1" max="120" value="{{ old('timeout', $credentials['timeout'] ?? 5) }}" class="pos-field mt-1.5">
                </label>
                <label class="block md:col-span-2">
                    <span class="pos-label">Webhook secret (Worldpay)</span>
                    <input name="webhook_secret" type="password" autocomplete="new-password" class="pos-field mt-1.5" placeholder="{{ $providerAccount->exists ? '••••••••' : 'Optional' }}">
                </label>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="pos-btn-primary">Save gateway</button>
            <a href="{{ route('tenant.settings.payment-providers.index') }}" class="pos-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
