<a href="{{ route('home') }}" class="flex items-center gap-2.5 {{ $class ?? '' }}" aria-label="HANDY NOW home">
    <svg class="h-10 w-10 shrink-0 drop-shadow-lg" viewBox="0 0 100 100" aria-hidden="true">
        <rect width="100" height="100" rx="20" fill="#FF8A24"/>
        <path d="M25 31L43 22V78H25z M57 22H75V69L57 78z M43 44H57V56H43z" fill="{{ ($light ?? false) ? '#070B14' : '#101D35' }}"/>
    </svg>
    <span class="font-display text-xl font-extrabold leading-none tracking-tight {{ ($light ?? false) ? 'text-white' : 'text-navy-900' }}">HANDY<span class="text-brand-500">NOW</span></span>
</a>
