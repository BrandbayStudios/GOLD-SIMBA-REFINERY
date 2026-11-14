<div class="card card-pad" x-data="jobChat({ url: '{{ route('messages.index', $sr) }}' })">
    <h2 class="flex items-center gap-2 font-display text-lg font-bold"><x-icon name="message" class="h-5 w-5 text-brand-500" /> Messages</h2>
    <div x-ref="box" class="mt-4 max-h-72 space-y-2 overflow-y-auto rounded-xl bg-mist p-3">
        <p x-show="!messages.length" class="py-4 text-center text-sm text-navy-300">No messages yet. Say hello 👋</p>
        <template x-for="m in messages" :key="m.id">
            <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                <div class="max-w-[80%] rounded-2xl px-3.5 py-2 text-sm" :class="m.mine ? 'bg-navy-900 text-white' : 'bg-white'">
                    <p x-text="m.body" class="whitespace-pre-line"></p>
                    <p class="mt-0.5 text-[10px] opacity-60" x-text="m.time"></p>
                </div>
            </div>
        </template>
    </div>
    <form @submit.prevent="send" class="mt-3 flex gap-2">
        <input x-model="body" class="input" placeholder="Type a message…" maxlength="1000" aria-label="Message">
        <button class="btn btn-primary" :disabled="sending">Send</button>
    </form>
</div>
