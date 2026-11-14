@extends('layouts.dashboard', ['title' => 'Provider dashboard'])
@section('content')
@if($profile->verification_status !== 'approved')
    <div class="mb-6 rounded-2xl border p-5 {{ $profile->verification_status === 'rejected' ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
        <p class="font-display text-lg font-bold">{{ $profile->verification_status === 'rejected' ? 'Verification unsuccessful' : 'Verification in progress' }}</p>
        <p class="mt-1 text-sm">{{ $profile->verification_status === 'rejected' ? 'Reason: '.$profile->verification_notes.' – update your documents to resubmit.' : 'Our team is reviewing your NRC and documents. You can go online and receive jobs once approved.' }}</p>
        <a href="{{ route('provider.profile') }}" class="btn btn-sm btn-navy mt-3">View verification details</a>
    </div>
@endif

{{-- Availability --}}
<div class="card card-pad flex flex-wrap items-center gap-4 {{ $profile->is_online ? 'ring-2 ring-emerald-400' : '' }}">
    <span class="flex h-12 w-12 items-center justify-center rounded-xl {{ $profile->is_online ? 'bg-emerald-50 text-emerald-600' : 'bg-mist text-navy-300' }}">
        @if($profile->is_online)<span class="pulse-dot text-emerald-500"></span>@else<x-icon name="bell" />@endif
    </span>
    <div class="flex-1">
        <p class="font-display text-lg font-bold">You are {{ $profile->is_online ? 'ONLINE' : 'OFFLINE' }}</p>
        <p class="text-sm text-navy-500">{{ $profile->is_online ? 'You will receive job alerts in your areas by SMS and in-app.' : 'Go online to start receiving emergency job alerts.' }}</p>
    </div>
    <form method="POST" action="{{ route('provider.availability') }}">
        @csrf <input type="hidden" name="online" value="{{ $profile->is_online ? 0 : 1 }}">
        <button class="btn {{ $profile->is_online ? 'btn-outline' : 'btn-success' }}" @disabled(! $profile->isApproved())>{{ $profile->is_online ? 'Go offline' : 'Go online' }}</button>
    </form>
    @error('availability')<p class="field-error w-full">{{ $message }}</p>@enderror
</div>

@if($activeJobs->whereIn('status', ['accepted', 'en_route'])->isNotEmpty())
    <div class="mt-4 rounded-2xl bg-navy-900 p-4 text-sm text-white" x-data="shareLocation({ url: '{{ route('provider.location') }}', auto: true })">
        <p class="flex items-center gap-2 font-semibold"><x-icon name="navigation" class="h-4 w-4 text-brand-400" />
            <span x-show="status === 'sharing'">Sharing your live location with your customer<span x-show="last"> · last sent <span x-text="last"></span></span></span>
            <span x-show="status === 'denied'" x-cloak>Location permission denied – customers can't see your arrival progress.</span>
            <span x-show="status === 'starting' || status === 'idle'">Starting location sharing…</span>
        </p>
    </div>
@endif

<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="New job alerts" :value="$newJobs->count()" icon="bell" tone="orange" :href="route('provider.jobs.index')" />
    <x-stat label="Active jobs" :value="$activeJobs->count()" icon="siren" :href="route('provider.jobs.index', ['tab' => 'active'])" />
    <x-stat label="Earned this month" :value="zmw($stats['earnings_month'], 0)" icon="wallet" tone="green" :href="route('provider.earnings')" hint="after commission" />
    <x-stat label="Rating" :value="number_format($stats['rating'], 1).' ★'" icon="star" tone="violet" :href="route('provider.reviews')" :hint="$stats['rating_count'].' reviews'" />
</div>

@if($pendingPayments->isNotEmpty())
    <div class="mt-6 card card-pad">
        <h2 class="font-display text-lg font-bold">Payments to confirm</h2>
        @foreach($pendingPayments as $p)
            <div class="mt-3 flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-3 text-sm">
                <span class="flex-1"><b>{{ zmw($p->amount) }}</b> via {{ $p->methodLabel() }} for {{ $p->serviceRequest->reference }} @if($p->transaction_reference)(ref {{ $p->transaction_reference }})@endif</span>
                <form method="POST" action="{{ route('provider.payments.confirm', $p) }}">@csrf<button class="btn btn-sm btn-success">Received</button></form>
                <form method="POST" action="{{ route('provider.payments.reject', $p) }}">@csrf<button class="btn btn-sm btn-outline">Not received</button></form>
            </div>
        @endforeach
    </div>
@endif

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section>
        <h2 class="font-display text-lg font-bold">New job alerts near you</h2>
        <div class="mt-3 space-y-3">
            @forelse($newJobs as $sr)
                @php $m = $sr->matches->first(); @endphp
                <a href="{{ route('provider.jobs.show', $sr) }}" class="block rounded-2xl border bg-white p-4 hover:shadow-md {{ $sr->priority === 'emergency' ? 'border-red-200' : 'border-navy-50' }}">
                    <div class="flex items-center justify-between gap-2"><p class="font-bold">{{ $sr->category->name }}</p><x-priority-badge :priority="$sr->priority" /></div>
                    <p class="mt-1 line-clamp-2 text-sm text-navy-500">{{ $sr->description }}</p>
                    <p class="mt-2 text-xs font-semibold text-navy-300">{{ $sr->address }} @if($m?->distance_km) · {{ $m->distance_km }} km @endif · {{ $sr->created_at->diffForHumans() }} <x-demo-badge :show="$sr->is_demo" /></p>
                </a>
            @empty
                <div class="card card-pad text-center text-sm text-navy-500">No new jobs right now. {{ $profile->is_online ? 'We will alert you the moment one comes in.' : 'Go online to receive jobs.' }}</div>
            @endforelse
        </div>
    </section>
    <section>
        <h2 class="font-display text-lg font-bold">Your active jobs</h2>
        <div class="mt-3 space-y-3">
            @forelse($activeJobs as $sr)
                @include('partials.request-row', ['sr' => $sr, 'href' => route('provider.jobs.show', $sr)])
            @empty
                <div class="card card-pad text-center text-sm text-navy-500">No active jobs.</div>
            @endforelse
        </div>
        @if($pendingQuotes->isNotEmpty())
            <h2 class="mt-6 font-display text-lg font-bold">Quotes awaiting customer</h2>
            <div class="mt-3 space-y-2">
                @foreach($pendingQuotes as $q)
                    <a href="{{ route('provider.jobs.show', $q->serviceRequest) }}" class="flex items-center justify-between rounded-xl bg-white p-3 text-sm hover:bg-mist"><span>{{ $q->serviceRequest->reference }} · {{ $q->serviceRequest->category->name }}</span><b>{{ zmw($q->total()) }}</b></a>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
