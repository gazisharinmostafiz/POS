<div
    id="internal-chat"
    data-component="internal-chat"
    data-rooms-url="{{ route('tenant.chat.rooms') }}"
    data-messages-url-template="{{ url('/tenant/chat/rooms/__ROOM__/messages') }}"
    data-tenant-id="{{ current_tenant()->id }}"
    data-branch-id="{{ current_branch()?->id }}"
    class="fixed bottom-4 right-4 z-50"
>
    <button type="button" class="min-h-12 rounded-full bg-cyan-400 px-5 py-3 font-black text-slate-950 shadow-lg" @click="open = !open">
        Chat <span v-if="totalUnread" class="ml-2 rounded-full bg-red-600 px-2 py-1 text-xs text-white">@{{ totalUnread }}</span>
    </button>

    <section v-if="open" class="mt-3 grid h-[520px] w-[min(92vw,760px)] grid-cols-[220px_1fr] overflow-hidden rounded-lg border border-slate-700 bg-slate-950 text-slate-100 shadow-2xl">
        <aside class="border-r border-slate-800 p-3">
            <h2 class="mb-3 text-lg font-black">Internal chat</h2>
            <button
                v-for="room in rooms"
                :key="room.key"
                type="button"
                class="mb-2 block w-full rounded-lg px-3 py-3 text-left font-bold"
                :class="activeRoom === room.key ? 'bg-cyan-400 text-slate-950' : 'bg-slate-900 text-slate-100'"
                @click="selectRoom(room.key)"
            >
                <span class="block">@{{ room.label }}</span>
                <span v-if="room.unread_count" class="mt-1 inline-block rounded-full bg-red-600 px-2 py-1 text-xs text-white">@{{ room.unread_count }} unread</span>
            </button>
        </aside>

        <div class="flex min-h-0 flex-col">
            <div class="flex-1 space-y-3 overflow-y-auto p-4">
                <p v-if="!activeRoom" class="text-slate-400">Choose a room.</p>
                <article v-for="message in messages" :key="message.id" class="rounded-lg bg-slate-900 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-400">
                        <span class="font-bold text-slate-200">@{{ message.sender_name }} · @{{ message.sender_role }}</span>
                        <span>@{{ formatChatTime(message.timestamp) }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap text-base">@{{ message.message }}</p>
                    <p v-if="message.related_order_id" class="mt-1 text-sm font-bold text-cyan-300">Order #@{{ message.related_order_id }}</p>
                </article>
            </div>

            <form class="border-t border-slate-800 p-3" @submit.prevent="sendMessage">
                <textarea v-model="newMessage" class="min-h-20 w-full rounded-lg border border-slate-700 bg-slate-900 p-3" placeholder="Type a message"></textarea>
                <button type="submit" class="mt-2 min-h-11 w-full rounded-lg bg-cyan-400 px-4 py-2 font-black text-slate-950" :disabled="!activeRoom || !newMessage.trim()">Send message</button>
            </form>
        </div>
    </section>
</div>
