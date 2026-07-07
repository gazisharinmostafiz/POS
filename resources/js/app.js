import './bootstrap';
import { createApp } from 'vue';

window.TongPosConfig = {
    apiBaseUrl: import.meta.env.VITE_API_BASE_URL || window.location.origin,
};

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const message = form.dataset.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
        return;
    }

    const submitter = event.submitter;

    if (submitter instanceof HTMLButtonElement && form.dataset.loadingText) {
        submitter.disabled = true;
        submitter.textContent = form.dataset.loadingText;
    }
});

const isLocalHost = ['localhost', '127.0.0.1', '[::1]', '::1'].includes(window.location.hostname);

if ('serviceWorker' in navigator && isLocalHost) {
    navigator.serviceWorker.getRegistrations()
        .then((registrations) => registrations.forEach((registration) => registration.unregister()))
        .catch(() => {});
}

if ('serviceWorker' in navigator && import.meta.env.PROD && !isLocalHost) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

const realtimeEvents = [
    '.OrderCreated',
    '.OrderUpdated',
    '.KitchenTicketCreated',
    '.KitchenTicketStatusChanged',
    '.PaymentCompleted',
    '.TableStatusChanged',
    '.ChatMessageSent',
];

function subscribeTenantRealtime(element, refreshCallback) {
    const tenantId = element?.dataset.tenantId;

    if (!tenantId || typeof refreshCallback !== 'function') {
        return { unsubscribe: null, mode: 'offline' };
    }

    if (window.Echo) {
        const channel = window.Echo.private(`tenant.${tenantId}`);

        realtimeEvents.forEach((eventName) => {
            channel.listen(eventName, refreshCallback);
        });

        return {
            mode: 'live',
            unsubscribe: () => window.Echo.leave(`tenant.${tenantId}`),
        };
    }

    const interval = window.setInterval(refreshCallback, 10000);

    return {
        mode: 'polling',
        unsubscribe: () => window.clearInterval(interval),
    };
}

const realtimeMixin = {
    data() {
        return {
            realtimeMode: 'offline',
            unsubscribeRealtime: null,
        };
    },

    computed: {
        realtimeDotClass() {
            if (this.realtimeMode === 'live') {
                return 'pos-live-dot';
            }

            if (this.realtimeMode === 'polling') {
                return 'pos-live-dot pos-live-dot-polling';
            }

            return 'pos-live-dot pos-live-dot-offline';
        },

        realtimeLabel() {
            if (this.realtimeMode === 'live') {
                return 'Live sync';
            }

            if (this.realtimeMode === 'polling') {
                return 'Auto-refresh';
            }

            return 'Offline';
        },
    },

    beforeUnmount() {
        if (this.unsubscribeRealtime) {
            this.unsubscribeRealtime();
        }
    },
};

const waiterPosEl = document.getElementById('waiter-pos');

