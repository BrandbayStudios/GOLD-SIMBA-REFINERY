@extends('layouts.dashboard', ['title' => 'Hello, '.explode(' ', auth()->user()->name)[0]])
@section('header-actions')<a href="{{ route('requests.create') }}" class="btn btn-sm btn-primary hidden sm:inline-flex">New request</a>@endsection
@section('content')
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Active requests" :value="$stats['active']" icon="siren" tone="orange" :href="route('customer.requests.index', ['filter' => 'active'])" />
    <x-stat label="Completed" :value="$stats['completed']" icon="check-circle" tone="green" />
    <x-stat label="Total requests" :value="$stats['total']" icon="list" />
    <x-stat label="Total paid" :value="zmw($stats['spent'], 0)" icon="wallet" tone="violet" :href="route('customer.payments')" />
</div>

<a href="{{ route('requests.emergency') }}" class="mt-6 flex items-center gap-4 rounded-2xl bg-gradient-to-r from-red-600 to-brand-500 p-5 text-white shadow-lg">
    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20"><x-icon name="siren" /></span>
    <span class="flex-1"><span class="block font-display text-lg font-bold">Need help right now?</span><span class="text-sm text-white/85">Send an emergency request – nearby providers are alerted instantly.</span></span>
    <x-icon name="arrow-right" />
</a>

<div class="mt-8 grid gap-6 xl:grid-cols-[1fr_360px]">
    <section>
        <h2 class="font-display text-lg font-bold">Active requests</h2>
        <div class="mt-3 space-y-3">
            @forelse($active as $sr)
                @include('partials.request-row', ['sr' => $sr, 'href' => route('customer.requests.show', $sr)])
            @empty
                <div class="card card-pad text-center text-sm text-navy-500">No active requests. <a href="{{ route('requests.create') }}" class="font-semibold text-brand-600">Request a service →</a></div>
            @endforelse
        </div>
        <h2 class="mt-8 font-display text-lg font-bold">Recent history</h2>
        <div class="mt-3 space-y-3">
            @forelse($recent as $sr)
                @include('partials.request-row', ['sr' => $sr, 'href' => route('customer.requests.show', $sr)])
            @empty
                <p class="text-sm text-navy-500">Nothing here yet.</p>
            @endforelse
        </div>
    </section>
    <aside class="space-y-6">
        @if($toRate->isNotEmpty())
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Rate your providers</h2>
            <div class="mt-3 space-y-2">
                @foreach($toRate as $sr)
                    <a href="{{ route('customer.requests.show', $sr) }}" class="flex items-center justify-between rounded-xl bg-mist p-3 text-sm font-semibold hover:bg-navy-50">{{ $sr->reference }} <span class="text-brand-600">Rate ★</span></a>
                @endforeach
            </div>
        </div>
        @endif
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Need support?</h2>
            <p class="mt-1 text-sm text-navy-500">Our team is available 24/7.</p>
            <div class="mt-4 grid gap-2">
                <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="btn btn-outline"><x-icon name="phone" class="h-4 w-4" /> {{ $supportPhone }}</a>
                <a href="{{ whatsapp_link($supportWhatsapp, 'Hello HANDY NOW support') }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-icon name="whatsapp" class="h-4 w-4" /> WhatsApp support</a>
                <a href="{{ route('customer.complaints.create') }}" class="btn btn-ghost">Report a complaint</a>
            </div>
        </div>
    </aside>
</div>
@endsection
