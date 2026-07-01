<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Waiter POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <main
        id="waiter-pos"
        data-component="waiter-pos"
        data-data-url="{{ route('waiter.pos.data') }}"
        data-order-url="{{ route('waiter.pos.orders.store') }}"
        data-addon-url="{{ route('waiter.pos.orders.addon') }}"
        data-active-order-url-template="{{ url('/waiter/pos/tables/__TABLE__/active-order') }}"
        data-tenant-id="{{ current_tenant()->id }}"
        data-branch-id="{{ current_branch()?->id }}"
        class="min-h-screen"
    >
        <div class="mx-auto max-w-7xl px-4 py-5">
            <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">Waiter POS</p>
                    <h1 class="text-3xl font-bold">Order screen</h1>
                </div>
                <button type="button" class="min-h-12 rounded-lg border border-slate-700 px-5 py-3 text-base font-semibold" @click="chatPlaceholder = true">
                    Quick chat
                </button>
            </header>

            <div v-if="loading" class="mb-4 rounded-lg border border-slate-800 bg-slate-900 px-4 py-3 text-slate-300">Loading tables and menu...</div>
            <div v-if="notice" class="mb-4 rounded-lg border border-emerald-700 bg-emerald-950 px-4 py-3 text-emerald-200">@{{ notice }}</div>
            <div v-if="error" class="mb-4 rounded-lg border border-red-700 bg-red-950 px-4 py-3 text-red-200">@{{ error }}</div>
            <div v-if="chatPlaceholder" class="mb-4 rounded-lg border border-cyan-700 bg-cyan-950 px-4 py-3 text-cyan-100">Quick chat placeholder for future staff messaging.</div>

            <section class="mb-5 rounded-lg border border-slate-800 bg-slate-900 p-4">
                <div class="mb-3 flex flex-wrap gap-3">
                    <button type="button" class="min-h-14 rounded-lg px-5 py-3 text-base font-semibold" :class="sourceType === 'takeaway' ? selectedClass : neutralClass" @click="selectSource('takeaway')">Takeaway</button>
                    <button type="button" class="min-h-14 rounded-lg px-5 py-3 text-base font-semibold" :class="sourceType === 'walk_in' ? selectedClass : neutralClass" @click="selectSource('walk_in')">Walk-in</button>
                </div>

                <h2 class="mb-3 text-lg font-semibold">Tables</h2>
                <div v-if="!loading && tables.length === 0" class="pos-empty-dark">No active tables are configured. Use Takeaway or Walk-in, or ask an admin to update table count.</div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    <button
                        v-for="table in tables"
                        :key="table.id"
                        type="button"
                        class="min-h-20 rounded-lg border p-3 text-left"
                        :class="sourceType === 'table' && tableNumber === table.number ? 'border-cyan-300 bg-cyan-950' : 'border-slate-700 bg-slate-950'"
                        @click="selectTable(table)"
                    >
                        <span class="block text-lg font-bold">@{{ table.label || ('Table ' + table.number) }}</span>
                        <span class="mt-2 inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(table.status)">@{{ table.status }}</span>
                    </button>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
                <section class="rounded-lg border border-slate-800 bg-slate-900 p-4">
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <button type="button" class="min-h-12 rounded-lg px-4 py-3 text-base font-semibold" :class="selectedCategoryId === null ? selectedClass : neutralClass" @click="selectedCategoryId = null">All</button>
                        <button
                            v-for="category in categories"
                            :key="category.id"
                            type="button"
                            class="min-h-12 rounded-lg px-4 py-3 text-base font-semibold"
                            :class="selectedCategoryId === category.id ? selectedClass : neutralClass"
                            @click="selectedCategoryId = category.id"
                        >
                            @{{ category.name }}
                        </button>
                    </div>

                    <div class="mb-4 flex gap-2">
                        <input v-model="search" type="search" class="min-h-12 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-4 text-base" placeholder="Search menu">
                        <button type="button" class="min-h-12 rounded-lg border border-slate-700 px-4 font-semibold" @click="search = ''">Clear</button>
                    </div>

                    <div v-if="!loading && filteredMenuItems.length === 0" class="pos-empty-dark">No menu items match the current search and category.</div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <button
                            v-for="item in filteredMenuItems"
                            :key="item.id"
                            type="button"
                            class="min-h-36 rounded-lg border border-slate-800 bg-slate-950 p-4 text-left shadow-sm transition hover:border-cyan-400"
                            @click="addToCart(item)"
                            data-menu-card
                        >
                            <span class="block text-lg font-bold">@{{ item.name }}</span>
                            <span class="mt-2 block text-sm text-slate-400">@{{ item.description || 'No description' }}</span>
                            <span class="mt-4 flex items-center justify-between">
                                <span class="text-xl font-bold">@{{ formatMoney(item.price) }}</span>
                                <span class="min-h-12 rounded-lg bg-cyan-400 px-4 py-3 font-bold text-slate-950" @click.stop="addToCart(item)">+</span>
                            </span>
                        </button>
                    </div>
                </section>

                <aside class="rounded-lg border border-slate-800 bg-slate-900 p-4">
                    <h2 class="text-xl font-bold">Cart</h2>
                    <p class="mt-1 text-sm text-slate-400">@{{ sourceLabel }}</p>

                    <div class="mt-4 space-y-3">
                        <p v-if="cart.length === 0" class="pos-empty-dark">Cart is empty. Tap any menu item card to add it.</p>
                        <div v-for="line in cart" :key="line.id" class="rounded-lg border border-slate-800 bg-slate-950 p-3">
                            <div class="flex justify-between gap-3">
                                <div>
                                    <p class="font-semibold">@{{ line.name }}</p>
                                    <p class="text-sm text-slate-400">@{{ formatMoney(line.price) }}</p>
                                </div>
                                <button type="button" class="min-h-11 rounded border border-red-700 px-3 text-red-200" @click="removeFromCart(line.id)">Remove</button>
                            </div>
                            <div class="mt-3 flex items-center gap-3">
                                <button type="button" class="min-h-12 min-w-12 rounded bg-slate-800 text-xl font-bold" @click="decreaseQuantity(line.id)">-</button>
                                <span class="min-w-10 text-center text-lg font-bold">@{{ line.quantity }}</span>
                                <button type="button" class="min-h-12 min-w-12 rounded bg-slate-800 text-xl font-bold" @click="increaseQuantity(line.id)">+</button>
                            </div>
                        </div>
                    </div>

                    <textarea v-model="kitchenNote" class="mt-4 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 p-3" placeholder="Kitchen note"></textarea>

                    <div class="mt-4 flex items-center justify-between text-xl font-bold">
                        <span>Total</span>
                        <span>@{{ formatMoney(cartTotal) }}</span>
                    </div>

                    <button type="button" class="mt-4 min-h-14 w-full rounded-lg bg-cyan-400 px-5 py-3 text-lg font-bold text-slate-950 disabled:opacity-50" :disabled="sending || cart.length === 0" @click="sendToKitchen(false)">@{{ sending ? 'Sending...' : 'Send to kitchen' }}</button>
                    <button type="button" class="mt-3 min-h-14 w-full rounded-lg border border-slate-700 px-5 py-3 text-lg font-bold" @click="saveHold">Save / hold</button>
                    <button type="button" class="mt-3 min-h-14 w-full rounded-lg bg-amber-400 px-5 py-3 text-lg font-bold text-slate-950 disabled:opacity-50" :disabled="sending || !canAddon" @click="sendToKitchen(true)">@{{ sending ? 'Sending...' : 'Send add-on order' }}</button>
                </aside>
            </div>
        </div>
    </main>
    @include('partials.internal-chat')
</body>
</html>
