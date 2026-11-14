@extends('layouts.dashboard', ['title' => 'Platform overview'])
@section('content')
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Active requests" :value="$stats['active_requests']" icon="siren" tone="orange" :href="route('admin.requests.index', ['status' => 'active'])" :hint="$stats['active_emergencies'].' emergencies'" />
    <x-stat label="Pending verification" :value="$stats['pending_verification']" icon="shield" tone="red" :href="route('admin.providers.index', ['status' => 'pending'])" />
    <x-stat label="Revenue processed" :value="zmw($stats['revenue'], 0)" icon="wallet" tone="green" :href="route('admin.payments.index')" />
    <x-stat label="Platform commission" :value="zmw($stats['commission'], 0)" icon="tag" tone="violet" :href="route('admin.reports')" />
    <x-stat label="Customers" :value="$stats['customers']" icon="users" :href="route('admin.users.index', ['role' => 'customer'])" />
    <x-stat label="Service providers" :value="$stats['providers']" icon="wrench" :href="route('admin.providers.index')" :hint="$stats['online_providers'].' online now'" />
    <x-stat label="Completed jobs" :value="$stats['completed']" icon="check-circle" tone="green" :href="route('admin.requests.index', ['status' => 'completed'])" />
    <x-stat label="Cancelled" :value="$stats['cancelled']" icon="x" :href="route('admin.requests.index', ['status' => 'cancelled'])" :hint="$stats['open_complaints'].' open complaints · '.$stats['pending_payments'].' unconfirmed payments'" />
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="card card-pad"><h2 class="font-display text-lg font-bold">Requests – last 14 days</h2><div class="mt-6">@include('partials.bar-chart', ['data' => $trend, 'key' => 'requests', 'color' => 'bg-navy-900'])</div></div>
    <div class="card card-pad"><h2 class="font-display text-lg font-bold">Revenue (ZMW) – last 14 days</h2><div class="mt-6">@include('partials.bar-chart', ['data' => $trend, 'key' => 'revenue', 'format' => fn ($v) => zmw($v, 0)])</div></div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_400px]">
    <section class="card card-pad">
        <div class="flex items-center justify-between"><h2 class="font-display text-lg font-bold">Live request monitor</h2><a href="{{ route('admin.requests.index', ['status' => 'active']) }}" class="text-sm font-semibold text-brand-600">View all →</a></div>
        <div class="mt-4 space-y-3">
            @forelse($emergencies as $sr)
                @include('partials.request-row', ['sr' => $sr, 'href' => route('admin.requests.show', $sr)])
            @empty
                <p class="py-6 text-center text-sm text-navy-500">No active requests.</p>
            @endforelse
        </div>
    </section>
    <aside class="space-y-6">
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Verification queue</h2>
            <div class="mt-3 space-y-2">
                @forelse($pendingProviders as $p)
                    <a href="{{ route('admin.providers.show', $p->user) }}" class="flex items-center gap-3 rounded-xl p-2 hover:bg-mist">
                        <img src="{{ $p->user->avatarUrl() }}" alt="" class="h-9 w-9 rounded-lg">
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $p->user->name }}</span><span class="block truncate text-xs text-navy-500">{{ $p->categories->pluck('name')->join(', ') }}</span></span>
                        <span class="text-xs text-navy-300">{{ $p->created_at->diffForHumans(null, true) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-navy-500">All providers reviewed. ✓</p>
                @endforelse
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Open complaints</h2>
            <div class="mt-3 space-y-2">
                @forelse($complaints as $c)
                    <a href="{{ route('admin.complaints.show', $c) }}" class="block rounded-xl p-2 hover:bg-mist"><span class="block text-sm font-bold">{{ $c->subject }}</span><span class="text-xs text-navy-500">{{ $c->user->name }} · {{ $c->created_at->diffForHumans() }}</span></a>
                @empty
                    <p class="text-sm text-navy-500">No open complaints.</p>
                @endforelse
            </div>
        </div>
    </aside>
</div>
@endsection
