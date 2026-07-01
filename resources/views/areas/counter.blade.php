<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Counter Billing</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main
        id="counter-billing"
        data-component="counter-billing"
        data-data-url="{{ route('counter.data') }}"
        data-bill-url-template="{{ url('/counter/bills/__ORDER__') }}"
        data-tenant-id="{{ current_tenant()->id }}"
        data-branch-id="{{ current_branch()?->id }}"
        class="min-h-screen"
    >
        <div class="mx-auto max-w-7xl px-4 py-5">
            <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Counter / Cashier</p>
                    <h1 class="text-3xl font-black">Billing</h1>
                </div>
                <button type="button" class="min-h-12 rounded-lg border border-slate-400 bg-white px-5 py-3 font-bold disabled:opacity-50" :disabled="loading" @click="loadOrders">@{{ loading ? 'Refreshing...' : 'Refresh' }}</button>
            </header>

            <div v-if="loading" class="mb-4 rounded-lg border border-slate-300 bg-white px-4 py-3 font-bold text-slate-600">Loading active orders...</div>
            <div v-if="error" class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 font-bold text-red-800">@{{ error }}</div>

            <div class="grid gap-5 lg:grid-cols-[1fr_420px]">
                <section class="rounded-lg border border-slate-300 bg-white p-4">
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button
                            v-for="filter in filters"
                            :key="filter.key"
                            type="button"
                            class="min-h-11 rounded-lg px-4 py-2 font-bold"
                            :class="activeFilter === filter.key ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-800'"
                            @click="activeFilter = filter.key"
                        >
                            @{{ filter.label }}
                        </button>
                    </div>

                    <div v-if="!loading && filteredOrders.length === 0" class="pos-empty">No orders match this filter.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left">
                            <thead class="border-b border-slate-300 text-sm uppercase text-slate-500">
                                <tr>
                                    <th class="py-3 pr-3">Time</th>
                                    <th class="py-3 pr-3">Order</th>
                                    <th class="py-3 pr-3">Source</th>
                                    <th class="py-3 pr-3">Items</th>
                                    <th class="py-3 pr-3">Status</th>
                                    <th class="py-3 pr-3 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="order in filteredOrders"
                                    :key="order.id"
                                    class="cursor-pointer border-b border-slate-200 hover:bg-cyan-50"
                                    :class="selectedOrderId === order.id ? 'bg-cyan-100' : ''"
                                    @click="selectOrder(order)"
                                >
                                    <td class="py-4 pr-3 font-bold">@{{ order.time }}</td>
                                    <td class="py-4 pr-3">
                                        <span class="block font-black">@{{ order.order_number }}</span>
                                        <span v-if="order.is_addon" class="mt-1 inline-flex rounded bg-amber-200 px-2 py-1 text-xs font-black text-amber-900">ADD-ON</span>
                                    </td>
                                    <td class="py-4 pr-3 font-bold">@{{ sourceLabel(order) }}</td>
                                    <td class="py-4 pr-3">@{{ order.item_count }}</td>
                                    <td class="py-4 pr-3">
                                        <span class="rounded-full bg-slate-200 px-3 py-1 text-sm font-bold">@{{ order.order_status }}</span>
                                        <span class="ml-1 rounded-full px-3 py-1 text-sm font-bold" :class="order.payment_status === 'paid' ? 'bg-emerald-200 text-emerald-900' : 'bg-orange-200 text-orange-900'">@{{ order.payment_status }}</span>
                                    </td>
                                    <td class="py-4 pr-3 text-right font-black">@{{ formatMoney(order.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="rounded-lg border border-slate-300 bg-white p-4">
                    <h2 class="text-2xl font-black">Selected bill</h2>
                    <p v-if="!bill" class="mt-4 text-slate-500">Select an order to view the bill.</p>

                    <div v-if="bill" class="mt-4">
                        <div class="rounded-lg bg-slate-100 p-3">
                            <p class="font-black">@{{ billTitle }}</p>
                            <p class="mt-1 text-sm text-slate-600">@{{ bill.order_numbers.join(', ') }}</p>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div v-for="item in bill.items" :key="item.order_number + item.name + item.quantity" class="border-b border-slate-200 pb-3">
                                <div class="flex justify-between gap-3">
                                    <div>
                                        <p class="font-bold">@{{ item.name }}</p>
                                        <p class="text-sm text-slate-500">@{{ item.order_number }} <span v-if="item.is_addon">ADD-ON</span></p>
                                    </div>
                                    <p class="font-black">@{{ formatMoney(item.line_total) }}</p>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">@{{ item.quantity }} x @{{ formatMoney(item.unit_price) }}</p>
                            </div>
                        </div>

                        <dl class="mt-4 space-y-2 text-lg">
                            <div class="flex justify-between">
                                <dt>Subtotal</dt>
                                <dd class="font-bold">@{{ formatMoney(bill.subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Discount</dt>
                                <dd class="font-bold">-@{{ formatMoney(bill.discount_total || 0) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>After discount</dt>
                                <dd class="font-bold">@{{ formatMoney(bill.discounted_subtotal || bill.subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Service (@{{ bill.service_charge_percent }}%)</dt>
                                <dd class="font-bold">@{{ formatMoney(bill.service_charge_total) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Tax/VAT (@{{ bill.tax_vat_percent }}%)</dt>
                                <dd class="font-bold">@{{ formatMoney(bill.tax_vat_total) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-slate-300 pt-3 text-2xl font-black">
                                <dt>Total payable</dt>
                                <dd>@{{ formatMoney(bill.total_payable) }}</dd>
                            </div>
                        </dl>

                        <section class="mt-5 rounded-lg border border-slate-300 p-3">
                            <h3 class="font-black">Discount</h3>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <select v-model="discount.type" class="min-h-11 rounded-lg border border-slate-300 px-3">
                                    <option value="flat">Flat amount</option>
                                    <option value="percent">Percentage</option>
                                </select>
                                <input v-model.number="discount.value" type="number" min="0" step="0.01" class="min-h-11 rounded-lg border border-slate-300 px-3" placeholder="Value">
                            </div>
                            <input v-model="discount.reason" type="text" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3" placeholder="Reason optional">
                            <button type="button" class="mt-3 min-h-11 w-full rounded-lg bg-slate-950 px-4 py-2 font-black text-white disabled:opacity-50" :disabled="processing" @click="applyDiscount">@{{ processing ? 'Applying...' : 'Apply discount' }}</button>
                        </section>

                        <section class="mt-4 rounded-lg border border-slate-300 p-3">
                            <h3 class="font-black">Split bill</h3>
                            <div class="mt-3 flex gap-2">
                                <input v-model.number="split.peopleCount" type="number" min="1" step="1" class="min-h-11 flex-1 rounded-lg border border-slate-300 px-3" placeholder="People">
                                <button type="button" class="min-h-11 rounded-lg bg-slate-950 px-4 py-2 font-black text-white disabled:opacity-50" :disabled="processing" @click="calculateSplit">Split</button>
                            </div>
                            <p v-if="split.result" class="mt-2 font-bold">
                                @{{ split.result.people_count }} people: @{{ formatMoney(split.result.amount_per_person) }} each
                                <span v-if="split.result.rounding_difference">(@{{ formatMoney(split.result.rounding_difference) }} rounding)</span>
                            </p>
                        </section>

                        <section class="mt-4 rounded-lg border border-slate-300 p-3">
                            <h3 class="font-black">Payment</h3>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <input v-model.number="payment.cashAmount" type="number" min="0" step="0.01" class="min-h-11 rounded-lg border border-slate-300 px-3" placeholder="Cash amount">
                                <input v-model.number="payment.cardAmount" type="number" min="0" step="0.01" class="min-h-11 rounded-lg border border-slate-300 px-3" placeholder="Card amount">
                            </div>
                            <button type="button" class="mt-3 min-h-11 w-full rounded-lg bg-emerald-600 px-4 py-2 font-black text-white disabled:opacity-50" :disabled="processing" @click="recordPayment">@{{ processing ? 'Recording...' : 'Record payment' }}</button>
                            <dl v-if="payment.result" class="mt-3 space-y-1 font-bold">
                                <div class="flex justify-between"><dt>Total paid</dt><dd>@{{ formatMoney(payment.result.total_paid) }}</dd></div>
                                <div class="flex justify-between"><dt>Balance</dt><dd>@{{ formatMoney(payment.result.balance) }}</dd></div>
                                <div class="flex justify-between"><dt>Change</dt><dd>@{{ formatMoney(payment.result.change) }}</dd></div>
                                <div class="flex justify-between"><dt>Status</dt><dd>@{{ payment.result.status }}</dd></div>
                            </dl>
                        </section>
                    </div>
                </aside>
            </div>
        </div>
    </main>
    @include('partials.internal-chat')
</body>
</html>
