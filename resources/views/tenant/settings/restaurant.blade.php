@extends(request()->routeIs('platform.*') ? 'platform.layout' : 'layouts.tenant-app', request()->routeIs('platform.*') ? [] : [
    'pageTitle' => 'Settings',
    'activeNav' => 'settings',
    'bodyClass' => 'pos-theme-light',
])

@if (request()->routeIs('platform.*'))
    @section('title', 'Restaurant Settings')
@endif

@section('content')
<div class="pos-page">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Configuration',
        'title' => 'Restaurant settings',
        'subtitle' => 'Business profile, tax, service charge, receipts, and operational defaults.',
        'showHome' => ! request()->routeIs('platform.*'),
    ])

    @unless (request()->routeIs('platform.*'))
        @include('partials.admin-settings-nav', ['active' => 'general'])
    @endunless

    @include('partials.admin-flash')

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <form method="POST" action="{{ $submitRoute }}" enctype="multipart/form-data" data-loading-text="Saving…" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="pos-card p-6">
                <h2 class="pos-section-title mb-4">Business profile</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block md:col-span-2">
                        <span class="pos-label">Restaurant name</span>
                        <input name="restaurant_name" required value="{{ old('restaurant_name', $settings['restaurant_name']) }}" class="pos-field mt-1.5">
                        @error('restaurant_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="pos-label">Logo text</span>
                        <input name="logo_text" value="{{ old('logo_text', $settings['logo_text']) }}" class="pos-field mt-1.5">
                    </label>
                    <label class="block">
                        <span class="pos-label">Theme color</span>
                        <input name="theme_color" type="color" value="{{ old('theme_color', $settings['theme_color']) }}" class="pos-field mt-1.5 h-11">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="pos-label">Logo upload</span>
                        <input name="logo" type="file" accept="image/*" class="pos-field mt-1.5">
                        <p class="pos-toggle-hint mt-1">Current: {{ $settings['logo_upload_path'] ?? 'none' }}</p>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="pos-label">Address</span>
                        <textarea name="address" rows="3" class="pos-field mt-1.5">{{ old('address', $settings['address']) }}</textarea>
                    </label>
                    <label class="block">
                        <span class="pos-label">Phone</span>
                        <input name="phone" value="{{ old('phone', $settings['phone']) }}" class="pos-field mt-1.5">
                    </label>
                    <label class="block">
                        <span class="pos-label">Email</span>
                        <input name="email" type="email" value="{{ old('email', $settings['email']) }}" class="pos-field mt-1.5">
                    </label>
                    <label class="block">
                        <span class="pos-label">Website</span>
                        <input name="website" type="url" value="{{ old('website', $settings['website']) }}" class="pos-field mt-1.5">
                    </label>
                    <label class="block">
                        <span class="pos-label">Business registration no.</span>
                        <input name="business_registration_number" value="{{ old('business_registration_number', $settings['business_registration_number']) }}" class="pos-field mt-1.5" placeholder="Optional">
                    </label>
                </div>
            </section>

            <section class="pos-card p-6">
                <h2 class="pos-section-title mb-4">Operations & currency</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="pos-label">Table count</span>
                        <input name="table_count" type="number" min="0" max="1000" value="{{ old('table_count', $settings['table_count']) }}" class="pos-field mt-1.5">
                    </label>
                    <label class="block">
                        <span class="pos-label">Timezone</span>
                        <input name="timezone" value="{{ old('timezone', $settings['timezone']) }}" class="pos-field mt-1.5" placeholder="Europe/London">
                    </label>
                    <label class="block">
                        <span class="pos-label">Currency symbol</span>
                        <input name="currency_symbol" required value="{{ old('currency_symbol', $settings['currency_symbol']) }}" class="pos-field mt-1.5">
                    </label>
                    <label class="block">
                        <span class="pos-label">Currency code</span>
                        <input name="currency_code" required maxlength="3" value="{{ old('currency_code', $settings['currency_code']) }}" class="pos-field mt-1.5 uppercase">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="pos-label">Default card provider</span>
                        <select name="default_card_provider" class="pos-field mt-1.5">
                            @foreach (['external_card' => 'External card terminal', 'teya' => 'Teya', 'worldpay' => 'Worldpay'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('default_card_provider', $settings['default_card_provider']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="pos-card p-6">
                <h2 class="pos-section-title mb-4">Tax & service charge</h2>
                <div class="space-y-4">
                    <div class="pos-toggle-row">
                        <div>
                            <p class="pos-toggle-label">Enable service charge</p>
                            <p class="pos-toggle-hint">Applied to bills at the counter when enabled.</p>
                        </div>
                        <input type="hidden" name="service_charge_enabled" value="0">
                        <input type="checkbox" name="service_charge_enabled" value="1" @checked(old('service_charge_enabled', $settings['service_charge_enabled'])) class="h-5 w-5 rounded border-slate-300 text-brand-600">
                    </div>
                    <label class="block">
                        <span class="pos-label">Service charge (%)</span>
                        <input name="service_charge_percent" type="number" min="0" max="100" step="0.01" value="{{ old('service_charge_percent', $settings['service_charge_percent']) }}" class="pos-field mt-1.5 max-w-xs">
                    </label>

                    <div class="pos-toggle-row">
                        <div>
                            <p class="pos-toggle-label">Enable tax / VAT</p>
                            <p class="pos-toggle-hint">Tax is calculated on the discounted subtotal.</p>
                        </div>
                        <input type="hidden" name="tax_enabled" value="0">
                        <input type="checkbox" name="tax_enabled" value="1" @checked(old('tax_enabled', $settings['tax_enabled'])) class="h-5 w-5 rounded border-slate-300 text-brand-600">
                    </div>
                    <label class="block">
                        <span class="pos-label">Tax / VAT (%)</span>
                        <input name="tax_vat_percent" type="number" min="0" max="100" step="0.01" value="{{ old('tax_vat_percent', $settings['tax_vat_percent']) }}" class="pos-field mt-1.5 max-w-xs">
                    </label>
                </div>
            </section>

            <section class="pos-card p-6">
                <h2 class="pos-section-title mb-4">Receipts & invoices</h2>
                <div class="grid gap-4">
                    <label class="block">
                        <span class="pos-label">Receipt header</span>
                        <textarea name="receipt_header" rows="2" class="pos-field mt-1.5" placeholder="Shown at top of printed receipts">{{ old('receipt_header', $settings['receipt_header']) }}</textarea>
                    </label>
                    <label class="block">
                        <span class="pos-label">Invoice footer</span>
                        <textarea name="invoice_footer" rows="3" class="pos-field mt-1.5" placeholder="Thank you message">{{ old('invoice_footer', $settings['invoice_footer']) }}</textarea>
                    </label>
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="pos-btn-primary pos-btn-touch">Save settings</button>
                @unless (request()->routeIs('platform.*'))
                    <a href="{{ route('tenant.settings.payment-providers.index') }}" class="pos-btn-secondary pos-btn-touch">Manage payment gateways</a>
                @endunless
            </div>
        </form>

        <aside class="space-y-4">
            <div class="pos-card p-5">
                <h2 class="font-bold text-slate-900">Live preview</h2>
                <div class="mt-4 rounded-xl border border-slate-200 bg-surface-subtle p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl text-lg font-bold text-white" style="background-color: {{ $settings['theme_color'] }}">
                            {{ strtoupper(substr($settings['logo_text'] ?: $settings['restaurant_name'], 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-bold">{{ $settings['logo_text'] ?: $settings['restaurant_name'] }}</p>
                            <p class="text-sm text-slate-500">{{ $settings['restaurant_name'] }}</p>
                        </div>
                    </div>
                    <dl class="mt-5 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Currency</dt><dd class="font-semibold">{{ $settings['currency_symbol'] }} {{ $settings['currency_code'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Service charge</dt><dd class="font-semibold">{{ ($settings['service_charge_enabled'] ?? true) ? $settings['service_charge_percent'].'%' : 'Off' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Tax / VAT</dt><dd class="font-semibold">{{ ($settings['tax_enabled'] ?? true) ? $settings['tax_vat_percent'].'%' : 'Off' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Tables</dt><dd class="font-semibold">{{ $settings['table_count'] }}</dd></div>
                    </dl>
                </div>
            </div>

            @unless (request()->routeIs('platform.*'))
                <div class="pos-card p-5">
                    <h3 class="font-bold">Quick links</h3>
                    <div class="mt-3 space-y-2 text-sm">
                        <a href="{{ route('tenant.menu.categories.index') }}" class="block font-semibold text-brand-600 hover:text-brand-700">Menu categories →</a>
                        <a href="{{ route('tenant.menu.items.index') }}" class="block font-semibold text-brand-600 hover:text-brand-700">Menu items →</a>
                        <a href="{{ route('tenant.settings.payment-providers.index') }}" class="block font-semibold text-brand-600 hover:text-brand-700">Payment gateways →</a>
                        <a href="{{ route('tenant.printers.index') }}" class="block font-semibold text-brand-600 hover:text-brand-700">Printers →</a>
                    </div>
                </div>
            @endunless
        </aside>
    </div>
</div>
@endsection
