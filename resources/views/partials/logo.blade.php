<a href="{{ route('home') }}" class="flex items-center gap-2.5 {{ $class ?? '' }}" aria-label="HANDY NOW home">
    <span class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-lg shadow-brand-500/30">
        <x-icon name="wrench" class="h-5 w-5" :stroke="2.5" />
        <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-navy-900"></span>
    </span>
    <span class="font-display text-xl font-extrabold leading-none tracking-tight {{ ($light ?? false) ? 'text-white' : 'text-navy-900' }}">HANDY<span class="text-brand-500">NOW</span></span>
</a>
