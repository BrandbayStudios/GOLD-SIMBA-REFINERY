@auth
<div x-data="notifBell({ url: '{{ route('notifications.count') }}', unread: {{ auth()->user()->unreadNotifications()->count() }}, interval: {{ auth()->user()->isProvider() ? 15000 : 30000 }} })" class="relative">
    <a href="{{ route('notifications.index') }}" class="relative flex h-10 w-10 items-center justify-center rounded-xl {{ ($dark ?? false) ? 'text-navy-100 hover:bg-white/10' : 'text-navy-700 hover:bg-mist' }}" aria-label="Notifications">
        <x-icon name="bell" />
        <span x-show="unread > 0" x-cloak x-text="unread > 9 ? '9+' : unread" class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-500 px-1 text-[10px] font-bold text-white"></span>
    </a>
    <template x-teleport="body">
        <div x-show="toast" x-cloak x-transition class="fixed right-4 top-4 z-[1000] w-[calc(100%-2rem)] max-w-sm">
            <a href="{{ route('notifications.index') }}" class="flex gap-3 rounded-2xl border p-4 shadow-2xl" :class="toast?.kind === 'emergency' ? 'border-red-200 bg-red-600 text-white' : 'border-navy-100 bg-white text-navy-900'">
                <x-icon name="bell" class="mt-0.5 h-5 w-5 shrink-0" />
                <span><span class="block text-sm font-bold" x-text="toast?.title"></span><span class="mt-0.5 block text-sm opacity-80" x-text="toast?.body"></span></span>
            </a>
        </div>
    </template>
</div>
@endauth
