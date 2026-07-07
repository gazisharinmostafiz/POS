@extends('layouts.tenant-app', [
    'pageTitle' => 'Kitchen Display',
    'activeNav' => 'kitchen',
    'bodyClass' => 'pos-theme-kitchen',
])

@section('content')
<div
    id="kitchen-display"
    data-component="kitchen-display"
    data-data-url="{{ route('kitchen.data') }}"
    data-status-url-template="{{ url('/kitchen/tickets/__TICKET__/status') }}"
    data-tenant-id="{{ current_tenant()->id }}"
    data-branch-id="{{ current_branch()?->id }}"
    class="pos-page min-h-screen"
>
    <header class="pos-page-header">
        <div>
            <p class="pos-page-eyebrow">Kitchen Display System</p>
            <h1 class="pos-page-title text-4xl">Live tickets</h1>
            <p class="pos-page-subtitle">Bump orders as they progress through prep.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="pos-live-badge">
                <span class="pos-live-dot" :class="realtimeDotClass"></span>
                @{{ realtimeLabel }}
            </div>
            <button type="button" class="pos-btn-accent pos-btn-touch" :disabled="loading" @click="loadTickets">@{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
        </div>
    </header>

    <div v-if="loading && !hasTickets" class="pos-alert-info mb-4 text-lg">Loading kitchen tickets…</div>
    <div v-if="error" class="pos-alert-error mb-4 text-lg">@{{ error }}</div>

    <section class="pos-kitchen-board">
        <div v-for="column in columns" :key="column.status" class="pos-kitchen-column">
            <div class="pos-kitchen-column-header">
                <h2 class="text-2xl font-bold uppercase tracking-wide">@{{ column.label }}</h2>
                <span class="flex h-10 min-w-10 items-center justify-center rounded-full bg-accent-500 px-3 text-lg font-bold text-white">@{{ ticketsFor(column.status).length }}</span>
            </div>

            <div v-if="!loading && ticketsFor(column.status).length === 0" class="flex flex-1 items-center justify-center rounded-xl border-2 border-dashed border-slate-800 p-8 text-center text-lg font-semibold text-slate-500">
                No tickets
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto pos-scroll pr-1">
                <article
                    v-for="ticket in ticketsFor(column.status)"
                    :key="ticket.id"
                    class="pos-kitchen-ticket"
                    :class="isUrgent(ticket) ? 'pos-kitchen-ticket-urgent' : ''"
                    data-kitchen-ticket-card
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-3xl font-black tracking-tight">@{{ ticket.order.order_number }}</p>
                            <p class="mt-1 text-lg font-semibold text-accent-300">@{{ sourceLabel(ticket.order) }}</p>
                        </div>
                        <div class="text-right">
                            <span v-if="ticket.is_addon" class="pos-badge-warning">ADD-ON</span>
                            <p class="mt-2 font-mono text-2xl font-bold text-white">@{{ elapsed(ticket.created_at) }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-4 text-sm font-medium text-slate-400">
                        <span>Waiter: @{{ ticket.order.waiter_name || '—' }}</span>
                        <span>@{{ ticket.ordered_time }}</span>
                    </div>

                    <ul class="mt-4 space-y-2">
                        <li v-for="item in ticket.order.items" :key="item.name + item.quantity + item.note" class="pos-kitchen-item">
                            <div class="flex justify-between gap-3 text-xl font-bold">
                                <span>@{{ item.name }}</span>
                                <span class="text-accent-400">×@{{ item.quantity }}</span>
                            </div>
                            <p v-if="item.note" class="mt-1 text-sm font-semibold text-accent-200">@{{ item.note }}</p>
                        </li>
                    </ul>

                    <p v-if="ticket.kitchen_note" class="mt-4 rounded-xl border border-accent-500/40 bg-accent-950/30 p-3 text-sm font-semibold text-accent-100">
                        @{{ ticket.kitchen_note }}
                    </p>

                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <button v-if="ticket.status === 'pending'" type="button" class="pos-btn-accent pos-btn-touch-xl col-span-full" :disabled="updatingTicketId === ticket.id" @click="updateTicketStatus(ticket, 'cooking')">Start cooking</button>
                        <button v-if="ticket.status !== 'ready'" type="button" class="pos-btn-success pos-btn-touch-xl" :disabled="updatingTicketId === ticket.id" @click="updateTicketStatus(ticket, 'ready')">Mark ready</button>
                        <button v-if="ticket.status !== 'pending'" type="button" class="pos-btn-secondary pos-btn-touch" :disabled="updatingTicketId === ticket.id" @click="updateTicketStatus(ticket, 'pending')">Recall</button>
                    </div>
                </article>
            </div>
        </div>
    </section>
</div>
@endsection

@push('after-body')
    @include('partials.internal-chat')
@endpush
