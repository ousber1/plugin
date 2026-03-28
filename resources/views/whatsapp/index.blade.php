@extends('layouts.app')
@section('title', 'WhatsApp')

@section('content')
<div x-data="whatsappChat()" class="flex gap-4" style="height: calc(100vh - 120px);">
    {{-- Conversations List --}}
    <div class="w-80 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col shrink-0">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-sm"><i class="fab fa-whatsapp text-emerald-500 mr-1"></i> Chats</h3>
                <div class="flex gap-2">
                    <a href="{{ route('whatsapp.contacts') }}" class="text-xs text-primary-500 hover:underline">Contacts</a>
                    <a href="{{ route('whatsapp.automation') }}" class="text-xs text-primary-500 hover:underline">Auto</a>
                </div>
            </div>
            <input type="text" x-model="searchConvo" placeholder="Search conversations..." class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        </div>
        <div class="flex-1 overflow-y-auto">
            <template x-for="convo in filteredConversations" :key="convo.id">
                <button @click="selectConversation(convo)" :class="activeConvo?.id === convo.id ? 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-primary-500' : ''"
                        class="w-full px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-700/30 flex items-center gap-3 border-b border-slate-100 dark:border-slate-700">
                    <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center shrink-0">
                        <i class="fab fa-whatsapp text-emerald-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate" x-text="convo.contact_name || convo.phone"></p>
                        <p class="text-xs text-slate-400 truncate" x-text="convo.last_message || 'No messages'"></p>
                    </div>
                    <span class="text-[10px] text-slate-400" x-text="convo.last_time"></span>
                </button>
            </template>
            <div x-show="conversations.length === 0" class="p-8 text-center text-slate-400 text-sm">No conversations yet</div>
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex-1 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col">
        <template x-if="activeConvo">
            <div class="flex flex-col h-full">
                {{-- Chat Header --}}
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center">
                            <i class="fab fa-whatsapp text-emerald-600"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" x-text="activeConvo.contact_name || 'Unknown'"></p>
                            <p class="text-xs text-slate-400" x-text="activeConvo.phone"></p>
                        </div>
                    </div>
                </div>

                {{-- Messages --}}
                <div class="flex-1 overflow-y-auto p-6 space-y-3" id="messagesContainer">
                    <template x-for="msg in messages" :key="msg.id">
                        <div :class="msg.direction === 'outbound' ? 'flex justify-end' : 'flex justify-start'">
                            <div :class="msg.direction === 'outbound' ? 'bg-emerald-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200'"
                                 class="max-w-[70%] rounded-2xl px-4 py-2.5 shadow-sm">
                                <p class="text-sm whitespace-pre-wrap" x-text="msg.content"></p>
                                <p class="text-[10px] mt-1 opacity-70" x-text="msg.time"></p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Message Input --}}
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
                    <form @submit.prevent="sendMessage()" class="flex gap-3">
                        <input type="text" x-model="newMessage" placeholder="Type a message..." class="flex-1 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm bg-transparent focus:ring-2 focus:ring-emerald-500 outline-none">
                        <button type="submit" :disabled="!newMessage.trim()" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-medium hover:bg-emerald-700 disabled:opacity-50">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </template>

        <template x-if="!activeConvo">
            <div class="flex-1 flex items-center justify-center text-slate-400">
                <div class="text-center">
                    <i class="fab fa-whatsapp text-6xl mb-4 text-slate-200 dark:text-slate-600"></i>
                    <p class="text-lg font-medium">Select a conversation</p>
                    <p class="text-sm">Choose a chat from the left panel</p>
                </div>
            </div>
        </template>
    </div>

    {{-- Broadcast Panel --}}
    <div class="w-72 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shrink-0 hidden xl:block">
        <h3 class="text-sm font-semibold mb-3"><i class="fas fa-broadcast-tower text-primary-500 mr-1"></i> Quick Broadcast</h3>
        <form method="POST" action="{{ route('whatsapp.broadcast') }}" class="space-y-3">
            @csrf
            <textarea name="message" rows="4" placeholder="Broadcast message..." class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></textarea>
            <select name="contacts[]" multiple class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent h-24">
                @foreach($contacts ?? [] as $contact)
                <option value="{{ $contact->id }}">{{ $contact->name ?? $contact->phone }}</option>
                @endforeach
            </select>
            <button type="submit" class="w-full py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
                <i class="fas fa-paper-plane mr-1"></i> Send Broadcast
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function whatsappChat() {
    return {
        conversations: [],
        messages: [],
        activeConvo: null,
        newMessage: '',
        searchConvo: '',

        get filteredConversations() {
            if (!this.searchConvo) return this.conversations;
            return this.conversations.filter(c => (c.contact_name || c.phone || '').toLowerCase().includes(this.searchConvo.toLowerCase()));
        },

        async init() {
            const res = await fetch('{{ route("whatsapp.conversations") }}');
            this.conversations = await res.json();
        },

        async selectConversation(convo) {
            this.activeConvo = convo;
            const res = await fetch(`/whatsapp/messages/${convo.id}`);
            this.messages = await res.json();
            this.$nextTick(() => {
                const container = document.getElementById('messagesContainer');
                if (container) container.scrollTop = container.scrollHeight;
            });
        },

        async sendMessage() {
            if (!this.newMessage.trim() || !this.activeConvo) return;
            const msg = this.newMessage;
            this.newMessage = '';
            try {
                const res = await fetch('{{ route("whatsapp.send") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ conversation_id: this.activeConvo.id, message: msg })
                });
                const data = await res.json();
                if (data.success) {
                    this.messages.push({ id: Date.now(), direction: 'outbound', content: msg, time: 'Just now' });
                }
            } catch (e) { console.error(e); }
        }
    };
}
</script>
@endpush
