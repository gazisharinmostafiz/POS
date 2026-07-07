<div
    id="internal-chat"
    data-component="internal-chat"
    data-rooms-url="{{ route('tenant.chat.rooms') }}"
    data-messages-url-template="{{ url('/tenant/chat/rooms/__ROOM__/messages') }}"
    data-tenant-id="{{ current_tenant()->id }}"
    data-branch-id="{{ current_branch()?->id }}"
    class="fixed bottom-20 right-4 z-50 lg:bottom-6"
>
    <button type="button" class="flex min-h-touch items-center gap-2 rounded-full bg-brand-600 px-5 py-3 text-sm font-bold text-white shadow-card-lg transition hover:bg-brand-700" @click="open = !open">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Team chat
        <span v-if="totalUnread" class="rounded-full bg-accent-500 px-2 py-0.5 text-xs font-bold">@{{ totalUnread }}</span>
    </button>

    <section v-if="open" class="mt-3 grid h-[min(520px,70vh)] w-[min(92vw,42rem)] grid-cols-[12rem_1fr] overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 text-slate-100 shadow-card-xl">
        <aside class="border-r border-slate-800 p-3 pos-scroll overflow-y-auto">
            <h2 class="mb-3 px-2 text-xs font-bold uppercase tracking-wider text-slate-500">Rooms</h2>
            <button
                v-for="room in rooms"
                :key="room.key"
                type="button"
                class="mb-1 block w-full rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition"
                :class="activeRoom === room.key ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-900'"
                @click="selectRoom(room.key)"
            >
                @{{ room.label }}
                <span v-if="room.unread_count" class="mt-1 block text-xs text-accent-300">@{{ room.unread_count }} new</span>
            </button>
        </aside>

        <div class="flex min-h-0 flex-col">
            <div class="flex-1 space-y-3 overflow-y-auto p-4 pos-scroll">
                <p v-if="!activeRoom" class="text-sm text-slate-500">Select a room to start messaging.</p>
                <article v-for="message in messages" :key="message.id" class="rounded-xl bg-slate-900/80 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span class="font-semibold text-slate-300">@{{ message.sender_name }}</span>
                        <span>@{{ formatChatTime(message.timestamp) }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap text-sm">@{{ message.message }}</p>
                </article>
            </div>

            <form class="border-t border-slate-800 p-3" @submit.prevent="sendMessage">
                <textarea v-model="newMessage" class="pos-field-dark min-h-16 resize-none" placeholder="Message your team…"></textarea>
                <button type="submit" class="pos-btn-primary mt-2 w-full" :disabled="!activeRoom || !newMessage.trim()">Send</button>
            </form>
        </div>
    </section>
</div>
