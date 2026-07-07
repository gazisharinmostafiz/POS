@extends('layouts.tenant-app', [
    'pageTitle' => 'Payment Gateways',
    'activeNav' => 'payment-providers',
    'bodyClass' => 'pos-theme-light',
])

@section('content')
<div class="pos-page">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Configuration',
        'title' => 'Payment gateways',
        'subtitle' => 'Connect, enable, and manage card payment providers for your counter.',
        'actions' => '<a href="'.route('tenant.settings.payment-providers.create').'" class="pos-btn-primary">Add gateway</a>',
    ])

    @include('partials.admin-settings-nav', ['active' => 'payments'])
    @include('partials.admin-flash')

    @if ($providers->isEmpty())
        <section class="pos-empty">
            <p class="mb-4">No payment gateways configured yet.</p>
            <a href="{{ route('tenant.settings.payment-providers.create') }}" class="pos-btn-primary">Add your first gateway</a>
        </section>
    @else
        <div class="pos-table-wrap">
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Account</th>
                        <th>Terminal</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($providers as $provider)
                        <tr>
                            <td class="font-semibold">{{ $providerOptions[$provider->provider] ?? $provider->provider }}</td>
                            <td>{{ $provider->account_reference ?: '—' }}</td>
                            <td>{{ $provider->terminal_id ?: '—' }}</td>
                            <td><span class="pos-badge-neutral">{{ $provider->settings['mode'] ?? 'test' }}</span></td>
                            <td>
                                <span class="{{ $provider->is_active ? 'pos-badge-success' : 'pos-badge-neutral' }}">
                                    {{ $provider->is_active ? 'Enabled' : 'Disabled' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('tenant.settings.payment-providers.edit', $provider) }}" class="pos-btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                    <form method="POST" action="{{ route('tenant.settings.payment-providers.toggle', $provider) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="pos-btn-secondary px-3 py-1.5 text-xs">{{ $provider->is_active ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form method="POST" data-confirm="Remove this payment gateway?" action="{{ route('tenant.settings.payment-providers.destroy', $provider) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="pos-btn-danger px-3 py-1.5 text-xs">Remove</button>
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
