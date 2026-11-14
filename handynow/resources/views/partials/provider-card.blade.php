@php $dark = $dark ?? false; @endphp
<article class="flex flex-col rounded-2xl p-5 {{ $dark ? 'bg-white/5 ring-1 ring-white/10' : 'card' }}">
    <div class="flex items-start gap-3">
        <div class="relative shrink-0">
            <img src="{{ $p->user->avatarUrl() }}" alt="{{ $p->user->name }}" class="h-14 w-14 rounded-2xl object-cover">
            <span class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 {{ $dark ? 'border-navy-900' : 'border-white' }} {{ $p->is_online ? 'bg-emerald-500' : 'bg-navy-200' }}" title="{{ $p->is_online ? 'Online' : 'Offline' }}"></span>
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="flex items-center gap-1.5 truncate font-bold">{{ $p->user->name }}
                <span class="text-sky-500" title="Verified provider"><x-icon name="shield" class="h-4 w-4" /></span>
            </h3>
            @if($p->business_name)<p class="truncate text-xs {{ $dark ? 'text-navy-200' : 'text-navy-500' }}">{{ $p->business_name }}</p>@endif
            <p class="mt-1 truncate text-xs font-semibold text-brand-500">{{ $p->categories->pluck('name')->join(', ') }}</p>
        </div>
    </div>
    <div class="mt-4 flex items-center gap-2 text-sm">
        <x-stars :rating="$p->rating_avg" />
        <span class="font-bold">{{ number_format($p->rating_avg, 1) }}</span>
        <span class="{{ $dark ? 'text-navy-300' : 'text-navy-300' }}">({{ $p->rating_count }})</span>
    </div>
    <div class="mt-2 space-y-1 text-xs {{ $dark ? 'text-navy-200' : 'text-navy-500' }}">
        <p class="flex items-center gap-1.5"><x-icon name="clock" class="h-3.5 w-3.5" /> Responds in ~{{ $p->avg_response_minutes }} min</p>
        <p class="flex items-center gap-1.5"><x-icon name="map-pin" class="h-3.5 w-3.5" /> <span data-provider-distance="{{ $p->user_id }}">{{ collect($p->operating_areas)->take(2)->join(', ') }}</span></p>
    </div>
    <div class="mt-4 flex gap-2">
        <a href="{{ route('providers.show', $p->user_id) }}" class="btn btn-sm flex-1 {{ $dark ? 'bg-white/10 text-white hover:bg-white/20' : 'btn-outline' }}">Profile</a>
        <a href="{{ route('requests.create', ['category' => $p->categories->first()?->slug]) }}" class="btn btn-sm btn-primary flex-1">Request</a>
    </div>
    @if($p->user->is_demo)<p class="mt-3 text-center"><x-demo-badge :show="true" /></p>@endif
</article>