if (waiterPosEl?.dataset.component === 'waiter-pos') {
    createApp({
        mixins: [realtimeMixin],

        data() {
            return {
                categories: [],
                menuItems: [],
                tables: [],
                sourceTypes: {
                    table: 'table',
                    takeaway: 'takeaway',
                    walkIn: 'walk_in',
                },
                sourceType: 'table',
                tableNumber: null,
                selectedCategoryId: null,
                search: '',
                cart: [],
                kitchenNote: '',
                activeTableOrder: null,
                currencySymbol: '£',
                notice: '',
                error: '',
                loading: true,
                sending: false,
            };
        },

        computed: {
            filteredMenuItems() {
                const searchTerm = this.search.trim().toLowerCase();

                return this.menuItems.filter((item) => {
                    const matchesCategory = this.selectedCategoryId === null
                        || item.category_id === this.selectedCategoryId;
                    const matchesSearch = !searchTerm
                        || item.name.toLowerCase().includes(searchTerm)
                        || (item.description || '').toLowerCase().includes(searchTerm);

                    return matchesCategory && matchesSearch;
                });
            },

            cartTotal() {
                return this.cart.reduce((total, line) => total + (Number(line.price) * line.quantity), 0);
            },

            sourceLabel() {
                if (this.sourceType === this.sourceTypes.takeaway) {
                    return 'Takeaway';
                }

                if (this.sourceType === this.sourceTypes.walkIn) {
                    return 'Walk-in';
                }

                return this.tableNumber ? `Table ${this.tableNumber}` : 'Select table';
            },

            selectedTable() {
                return this.tables.find((table) => table.number === this.tableNumber) || null;
            },

            canAddon() {
                if (this.sourceType !== this.sourceTypes.table || !this.tableNumber) {
                    return false;
                }

                const activeStatuses = ['occupied', 'order_sent', 'cooking', 'ready', 'pending_bill'];

                return Boolean(this.activeTableOrder)
                    || activeStatuses.includes(this.selectedTable?.status);
            },
        },

        mounted() {
            this.loadData();
            const subscription = subscribeTenantRealtime(waiterPosEl, () => this.loadData());
            this.realtimeMode = subscription.mode;
            this.unsubscribeRealtime = subscription.unsubscribe;
        },

        methods: {
            async loadData() {
                this.error = '';
                this.loading = true;

                try {
                    const response = await window.axios.get(waiterPosEl.dataset.dataUrl);
                    this.categories = response.data.categories || [];
                    this.menuItems = response.data.menu_items || [];
                    this.tables = response.data.tables || [];
                    this.sourceTypes = response.data.source_types || this.sourceTypes;
                    this.currencySymbol = response.data.currency_symbol || this.currencySymbol;

                    if (!this.tableNumber && this.tables.length > 0) {
                        await this.selectTable(this.tables[0]);
                    } else if (this.tables.length === 0) {
                        this.selectSource(this.sourceTypes.takeaway);
                    }
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to load waiter POS data.';
                } finally {
                    this.loading = false;
                }
            },

            selectSource(sourceType) {
                this.sourceType = sourceType;
                this.activeTableOrder = null;

                if (sourceType !== this.sourceTypes.table) {
                    this.tableNumber = null;
                }
            },

            async selectTable(table) {
                this.sourceType = this.sourceTypes.table;
                this.tableNumber = table.number;
                this.activeTableOrder = null;

                await this.loadActiveTableOrder(table.number);
            },

            async loadActiveTableOrder(tableNumber) {
                const template = waiterPosEl.dataset.activeOrderUrlTemplate;

                if (!template) {
                    return;
                }

                try {
                    const url = template.replace('__TABLE__', tableNumber);
                    const response = await window.axios.get(url);
                    this.activeTableOrder = response.data.order;
                } catch (error) {
                    this.activeTableOrder = null;
                }
            },

            statusBadgeClass(status) {
                const classes = {
                    free: 'pos-badge-success',
                    occupied: 'pos-badge-warning',
                    order_sent: 'pos-badge-neutral',
                    cooking: 'pos-badge-warning',
                    ready: 'pos-badge-success',
                    pending_bill: 'pos-badge-danger',
                    paid: 'pos-badge-neutral',
                };

                return classes[status] || 'pos-badge-neutral';
            },

            formatMoney(value) {
                return `${this.currencySymbol}${Number(value || 0).toFixed(2)}`;
            },

            addToCart(item) {
                const existingLine = this.cart.find((line) => line.id === item.id);

                if (existingLine) {
                    existingLine.quantity += 1;
                    return;
                }

                this.cart.push({
                    id: item.id,
                    name: item.name,
                    price: Number(item.price),
                    quantity: 1,
                });
            },

            increaseQuantity(itemId) {
                const line = this.cart.find((cartLine) => cartLine.id === itemId);

                if (line) {
                    line.quantity += 1;
                }
            },

            decreaseQuantity(itemId) {
                const line = this.cart.find((cartLine) => cartLine.id === itemId);

                if (!line) {
                    return;
                }

                if (line.quantity === 1) {
                    this.removeFromCart(itemId);
                    return;
                }

                line.quantity -= 1;
            },

            removeFromCart(itemId) {
                this.cart = this.cart.filter((line) => line.id !== itemId);
            },

            async sendToKitchen(isAddon) {
                this.notice = '';
                this.error = '';
                this.sending = true;

                if (this.cart.length === 0) {
                    this.error = 'Add at least one item before sending to kitchen.';
                    this.sending = false;
                    return;
                }

                if (this.sourceType === this.sourceTypes.table && !this.tableNumber) {
                    this.error = 'Select a table for table orders.';
                    this.sending = false;
                    return;
                }

                if (isAddon && !this.canAddon) {
                    this.error = 'Add-on orders require an existing active table order.';
                    this.sending = false;
                    return;
                }

                const payload = {
                    source_type: this.sourceType,
                    table_number: this.sourceType === this.sourceTypes.table ? this.tableNumber : null,
                    kitchen_note: this.kitchenNote,
                    items: this.cart.map((line) => ({
                        menu_item_id: line.id,
                        quantity: line.quantity,
                    })),
                };

                try {
                    const url = isAddon ? waiterPosEl.dataset.addonUrl : waiterPosEl.dataset.orderUrl;
                    const response = await window.axios.post(url, payload);
                    this.notice = `${isAddon ? 'Add-on order' : 'Order'} ${response.data.order.order_number} sent to kitchen.`;
                    this.cart = [];
                    this.kitchenNote = '';
                    await this.loadData();
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to send order to kitchen.';
                } finally {
                    this.sending = false;
                }
            },

            saveHold() {
                this.notice = 'Order held locally. Persistent holds will be added in a later phase.';
                this.error = '';
            },
        },
    }).mount(waiterPosEl);
}

