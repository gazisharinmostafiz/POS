<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Dashboard</title>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-6">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Tenant admin</p>
                <h1 class="text-3xl font-black">Dashboard and reports</h1>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm font-bold">
                <a class="rounded bg-white px-3 py-2" href="{{ route('tenant.settings.restaurant.edit') }}">Restaurant settings</a>
                <a class="rounded bg-white px-3 py-2" href="{{ route('tenant.users.index') }}">Users</a>
                <a class="rounded bg-white px-3 py-2" href="{{ route('tenant.menu.categories.index') }}">Menu categories</a>
                <a class="rounded bg-white px-3 py-2" href="{{ route('tenant.menu.items.index') }}">Menu items</a>
                <a class="rounded bg-white px-3 py-2" href="{{ route('tenant.release-notes.index') }}">Release notes</a>
            </nav>
        </header>

        <form method="GET" action="{{ route('areas.tenant-admin') }}" class="mb-6 rounded-lg border border-slate-300 bg-white p-4">
            <div class="grid gap-3 md:grid-cols-4">
                <label class="block">
                    <span class="text-sm font-bold">Period</span>
                    <select name="period" class="mt-1 min-h-11 w-full rounded border border-slate-300 px-3">
                        @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'this_month' => 'This month', 'custom' => 'Custom'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['period'] ?? 'today') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Start date</span>
                    <input name="start_date" value="{{ $filters['start_date'] ?? '' }}" type="date" class="mt-1 min-h-11 w-full rounded border border-slate-300 px-3">
                </label>
                <label class="block">
                    <span class="text-sm font-bold">End date</span>
                    <input name="end_date" value="{{ $filters['end_date'] ?? '' }}" type="date" class="mt-1 min-h-11 w-full rounded border border-slate-300 px-3">
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Payment method</span>
                    <select name="payment_method" class="mt-1 min-h-11 w-full rounded border border-slate-300 px-3">
                        <option value="">All</option>
                        <option value="cash" @selected(($filters['payment_method'] ?? '') === 'cash')>Cash</option>
                        <option value="card" @selected(($filters['payment_method'] ?? '') === 'card')>Card</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Waiter</span>
                    <select name="waiter_id" class="mt-1 min-h-11 w-full rounded border border-slate-300 px-3">
                        <option value="">All</option>
                        @foreach ($filterOptions['waiters'] as $waiter)
                            <option value="{{ $waiter->id }}" @selected(($filters['waiter_id'] ?? '') == $waiter->id)>{{ $waiter->name }} ({{ $waiter->role }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Cashier</span>
                    <select name="cashier_id" class="mt-1 min-h-11 w-full rounded border border-slate-300 px-3">
                        <option value="">All</option>
                        @foreach ($filterOptions['cashiers'] as $cashier)
                            <option value="{{ $cashier->id }}" @selected(($filters['cashier_id'] ?? '') == $cashier->id)>{{ $cashier->name }} ({{ $cashier->role }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Source</span>
                    <select name="source_type" class="mt-1 min-h-11 w-full rounded border border-slate-300 px-3">
                        <option value="">All</option>
                        @foreach ($filterOptions['source_types'] as $source)
                            <option value="{{ $source }}" @selected(($filters['source_type'] ?? '') === $source)>{{ str_replace('_', '-', $source) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="min-h-11 w-full rounded bg-slate-950 px-4 py-2 font-black text-white">Apply filters</button>
                </div>
            </div>
        </form>

        @php($dashboard = $reportData['dashboard'])
        <section class="mb-6 grid gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ([
                'Total sales today' => '£'.number_format($dashboard['total_sales_today'], 2),
                'Total orders today' => $dashboard['total_orders_today'],
                'Cash collected' => '£'.number_format($dashboard['cash_collected'], 2),
                'Card collected' => '£'.number_format($dashboard['card_collected'], 2),
                'Discount total' => '£'.number_format($dashboard['discount_total'], 2),
                'Pending bills' => $dashboard['pending_bills'],
                'Active kitchen tickets' => $dashboard['active_kitchen_tickets'],
                'Average order value' => '£'.number_format($dashboard['average_order_value'], 2),
                'Top selling item' => $dashboard['top_selling_item'] ?: 'None',
            ] as $label => $value)
                <article class="rounded-lg border border-slate-300 bg-white p-4">
                    <p class="text-sm font-bold uppercase text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-slate-300 bg-white p-4">
                <h2 class="text-xl font-black">Sales report</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead><tr class="border-b"><th class="py-2">Date</th><th>Order</th><th>Source</th><th>Waiter</th><th>Cashier</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            @foreach ($reportData['sales_report'] as $row)
                                <tr class="border-b"><td class="py-2">{{ $row['date'] }}</td><td>{{ $row['order_number'] }}</td><td>{{ $row['source_type'] }}</td><td>{{ $row['waiter_name'] }}</td><td>{{ $row['cashier_name'] }}</td><td>{{ $row['payment_status'] }}</td><td class="text-right">£{{ number_format($row['total'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-slate-300 bg-white p-4">
                <h2 class="text-xl font-black">Item sales report</h2>
                <table class="mt-3 w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="py-2">Item</th><th class="text-right">Qty</th><th class="text-right">Sales</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['item_sales_report'] as $row)
                            <tr class="border-b"><td class="py-2">{{ $row['item_name'] }}</td><td class="text-right">{{ $row['quantity'] }}</td><td class="text-right">£{{ number_format($row['sales_total'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-lg border border-slate-300 bg-white p-4">
                <h2 class="text-xl font-black">Cash/card report</h2>
                @php($cashCard = $reportData['cash_card_report'])
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between"><dt>Cash collected</dt><dd class="font-bold">£{{ number_format($cashCard['cash_collected'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Card collected</dt><dd class="font-bold">£{{ number_format($cashCard['card_collected'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Total paid</dt><dd class="font-bold">£{{ number_format($cashCard['total_paid'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Payment count</dt><dd class="font-bold">{{ $cashCard['payment_count'] }}</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-300 bg-white p-4">
                <h2 class="text-xl font-black">Discount report</h2>
                <table class="mt-3 w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="py-2">Order</th><th>Type</th><th>Reason</th><th class="text-right">Discount</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['discount_report'] as $row)
                            <tr class="border-b"><td class="py-2">{{ $row['order_number'] }}</td><td>{{ $row['discount_type'] }}</td><td>{{ $row['discount_reason'] }}</td><td class="text-right">£{{ number_format($row['discount_total'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-lg border border-slate-300 bg-white p-4 xl:col-span-2">
                <h2 class="text-xl font-black">Staff report</h2>
                <p class="mt-3 text-slate-600">{{ $reportData['staff_report']['message'] }}</p>
            </div>
        </section>
        <footer class="mt-8 text-xs font-bold text-slate-500">
            Version {{ config('app.version') }}
        </footer>
    </main>
</body>
</html>
