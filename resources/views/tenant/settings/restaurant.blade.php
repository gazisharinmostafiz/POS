@php
    $title = 'Restaurant Settings';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Tong POS Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <main class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">Tenant settings</p>
                <h1 class="mt-2 text-3xl font-bold">{{ $settings['restaurant_name'] }}</h1>
                <p class="mt-2 text-slate-400">Manage restaurant identity, contact details, currency, charges, and table defaults.</p>
            </div>
            <a href="{{ url()->previous() }}" class="rounded border border-slate-700 px-4 py-2 text-sm text-slate-200">Back</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <form method="POST" action="{{ $submitRoute }}" enctype="multipart/form-data" data-loading-text="Saving..." class="rounded border border-slate-800 bg-slate-900 p-5">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium" for="restaurant_name">Restaurant name</label>
                        <input id="restaurant_name" name="restaurant_name" required value="{{ old('restaurant_name', $settings['restaurant_name']) }}" class="pos-field-dark mt-2">
                        @error('restaurant_name') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="logo_text">Logo text</label>
                        <input id="logo_text" name="logo_text" value="{{ old('logo_text', $settings['logo_text']) }}" class="pos-field-dark mt-2">
                        @error('logo_text') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="logo">Logo upload</label>
                        <input id="logo" name="logo" type="file" accept="image/*" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        <p class="mt-1 text-xs text-slate-400">Current path: {{ $settings['logo_upload_path'] ?? 'none' }}</p>
                        @error('logo') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="theme_color">Theme color</label>
                        <input id="theme_color" name="theme_color" type="color" value="{{ old('theme_color', $settings['theme_color']) }}" class="mt-2 h-10 w-full rounded border border-slate-700 bg-slate-950 px-2 py-1">
                        @error('theme_color') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium" for="address">Address</label>
                        <textarea id="address" name="address" rows="3" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">{{ old('address', $settings['address']) }}</textarea>
                        @error('address') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="phone">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $settings['phone']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('phone') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $settings['email']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('email') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="website">Website</label>
                        <input id="website" name="website" type="url" value="{{ old('website', $settings['website']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('website') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="table_count">Table count</label>
                        <input id="table_count" name="table_count" type="number" min="0" max="1000" value="{{ old('table_count', $settings['table_count']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('table_count') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="currency_symbol">Currency symbol</label>
                        <input id="currency_symbol" name="currency_symbol" required value="{{ old('currency_symbol', $settings['currency_symbol']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('currency_symbol') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="currency_code">Currency code</label>
                        <input id="currency_code" name="currency_code" required maxlength="3" value="{{ old('currency_code', $settings['currency_code']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 uppercase">
                        @error('currency_code') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="service_charge_percent">Service charge percent</label>
                        <input id="service_charge_percent" name="service_charge_percent" type="number" min="0" max="100" step="0.01" value="{{ old('service_charge_percent', $settings['service_charge_percent']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('service_charge_percent') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium" for="tax_vat_percent">Tax/VAT percent</label>
                        <input id="tax_vat_percent" name="tax_vat_percent" type="number" min="0" max="100" step="0.01" value="{{ old('tax_vat_percent', $settings['tax_vat_percent']) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('tax_vat_percent') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium" for="invoice_footer">Invoice footer</label>
                        <textarea id="invoice_footer" name="invoice_footer" rows="3" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">{{ old('invoice_footer', $settings['invoice_footer']) }}</textarea>
                        @error('invoice_footer') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" class="pos-button mt-6 bg-cyan-400 text-slate-950">Save settings</button>
            </form>

            <aside class="rounded border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">Branding preview</p>
                <div class="mt-5 rounded border border-slate-800 bg-slate-950 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded text-lg font-bold text-white" style="background-color: {{ $settings['theme_color'] }}">
                            {{ substr($settings['logo_text'] ?: $settings['restaurant_name'], 0, 1) }}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold">{{ $settings['logo_text'] ?: $settings['restaurant_name'] }}</h2>
                            <p class="text-sm text-slate-400">{{ $settings['restaurant_name'] }}</p>
                        </div>
                    </div>

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400">Currency</dt>
                            <dd class="font-medium">{{ $settings['currency_symbol'] }} {{ $settings['currency_code'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400">Service charge</dt>
                            <dd class="font-medium">{{ $settings['service_charge_percent'] }}%</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400">Tax/VAT</dt>
                            <dd class="font-medium">{{ $settings['tax_vat_percent'] }}%</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400">Tables available later</dt>
                            <dd class="font-medium">{{ $settings['table_count'] }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 rounded bg-slate-900 p-4 text-sm text-slate-300">
                        {{ $settings['invoice_footer'] ?: 'Invoice footer preview' }}
                    </div>
                </div>
            </aside>
        </div>
    </main>
</body>
</html>