const kitchenDisplayEl = document.getElementById('kitchen-display');

if (kitchenDisplayEl?.dataset.component === 'kitchen-display') {
    createApp({
        mixins: [realtimeMixin],

        data() {
            return {
                groupedTickets: {
                    pending: [],
                    cooking: [],
                    ready: [],
                },
                columns: [
                    { status: 'pending', label: 'New' },
                    { status: 'cooking', label: 'Cooking' },
                    { status: 'ready', label: 'Ready' },
                ],
                now: Date.now(),
                timerHandle: null,
                loading: true,
                updatingTicketId: null,
                error: '',
            };
        },

        computed: {
            hasTickets() {
                return ['pending', 'cooking', 'ready'].some(
                    (status) => (this.groupedTickets[status] || []).length > 0,
                );
            },
        },

        mounted() {
            this.loadTickets();
            this.timerHandle = window.setInterval(() => {
                this.now = Date.now();
            }, 1000);
            const subscription = subscribeTenantRealtime(kitchenDisplayEl, () => this.loadTickets());
            this.realtimeMode = subscription.mode;
            this.unsubscribeRealtime = subscription.unsubscribe;
        },

        beforeUnmount() {
            if (this.timerHandle) {
                window.clearInterval(this.timerHandle);
            }
        },

        methods: {
            async loadTickets() {
                this.error = '';
                this.loading = true;

                try {
                    const response = await window.axios.get(kitchenDisplayEl.dataset.dataUrl);
                    this.groupedTickets = response.data.columns || this.groupedTickets;
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to load kitchen tickets.';
                } finally {
                    this.loading = false;
                }
            },

            ticketsFor(status) {
                return this.groupedTickets[status] || [];
            },

            isUrgent(ticket) {
                if (!ticket.created_at) {
                    return false;
                }

                const minutes = (this.now - Date.parse(ticket.created_at)) / 60000;

                return minutes >= 15 && ticket.status !== 'ready';
            },

            sourceLabel(order) {
                if (order.source_type === 'table') {
                    return `Table ${order.table_number}`;
                }

                if (order.source_type === 'takeaway') {
                    return 'Takeaway';
                }

                return 'Walk-in';
            },

            elapsed(createdAt) {
                if (!createdAt) {
                    return '0:00';
                }

                const seconds = Math.max(0, Math.floor((this.now - Date.parse(createdAt)) / 1000));
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;

                if (minutes < 60) {
                    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
                }

                const hours = Math.floor(minutes / 60);
                const remainingMinutes = minutes % 60;

                return `${hours}h ${remainingMinutes}m`;
            },

            async updateTicketStatus(ticket, status) {
                this.error = '';
                this.updatingTicketId = ticket.id;

                try {
                    const url = kitchenDisplayEl.dataset.statusUrlTemplate.replace('__TICKET__', ticket.id);
                    await window.axios.patch(url, { status });
                    await this.loadTickets();
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to update kitchen ticket.';
                } finally {
                    this.updatingTicketId = null;
                }
            },
        },
    }).mount(kitchenDisplayEl);
}

