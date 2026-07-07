@extends('layouts.tenant-app', [
    'pageTitle' => 'Waiter POS',
    'activeNav' => 'waiter',
    'bodyClass' => 'pos-theme-dark',
])

@section('content')
<div
    id="waiter-pos"
    data-component="waiter-pos"
    data-data-url="{{ route('waiter.pos.data') }}"
    data-order-url="{{ route('waiter.pos.orders.store') }}"
    data-addon-url="{{ route('waiter.pos.orders.addon') }}"
    data-active-order-url-template="{{ url('/waiter/pos/tables/__TABLE__/active-order') }}"
    data-tenant-id="{{ current_tenant()->id }}"
    data-branch-id="{{ current_branch()?->id }}"
    class="pos-page min-h-screen"
>
    <header class="pos-page-header">
        <div>
            <p class="pos-page-eyebrow">Operations</p>
            <h1 class="pos-page-title">Waiter POS</h1>
            <p class="pos-page-subtitle">Take orders, manage tables, and send tickets to kitchen.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="pos-live-badge">
                <span class="pos-live-dot" :class="realtimeDotClass"></span>
                @{{ realtimeLabel }}
            </div>
            <span class="pos-badge-neutral">@{{ sourceLabel }}</span>
        </div>
    </header>

    <div v-if="loading" class="pos-alert-info mb-4">Loading tables and menu…</div>
    <div v-if="notice" class="pos-alert-success mb-4">@{{ notice }}</div>
    <div v-if="error" class="pos-alert-error mb-4">@{{ error }}</div>

    <div class="pos-waiter-layout">
        <aside class="pos-dark-card p-4 xl:sticky xl:top-6 xl:self-start">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">Order type</h2>
            <div class="mb-5 grid gap-2">
                <button type="button" class="pos-btn-touch w-full" :class="sourceType === sourceTypes.takeaway ? 'pos-btn-accent' : 'pos-btn-secondary'" @click="selectSource('takeaway')">Takeaway</button>
                <button type="button" class="pos-btn-touch w-full" :class="sourceType === sourceTypes.walkIn ? 'pos-btn-accent' : 'pos-btn-secondary'" @click="selectSource('walk_in')">Walk-in</button>
            </div>

            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">Tables</h2>
            <div v-if="!loading && tables.length === 0" class="pos-empty-dark text-xs">No tables configured.</div>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="table in tables"
                    :key="table.id"
                    type="button"
                    class="pos-table-btn"
                    :class="sourceType === 'table' && tableNumber === table.number ? 'pos-table-btn-active' : 'pos-table-btn-idle'"
                    @click="selectTable(table)"
                >
                    <span class="block text-base font-bold">@{{ table.label || ('T' + table.number) }}</span>
                    <span class="pos-badge mt-2" :class="statusBadgeClass(table.status)">@{{ table.status }}</span>
                </button>
            </div>
        </aside>

        <section class="pos-dark-card flex flex-col overflow-hidden">
            <div class="pos-card-header border-slate-800">
                <h2 class="font-bold text-white">Menu</h2>
                <input v-model="search" type="search" class="pos-field-dark max-w-xs" placeholder="Search items…">
            </div>
            <div class="pos-card-body flex-1 pos-scroll">
                <div class="mb-4 flex flex-wrap gap-2">
                    <button type="button" class="pos-btn-tab" :class="selectedCategoryId === null ? 'pos-btn-tab-active' : 'pos-btn-tab-inactive'" @click="selectedCategoryId = null">All</button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        class="pos-btn-tab"
                        :class="selectedCategoryId === category.id ? 'pos-btn-tab-active' : 'pos-btn-tab-inactive'"
                        @click="selectedCategoryId = category.id"
                    >@{{ category.name }}</button>
                </div>

                <div v-if="!loading && filteredMenuItems.length === 0" class="pos-empty-dark">No items match your search.</div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button v-for="item in filteredMenuItems" :key="item.id" type="button" class="group pos-menu-item" data-menu-card @click="addToCart(item)">
                        <span class="text-base font-bold text-white">@{{ item.name }}</span>
                        <span class="mt-1 line-clamp-2 text-sm text-slate-400">@{{ item.description || 'Tap to add' }}</span>
                        <span class="mt-auto flex items-center justify-between pt-4">
                            <span class="text-xl font-bold text-accent-400">@{{ formatMoney(item.price) }}</span>
                            <span class="pos-menu-item-add">+</span>
                        </span>
                    </button>
                </div>
            </div>
        </section>

        <aside class="pos-cart-panel xl:sticky xl:top-6 xl:self-start">
            <div class="pos-card-header border-slate-800">
                <div>
                    <h2 class="font-bold text-white">Current order</h2>
                    <p class="text-xs text-slate-400">@{{ cart.length }} item(s)</p>
                </div>
                <span class="text-2xl font-bold text-accent-400">@{{ formatMoney(cartTotal) }}</span>
            </div>
            <div class="pos-card-body flex flex-col pos-scroll max-h-[50vh] xl:max-h-[calc(100vh-20rem)]">
                <p v-if="cart.length === 0" class="pos-empty-dark text-xs">Tap menu items to build an order.</p>
                <div v-for="line in cart" :key="line.id" class="pos-cart-line mb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-white">@{{ line.name }}</p>
                            <p class="text-sm text-slate-400">@{{ formatMoney(line.price) }} each</p>
                        </div>
                        <button type="button" class="pos-btn-danger px-3 py-1.5 text-xs" @click="removeFromCart(line.id)">Remove</button>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-800 text-xl font-bold" @click="decreaseQuantity(line.id)">−</button>
                        <span class="min-w-8 text-center text-lg font-bold">@{{ line.quantity }}</span>
                        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-800 text-xl font-bold" @click="increaseQuantity(line.id)">+</button>
                        <span class="ml-auto font-bold text-white">@{{ formatMoney(line.price * line.quantity) }}</span>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-800 p-4">
                <textarea v-model="kitchenNote" class="pos-field-dark mb-4 min-h-20" placeholder="Kitchen note (optional)"></textarea>
                <button type="button" class="pos-btn-accent pos-btn-touch-xl mb-2 w-full" :disabled="sending || cart.length === 0" @click="sendToKitchen(false)">@{{ sending ? 'Sending…' : 'Send to kitchen' }}</button>
                <button type="button" class="pos-btn-secondary pos-btn-touch w-full mb-2" @click="saveHold">Hold order</button>
                <button type="button" class="pos-btn-secondary pos-btn-touch w-full" :disabled="sending || !canAddon" @click="sendToKitchen(true)">@{{ sending ? 'Sending…' : 'Send add-on' }}</button>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('after-body')
    @include('partials.internal-chat')
@endpush
