<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kitchen Display</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-black text-white antialiased">
    <main
        id="kitchen-display"
        data-component="kitchen-display"
        data-data-url="{{ route('kitchen.data') }}"
        data-status-url-template="{{ url('/kitchen/tickets/__TICKET__/status') }}"
        data-tenant-id="{{ current_tenant()->id }}"
        data-branch-id="{{ current_branch()?->id }}"
        class="min-h-screen"
    >
        <div class="px-4 py-5">
            <nav class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border-2 border-slate-700 bg-slate-950 p-3">
                <div class="flex flex-wrap gap-2 text-sm font-black">
                    <a href="{{ auth()->user()->roleRedirectPath() }}" class="rounded bg-slate-800 px-4 py-2 text-white">Home</a>
                    @can('access-waiter-pos')
                        <a href="{{ route('areas.waiter') }}" class="rounded bg-slate-800 px-4 py-2 text-white">Waiter POS</a>
                    @endcan
                    @can('access-counter-screen')
                        <a href="{{ route('areas.counter') }}" class="rounded bg-slate-800 px-4 py-2 text-white">Counter</a>
                    @endcan
                    <a href="{{ route('areas.kitchen') }}" class="rounded bg-yellow-300 px-4 py-2 text-black">Kitchen</a>
                    @can('access-tenant-admin-area')
                        <a href="{{ route('areas.tenant-admin') }}" class="rounded bg-slate-800 px-4 py-2 text-white">Admin</a>
                    @endcan
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded border-2 border-white px-4 py-2 text-sm font-black text-white" type="submit">Log out</button>
                </form>
            </nav>

            <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-black uppercase tracking-widest text-yellow-300">Kitchen Display</p>
                    <h1 class="text-4xl font-black">Live tickets</h1>
                </div>
                <button type="button" class="min-h-14 rounded-lg border-2 border-white px-6 py-3 text-xl font-black disabled:opacity-50" :disabled="loading" @click="loadTickets">
                    @{{ loading ? 'Refreshing...' : 'Refresh' }}
                </button>
            </header>

            <div v-if="loading" class="mb-4 rounded-lg border-2 border-slate-600 bg-slate-950 px-5 py-4 text-xl font-black text-slate-200">Loading kitchen tickets...</div>
            <div v-if="error" class="mb-4 rounded-lg border-2 border-red-500 bg-red-950 px-4 py-3 text-lg font-bold text-red-100">@{{ error }}</div>

            <section class="grid gap-4 lg:grid-cols-3">
                <div v-for="column in columns" :key="column.status" class="min-h-[70vh] rounded-lg border-2 border-slate-700 bg-slate-950 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-3xl font-black">@{{ column.label }}</h2>
                        <span class="rounded-full bg-white px-4 py-2 text-xl font-black text-black">@{{ ticketsFor(column.status).length }}</span>
                    </div>

                    <div v-if="!loading && ticketsFor(column.status).length === 0" class="rounded-lg border-2 border-dashed border-slate-700 bg-black p-8 text-center text-2xl font-black text-slate-500">
                        No tickets
                    </div>
                    <div class="space-y-4">
                        <article
                            v-for="ticket in ticketsFor(column.status)"
                            :key="ticket.id"
                            class="rounded-lg border-2 border-white bg-slate-900 p-5 shadow-lg"
                            data-kitchen-ticket-card
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-4xl font-black">@{{ ticket.order.order_number }}</p>
                                    <p class="mt-1 text-2xl font-bold text-yellow-200">@{{ sourceLabel(ticket.order) }}</p>
                                </div>
                                <div class="text-right">
                                    <span v-if="ticket.is_addon" class="inline-flex rounded bg-amber-300 px-3 py-1 text-base font-black text-black">ADD-ON</span>
                                    <p class="mt-2 text-2xl font-black">@{{ elapsed(ticket.created_at) }}</p>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-2 text-xl font-bold sm:grid-cols-2">
                                <p>Waiter: @{{ ticket.order.waiter_name || 'Unknown' }}</p>
                                <p>Ordered: @{{ ticket.ordered_time }}</p>
                            </div>

                            <ul class="mt-4 space-y-2">
                                <li v-for="item in ticket.order.items" :key="item.name + item.quantity + item.note" class="rounded bg-black p-3">
                                    <div class="flex justify-between gap-3 text-2xl font-black">
                                        <span>@{{ item.name }}</span>
                                        <span>x@{{ item.quantity }}</span>
                                    </div>
                                    <p v-if="item.note" class="mt-1 text-base font-bold text-yellow-200">@{{ item.note }}</p>
                                </li>
                            </ul>

                            <p v-if="ticket.kitchen_note" class="mt-4 rounded border border-yellow-300 bg-yellow-950 p-3 text-lg font-bold text-yellow-100">
                                Note: @{{ ticket.kitchen_note }}
                            </p>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                <button v-if="ticket.status === 'pending'" type="button" class="min-h-16 rounded-lg bg-orange-400 px-4 py-3 text-xl font-black text-black disabled:opacity-50" :disabled="updatingTicketId === ticket.id" @click="updateTicketStatus(ticket, 'cooking')">Start Cooking</button>
                                <button v-if="ticket.status !== 'ready'" type="button" class="min-h-16 rounded-lg bg-lime-400 px-4 py-3 text-xl font-black text-black disabled:opacity-50" :disabled="updatingTicketId === ticket.id" @click="updateTicketStatus(ticket, 'ready')">Mark Ready</button>
                                <button v-if="ticket.status !== 'pending'" type="button" class="min-h-16 rounded-lg border-2 border-white px-4 py-3 text-xl font-black disabled:opacity-50" :disabled="updatingTicketId === ticket.id" @click="updateTicketStatus(ticket, 'pending')">Recall</button>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </main>
    @include('partials.internal-chat')
</body>
</html>