const counterBillingEl = document.getElementById('counter-billing');

if (counterBillingEl?.dataset.component === 'counter-billing') {
    createApp({
        mixins: [realtimeMixin],

        data() {
            return {
                orders: [],
                bill: null,
                selectedOrderId: null,
                activeFilter: 'all',
                currencySymbol: '£',
                discount: {
                    type: 'flat',
                    value: null,
                    reason: '',
                },
                split: {
                    peopleCount: null,
                    result: null,
                },
                payment: {
                    cashAmount: null,
                    cardAmount: null,
                    result: null,
                },
                loading: true,
                processing: false,
                filters: [
                    { key: 'all', label: 'All' },
                    { key: 'unpaid', label: 'Unpaid' },
                    { key: 'ready', label: 'Ready' },
                    { key: 'paid', label: 'Paid' },
                    { key: 'table', label: 'Table' },
                    { key: 'takeaway', label: 'Takeaway' },
                    { key: 'walk_in', label: 'Walk-in' },
                ],
                error: '',
            };
        },

        computed: {
            filteredOrders() {
                return this.orders.filter((order) => {
                    if (this.activeFilter === 'all') {
                        return true;
                    }

                    if (this.activeFilter === 'unpaid') {
                        return order.payment_status === 'unpaid';
                    }

                    if (this.activeFilter === 'paid') {
                        return order.payment_status === 'paid';
                    }

                    if (this.activeFilter === 'ready') {
                        return order.order_status === 'ready' || order.kitchen_status === 'ready';
                    }

                    return order.source_type === this.activeFilter;
                });
            },

            billTitle() {
                if (!this.bill) {
                    return '';
                }

                if (this.bill.source_type === 'table') {
                    return `Table ${this.bill.table_number}`;
                }

                return this.bill.source_type === 'takeaway' ? 'Takeaway' : 'Walk-in';
            },
        },

        mounted() {
            this.loadOrders();
            const subscription = subscribeTenantRealtime(counterBillingEl, () => this.loadOrders());
            this.realtimeMode = subscription.mode;
            this.unsubscribeRealtime = subscription.unsubscribe;
        },

        methods: {
            async loadOrders() {
                this.error = '';
                this.loading = true;

                try {
                    const response = await window.axios.get(counterBillingEl.dataset.dataUrl);
                    this.orders = response.data.orders || [];
                    this.currencySymbol = response.data.settings?.currency_symbol || this.currencySymbol;

                    if (this.selectedOrderId) {
                        const selectedStillExists = this.orders.some((order) => order.id === this.selectedOrderId);

                        if (selectedStillExists) {
                            await this.loadBill(this.selectedOrderId);
                        }
                    }
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to load counter orders.';
                } finally {
                    this.loading = false;
                }
            },

            async selectOrder(order) {
                this.selectedOrderId = order.id;
                await this.loadBill(order.id);
            },

            async loadBill(orderId) {
                this.error = '';

                try {
                    const url = counterBillingEl.dataset.billUrlTemplate.replace('__ORDER__', orderId);
                    const response = await window.axios.get(url);
                    this.bill = response.data.bill;
                    this.currencySymbol = this.bill.currency_symbol || this.currencySymbol;
                    this.split.result = null;
                    this.payment.result = null;
                } catch (error) {
                    this.bill = null;
                    this.error = error.response?.data?.message || 'Unable to load bill.';
                }
            },

            sourceLabel(order) {
                if (order.source_type === 'table') {
                    return `Table ${order.table_number}`;
                }

                if (order.source_type === 'takeaway') {
                    return 'Takeaway';
                }

                return 'Walk-in';
            },

            formatMoney(value) {
                return `${this.currencySymbol}${Number(value || 0).toFixed(2)}`;
            },

            async applyDiscount() {
                if (!this.bill) {
                    return;
                }

                this.error = '';
                this.processing = true;

                try {
                    const url = `${counterBillingEl.dataset.billUrlTemplate.replace('__ORDER__', this.bill.selected_order_id)}/discount`;
                    const response = await window.axios.post(url, {
                        type: this.discount.type,
                        value: this.discount.value || 0,
                        reason: this.discount.reason || null,
                    });
                    this.bill = response.data.bill;
                    await this.loadOrders();
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to apply discount.';
                } finally {
                    this.processing = false;
                }
            },

            async calculateSplit() {
                if (!this.bill) {
                    return;
                }

                this.error = '';
                this.processing = true;

                try {
                    const url = `${counterBillingEl.dataset.billUrlTemplate.replace('__ORDER__', this.bill.selected_order_id)}/split`;
                    const response = await window.axios.post(url, {
                        people_count: this.split.peopleCount || 1,
                    });
                    this.split.result = response.data.split_bill;
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to split bill.';
                } finally {
                    this.processing = false;
                }
            },

            async recordPayment() {
                if (!this.bill) {
                    return;
                }

                this.error = '';
                this.processing = true;

                try {
                    const url = `${counterBillingEl.dataset.billUrlTemplate.replace('__ORDER__', this.bill.selected_order_id)}/payment`;
                    const response = await window.axios.post(url, {
                        cash_amount: this.payment.cashAmount || 0,
                        card_amount: this.payment.cardAmount || 0,
                    });
                    this.payment.result = response.data.payment;
                    await this.loadOrders();
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to record payment.';
                } finally {
                    this.processing = false;
                }
            },
        },
    }).mount(counterBillingEl);
}

