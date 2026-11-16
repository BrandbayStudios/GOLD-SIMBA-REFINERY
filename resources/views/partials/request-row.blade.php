{{-- $sr, $href --}}
<a href="{{ $href }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-navy-50 bg-white p-4 transition hover:border-navy-200 hover:shadow-md">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $sr->priority === 'emergency' ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600' }}"><x-icon :name="$sr->category->icon" /></span>
    <div class="min-w-[12rem] flex-1">
        <p class="truncate font-bold">{{ $sr->service?->name ?? $sr->category->name }}</p>
        <p class="truncate text-xs text-navy-500">{{ $sr->reference }} · {{ $sr->address }} · {{ $sr->created_at->diffForHumans() }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-1.5">
        @if(($sr->pending_quotes_count ?? 0) > 0)<span class="badge badge-orange">{{ $sr->pending_quotes_count }} quote(s)</span>@endif
        @if($sr->priority !== 'normal')<x-priority-badge :priority="$sr->priority" />@endif
        <x-status-badge :status="$sr->status" />
        <x-demo-badge :show="$sr->is_demo" />
        @if($sr->agreed_amount)<span class="text-sm font-bold">{{ zmw($sr->agreed_amount) }}</span>@endif
    </div>
</a>
