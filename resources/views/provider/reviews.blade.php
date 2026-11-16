@extends('layouts.dashboard', ['title' => 'Reviews & ratings'])
@section('content')
<div class="card card-pad grid gap-6 sm:grid-cols-[200px_1fr]">
    <div class="text-center">
        <p class="font-display text-5xl font-extrabold">{{ number_format($profile->rating_avg, 1) }}</p>
        <x-stars :rating="$profile->rating_avg" size="h-5 w-5" />
        <p class="mt-1 text-sm text-navy-500">{{ $profile->rating_count }} ratings</p>
    </div>
    <div class="space-y-1.5">
        @php $total = max(1, $breakdown->sum()); @endphp
        @for($i = 5; $i >= 1; $i--)
            <div class="flex items-center gap-3 text-sm"><span class="w-6">{{ $i }}★</span><div class="h-2 flex-1 rounded-full bg-mist"><div class="h-2 rounded-full bg-brand-500" style="width: {{ ($breakdown[$i] ?? 0) / $total * 100 }}%"></div></div><span class="w-6 text-right text-navy-500">{{ $breakdown[$i] ?? 0 }}</span></div>
        @endfor
        <p class="pt-2 text-xs text-navy-300">Breakdown of reviews left on HANDY NOW.</p>
    </div>
</div>
<div class="mt-6 space-y-3">
    @forelse($reviews as $r)
        <div class="card card-pad">
            <div class="flex items-center justify-between"><x-stars :rating="$r->rating" /><span class="text-xs text-navy-300">{{ $r->created_at->format('d M Y') }} · {{ $r->serviceRequest->reference }}</span></div>
            <p class="mt-2">{{ $r->comment ?: 'No comment.' }}</p>
            <p class="mt-1 text-xs font-semibold text-navy-300">{{ $r->customer?->name ?? 'Guest customer' }} <x-demo-badge :show="$r->is_demo" /></p>
        </div>
    @empty
        <div class="card card-pad text-center text-navy-500">No reviews yet. Complete jobs to start building your reputation.</div>
    @endforelse
</div>
<div class="mt-6">{{ $reviews->links() }}</div>
@endsection