const tenantDashboardEl = document.getElementById('tenant-dashboard');

if (tenantDashboardEl?.dataset.component === 'tenant-dashboard') {
    createApp({
        mixins: [realtimeMixin],

        mounted() {
            const subscription = subscribeTenantRealtime(tenantDashboardEl, () => {
                // Dashboard tables are server-rendered; live badge reflects sync readiness.
                // Full page refresh on major events keeps analytics accurate without heavy client state.
            });
            this.realtimeMode = subscription.mode;
            this.unsubscribeRealtime = subscription.unsubscribe;
        },
    }).mount(tenantDashboardEl);
}

const internalChatEl = document.getElementById('internal-chat');

if (internalChatEl?.dataset.component === 'internal-chat') {
    createApp({
        mixins: [realtimeMixin],

        data() {
            return {
                open: false,
                rooms: [],
                activeRoom: null,
                messages: [],
                newMessage: '',
                error: '',
            };
        },

        computed: {
            totalUnread() {
                return this.rooms.reduce((total, room) => total + Number(room.unread_count || 0), 0);
            },
        },

        mounted() {
            this.loadRooms();
            const subscription = subscribeTenantRealtime(internalChatEl, (payload) => {
                if (!payload) {
                    this.loadRooms();
                    return;
                }

                if (!payload?.room) {
                    return;
                }

                this.loadRooms();

                if (this.activeRoom === payload.room) {
                    this.loadMessages(payload.room);
                }
            });
            this.realtimeMode = subscription.mode;
            this.unsubscribeRealtime = subscription.unsubscribe;
        },

        methods: {
            async loadRooms() {
                try {
                    const response = await window.axios.get(internalChatEl.dataset.roomsUrl);
                    this.rooms = response.data.rooms || [];

                    if (!this.activeRoom && this.rooms.length > 0) {
                        await this.selectRoom(this.rooms[0].key);
                    }
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to load chat rooms.';
                }
            },

            async selectRoom(room) {
                this.activeRoom = room;
                await this.loadMessages(room);
                await this.loadRooms();
            },

            async loadMessages(room) {
                try {
                    const url = internalChatEl.dataset.messagesUrlTemplate.replace('__ROOM__', room);
                    const response = await window.axios.get(url);
                    this.messages = response.data.messages || [];
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to load chat messages.';
                }
            },

            async sendMessage() {
                if (!this.activeRoom || !this.newMessage.trim()) {
                    return;
                }

                try {
                    const url = internalChatEl.dataset.messagesUrlTemplate.replace('__ROOM__', this.activeRoom);
                    await window.axios.post(url, {
                        message: this.newMessage.trim(),
                    });
                    this.newMessage = '';
                    await this.loadMessages(this.activeRoom);
                    await this.loadRooms();
                } catch (error) {
                    this.error = error.response?.data?.message || 'Unable to send chat message.';
                }
            },

            formatChatTime(value) {
                if (!value) {
                    return '';
                }

                return new Intl.DateTimeFormat(undefined, {
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));
            },
        },
    }).mount(internalChatEl);
}
