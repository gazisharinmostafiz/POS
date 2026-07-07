@extends('layouts.tenant-app', [
    'pageTitle' => 'Counter Billing',
    'activeNav' => 'counter',
    'bodyClass' => 'pos-theme-light',
])

@section('content')
<div
    id="counter-billing"
    data-component="counter-billing"
    data-data-url="{{ route('counter.data') }}"
    data-bill-url-template="{{ url('/counter/bills/__ORDER__') }}"
    data-tenant-id="{{ current_tenant()->id }}"
    data-branch-id="{{ current_branch()?->id }}"
    class="pos-page min-h-screen"
>
    <header class="pos-page-header">
        <div>
            <p class="pos-page-eyebrow">Point of sale</p>
            <h1 class="pos-page-title">Counter & billing</h1>
            <p class="pos-page-subtitle">Process payments, apply discounts, and close bills.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="pos-live-badge">
                <span class="pos-live-dot" :class="realtimeDotClass"></span>
                @{{ realtimeLabel }}
            </div>
            <button type="button" class="pos-btn-secondary" :disabled="loading" @click="loadOrders">@{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
        </div>
    </header>

    <div v-if="loading && orders.length === 0" class="pos-alert-info mb-4">Loading active orders…</div>
    <div v-if="error" class="pos-alert-error mb-4">@{{ error }}</div>

    <div class="pos-counter-layout">
        <section class="pos-card overflow-hidden">
            <div class="pos-card-header">
                <h2 class="font-bold">Open orders</h2>
                <span class="pos-badge-neutral">@{{ filteredOrders.length }} shown</span>
            </div>
            <div class="border-b border-slate-100 px-5 py-3">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="filter in filters"
                        :key="filter.key"
                        type="button"
                        class="pos-btn-tab"
                        :class="activeFilter === filter.key ? 'pos-btn-tab-active' : 'pos-btn-tab-inactive'"
                        @click="activeFilter = filter.key"
                    >@{{ filter.label }}</button>
                </div>
            </div>

            <div v-if="!loading && filteredOrders.length === 0" class="pos-empty m-5">No orders match this filter.</div>
            <div v-else class="pos-table-wrap border-0">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Order</th>
                            <th>Source</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="order in filteredOrders"
                            :key="order.id"
                            class="pos-order-row"
                            :class="selectedOrderId === order.id ? 'pos-table-row-selected' : ''"
                            @click="selectOrder(order)"
                        >
                            <td class="font-semibold text-slate-600">@{{ order.time }}</td>
                            <td>
                                <span class="font-bold text-slate-900">@{{ order.order_number }}</span>
                                <span v-if="order.is_addon" class="pos-badge-warning ml-2">Add-on</span>
                            </td>
                            <td>@{{ sourceLabel(order) }}</td>
                            <td>@{{ order.item_count }}</td>
                            <td>
                                <span class="pos-badge-neutral">@{{ order.order_status }}</span>
                                <span class="ml-1" :class="order.payment_status === 'paid' ? 'pos-badge-success' : 'pos-badge-warning'">@{{ order.payment_status }}</span>
                            </td>
                            <td class="text-right font-bold">@{{ formatMoney(order.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="pos-bill-panel">
            <div class="pos-card-header">
                <h2 class="font-bold">Bill details</h2>
            </div>

            <div class="pos-bill-scroll p-5">
                <p v-if="!bill" class="pos-empty text-sm">Select an order from the list to view and pay the bill.</p>

                <template v-if="bill">
                    <div class="rounded-xl bg-surface-subtle p-4">
                        <p class="font-bold text-slate-900">@{{ billTitle }}</p>
                        <p class="mt-1 text-sm text-slate-500">@{{ bill.order_numbers.join(', ') }}</p>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div v-for="item in bill.items" :key="item.order_number + item.name + item.quantity" class="border-b border-slate-100 pb-3">
                            <div class="flex justify-between gap-3">
                                <div>
                                    <p class="font-semibold">@{{ item.name }}</p>
                                    <p class="text-xs text-slate-500">@{{ item.quantity }} × @{{ formatMoney(item.unit_price) }}</p>
                                </div>
                                <p class="font-bold">@{{ formatMoney(item.line_total) }}</p>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-5 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-semibold">@{{ formatMoney(bill.subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="font-semibold text-red-600">−@{{ formatMoney(bill.discount_total || 0) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Service (@{{ bill.service_charge_percent }}%)</dt><dd class="font-semibold">@{{ formatMoney(bill.service_charge_total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Tax (@{{ bill.tax_vat_percent }}%)</dt><dd class="font-semibold">@{{ formatMoney(bill.tax_vat_total) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-3 text-xl font-bold">
                            <dt>Total</dt><dd class="text-brand-700">@{{ formatMoney(bill.total_payable) }}</dd>
                        </div>
                    </dl>

                    <section class="pos-card mt-5 p-4 shadow-none">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Discount</h3>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <select v-model="discount.type" class="pos-field">
                                <option value="flat">Flat amount</option>
                                <option value="percent">Percentage</option>
                            </select>
                            <input v-model.number="discount.value" type="number" min="0" step="0.01" class="pos-field" placeholder="Value">
                        </div>
                        <input v-model="discount.reason" type="text" class="pos-field mt-2" placeholder="Reason (optional)">
                        <button type="button" class="pos-btn-dark mt-3 w-full" :disabled="processing" @click="applyDiscount">@{{ processing ? 'Applying…' : 'Apply discount' }}</button>
                    </section>

                    <section class="pos-card mt-4 p-4 shadow-none">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Split bill</h3>
                        <div class="mt-3 flex gap-2">
                            <input v-model.number="split.peopleCount" type="number" min="1" class="pos-field flex-1" placeholder="People">
                            <button type="button" class="pos-btn-secondary" :disabled="processing" @click="calculateSplit">Split</button>
                        </div>
                        <p v-if="split.result" class="mt-2 text-sm font-semibold text-slate-700">
                            @{{ split.result.people_count }} × @{{ formatMoney(split.result.amount_per_person) }}
                        </p>
                    </section>

                    <section class="pos-card mt-4 p-4 shadow-none">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Payment</h3>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" class="pos-payment-key" @click="payment.cashAmount = bill.total_payable; payment.cardAmount = 0">Cash</button>
                            <button type="button" class="pos-payment-key" @click="payment.cardAmount = bill.total_payable; payment.cashAmount = 0">Card</button>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <input v-model.number="payment.cashAmount" type="number" min="0" step="0.01" class="pos-field" placeholder="Cash">
                            <input v-model.number="payment.cardAmount" type="number" min="0" step="0.01" class="pos-field" placeholder="Card">
                        </div>
                        <button type="button" class="pos-btn-success pos-btn-touch mt-3 w-full" :disabled="processing" @click="recordPayment">@{{ processing ? 'Processing…' : 'Complete payment' }}</button>
                        <dl v-if="payment.result" class="mt-3 space-y-1 rounded-xl bg-emerald-50 p-3 text-sm">
                            <div class="flex justify-between"><dt>Paid</dt><dd class="font-bold">@{{ formatMoney(payment.result.total_paid) }}</dd></div>
                            <div class="flex justify-between"><dt>Change</dt><dd class="font-bold">@{{ formatMoney(payment.result.change) }}</dd></div>
                            <div class="flex justify-between"><dt>Status</dt><dd class="font-bold capitalize">@{{ payment.result.status }}</dd></div>
                        </dl>
                    </section>
                </template>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('after-body')
    @include('partials.internal-chat')
@endpush
