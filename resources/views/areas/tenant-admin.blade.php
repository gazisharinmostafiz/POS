@extends('layouts.tenant-app', [
    'pageTitle' => 'Dashboard',
    'activeNav' => 'admin',
    'bodyClass' => 'pos-theme-light',
])

@section('content')
@php
    $dashboard = $reportData['dashboard'];
    $currency = '£';
    $maxItemSales = collect($reportData['item_sales_report'])->max('sales_total') ?: 1;
@endphp

<div class="pos-page">
    <header class="pos-page-header">
        <div>
            <p class="pos-page-eyebrow">Management</p>
            <h1 class="pos-page-title">Dashboard & analytics</h1>
            <p class="pos-page-subtitle">Sales, orders, payments, and performance for {{ current_tenant()?->name }}.</p>
        </div>
        <div
            id="tenant-dashboard"
            data-component="tenant-dashboard"
            data-data-url="{{ route('tenant.reports.data') }}"
            data-tenant-id="{{ current_tenant()->id }}"
            data-query="{{ http_build_query($filters) }}"
        >
            <div class="pos-live-badge">
                <span class="pos-live-dot" :class="realtimeDotClass"></span>
                @{{ realtimeLabel }}
            </div>
        </div>
    </header>

    <form method="GET" action="{{ route('areas.tenant-admin') }}" class="pos-filter-bar mb-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-bold text-slate-900">Filters</h2>
            <button type="submit" class="pos-btn-primary">Apply filters</button>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="block">
                <span class="pos-label">Period</span>
                <select name="period" class="pos-field mt-1.5">
                    @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'this_month' => 'This month', 'custom' => 'Custom range'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['period'] ?? 'today') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="pos-label">Start date</span>
                <input name="start_date" value="{{ $filters['start_date'] ?? '' }}" type="date" class="pos-field mt-1.5">
            </label>
            <label class="block">
                <span class="pos-label">End date</span>
                <input name="end_date" value="{{ $filters['end_date'] ?? '' }}" type="date" class="pos-field mt-1.5">
            </label>
            <label class="block">
                <span class="pos-label">Payment method</span>
                <select name="payment_method" class="pos-field mt-1.5">
                    <option value="">All methods</option>
                    <option value="cash" @selected(($filters['payment_method'] ?? '') === 'cash')>Cash</option>
                    <option value="card" @selected(($filters['payment_method'] ?? '') === 'card')>Card</option>
                </select>
            </label>
            <label class="block">
                <span class="pos-label">Waiter</span>
                <select name="waiter_id" class="pos-field mt-1.5">
                    <option value="">All waiters</option>
                    @foreach ($filterOptions['waiters'] as $waiter)
                        <option value="{{ $waiter->id }}" @selected(($filters['waiter_id'] ?? '') == $waiter->id)>{{ $waiter->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="pos-label">Cashier</span>
                <select name="cashier_id" class="pos-field mt-1.5">
                    <option value="">All cashiers</option>
                    @foreach ($filterOptions['cashiers'] as $cashier)
                        <option value="{{ $cashier->id }}" @selected(($filters['cashier_id'] ?? '') == $cashier->id)>{{ $cashier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="pos-label">Order source</span>
                <select name="source_type" class="pos-field mt-1.5">
                    <option value="">All sources</option>
                    @foreach ($filterOptions['source_types'] as $source)
                        <option value="{{ $source }}" @selected(($filters['source_type'] ?? '') === $source)>{{ str_replace('_', ' ', ucfirst($source)) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </form>

    <section class="pos-stat-grid mb-8">
        @foreach ([
            ['label' => 'Total sales', 'value' => $currency.number_format($dashboard['total_sales_today'], 2), 'meta' => 'Filtered period', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'iconClass' => 'bg-brand-50 text-brand-600'],
            ['label' => 'Orders', 'value' => number_format($dashboard['total_orders_today']), 'meta' => 'Completed & open', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'iconClass' => 'bg-accent-50 text-accent-600'],
            ['label' => 'Avg order value', 'value' => $currency.number_format($dashboard['average_order_value'], 2), 'meta' => 'Per transaction', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'iconClass' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Pending bills', 'value' => number_format($dashboard['pending_bills']), 'meta' => 'Awaiting payment', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'iconClass' => 'bg-amber-50 text-amber-600'],
            ['label' => 'Cash collected', 'value' => $currency.number_format($dashboard['cash_collected'], 2), 'meta' => 'Cash payments', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'iconClass' => 'bg-slate-100 text-slate-600'],
            ['label' => 'Card collected', 'value' => $currency.number_format($dashboard['card_collected'], 2), 'meta' => 'Card payments', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'iconClass' => 'bg-slate-100 text-slate-600'],
            ['label' => 'Kitchen tickets', 'value' => number_format($dashboard['active_kitchen_tickets']), 'meta' => 'Active now', 'icon' => 'M12 3v3m0 12v3M3 12h3m12 0h3', 'iconClass' => 'bg-accent-50 text-accent-600'],
            ['label' => 'Top item', 'value' => $dashboard['top_selling_item'] ?: '—', 'meta' => 'Best seller', 'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', 'iconClass' => 'bg-brand-50 text-brand-600'],
        ] as $kpi)
            <article class="pos-kpi-card">
                <div class="pos-kpi-icon {{ $kpi['iconClass'] }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['icon'] }}"/></svg>
                </div>
                <p class="pos-kpi-label mt-4">{{ $kpi['label'] }}</p>
                <p class="pos-kpi-value">{{ $kpi['value'] }}</p>
                <p class="pos-kpi-meta">{{ $kpi['meta'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="pos-report-grid">
        <div class="pos-card xl:col-span-2">
            <div class="pos-card-header">
                <h2 class="font-bold">Sales report</h2>
                <span class="pos-badge-neutral">{{ count($reportData['sales_report']) }} orders</span>
            </div>
            <div class="pos-table-wrap border-0 border-t border-slate-100">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order</th>
                            <th>Source</th>
                            <th>Waiter</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reportData['sales_report'] as $row)
                            <tr>
                                <td class="text-slate-500">{{ $row['date'] }}</td>
                                <td class="font-semibold">{{ $row['order_number'] }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($row['source_type'])) }}</td>
                                <td>{{ $row['waiter_name'] }}</td>
                                <td><span class="{{ $row['payment_status'] === 'paid' ? 'pos-badge-success' : 'pos-badge-warning' }}">{{ $row['payment_status'] }}</span></td>
                                <td class="text-right font-bold">{{ $currency }}{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-slate-500">No sales in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pos-card">
            <div class="pos-card-header">
                <h2 class="font-bold">Item performance</h2>
            </div>
            <div class="pos-card-body space-y-4">
                @forelse ($reportData['item_sales_report'] as $row)
                    <div>
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="font-semibold">{{ $row['item_name'] }}</span>
                            <span class="font-bold">{{ $currency }}{{ number_format($row['sales_total'], 2) }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="pos-chart-bar h-full" style="width: {{ min(100, round(($row['sales_total'] / $maxItemSales) * 100)) }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $row['quantity'] }} sold</p>
                    </div>
                @empty
                    <p class="pos-empty text-sm">No item sales data.</p>
                @endforelse
            </div>
        </div>

        <div class="pos-card">
            <div class="pos-card-header"><h2 class="font-bold">Cash & card</h2></div>
            @php($cashCard = $reportData['cash_card_report'])
            <div class="pos-card-body space-y-4">
                <div class="flex items-center justify-between rounded-xl bg-emerald-50 p-4">
                    <span class="font-semibold text-emerald-900">Cash</span>
                    <span class="text-xl font-bold text-emerald-700">{{ $currency }}{{ number_format($cashCard['cash_collected'], 2) }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-brand-50 p-4">
                    <span class="font-semibold text-brand-900">Card</span>
                    <span class="text-xl font-bold text-brand-700">{{ $currency }}{{ number_format($cashCard['card_collected'], 2) }}</span>
                </div>
                <dl class="space-y-2 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Total paid</dt><dd class="font-bold">{{ $currency }}{{ number_format($cashCard['total_paid'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Payments</dt><dd class="font-bold">{{ $cashCard['payment_count'] }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="pos-card">
            <div class="pos-card-header"><h2 class="font-bold">Discounts</h2></div>
            <div class="pos-table-wrap border-0 border-t border-slate-100">
                <table class="pos-table">
                    <thead><tr><th>Order</th><th>Type</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($reportData['discount_report'] as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['order_number'] }}</td>
                                <td>{{ $row['discount_type'] }}</td>
                                <td class="text-right font-bold text-red-600">−{{ $currency }}{{ number_format($row['discount_total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-slate-500">No discounts applied.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <footer class="mt-10 text-xs text-slate-400">PosLAB v{{ config('app.version') }} · Reports refresh live when orders and payments change.</footer>
</div>
@endsection
