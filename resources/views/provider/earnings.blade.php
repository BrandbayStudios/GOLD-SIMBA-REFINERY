@extends('layouts.dashboard', ['title' => 'Earnings & commissions'])
@section('content')
<div class="mb-6 grid gap-4 lg:grid-cols-[1fr_1fr]">
    <div class="card card-pad">
        <p class="text-xs font-bold uppercase tracking-wider text-navy-300">Your balance with HANDY NOW</p>
        <p class="mt-1 font-display text-4xl font-extrabold {{ $balance > 0 ? 'text-emerald-700' : ($balance < 0 ? 'text-red-600' : '') }}">{{ $balance < 0 ? '−' : '' }}{{ zmw(abs($balance)) }}</p>
        @if($balance > 0)
            <p class="mt-1 text-sm text-navy-500">HANDY NOW owes you this from jobs customers paid through the app. It is sent to <b>{{ auth()->user()->providerProfile->payoutLabel() }}</b>.</p>
        @elseif($balance < 0)
            <p class="mt-1 text-sm text-navy-500">You owe HANDY NOW this commission on jobs customers paid you directly.</p>
            @if($instructions)<p class="mt-2 rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-900">{{ $instructions }}</p>@endif
        @else
            <p class="mt-1 text-sm text-navy-500">Everything is settled.</p>
        @endif
        <a href="{{ route('provider.profile') }}" class="mt-3 inline-block text-sm font-semibold text-brand-600">Change payment method →</a>
    </div>
    <div class="card overflow-x-auto">
        <h2 class="p-5 pb-2 font-display text-lg font-bold">Payouts to you</h2>
        <table class="table-x"><thead><tr><th>Date</th><th>Amount</th><th>Sent to</th><th>Status</th></tr></thead><tbody>
            @forelse($payouts as $po)<tr><td class="text-xs">{{ ($po->paid_at ?? $po->created_at)->format('d M Y') }}</td><td class="font-bold">{{ zmw($po->amount) }}</td><td class="text-xs">{{ $po->destination }}@if($po->transaction_reference)<br><span class="text-navy-300">ref {{ $po->transaction_reference }}</span>@endif</td><td><x-status-badge :status="$po->status === 'paid' ? 'confirmed' : ($po->status === 'pending' ? 'pending' : 'cancelled')" :label="$po->status === 'pending' ? 'Being sent' : ucfirst($po->status)" /></td></tr>@empty<tr><td colspan="4" class="py-6 text-center text-navy-500">No payouts yet.</td></tr>@endforelse
        </tbody></table>
    </div>
</div>
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Gross received" :value="zmw($totals->gross, 0)" icon="wallet" />
    <x-stat label="Platform commission" :value="zmw($totals->commission, 0)" icon="tag" tone="orange" />
    <x-stat label="Your net earnings" :value="zmw($totals->net, 0)" icon="check-circle" tone="green" />
    <x-stat label="Paid jobs" :value="$totals->jobs" icon="list" tone="violet" />
</div>
<div class="card mt-6 overflow-x-auto">
    <table class="table-x">
        <thead><tr><th>Date</th><th>Job</th><th>Method</th><th>Amount</th><th>Commission</th><th>You earn</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td>{{ $p->created_at->format('d M Y') }}</td>
                <td><a href="{{ route('provider.jobs.show', $p->serviceRequest) }}" class="font-semibold text-brand-600">{{ $p->serviceRequest->reference }}</a></td>
                <td>{{ $p->methodLabel() }}</td>
                <td>{{ zmw($p->amount) }}</td>
                <td>{{ $p->status === 'confirmed' ? zmw($p->commission_amount).' ('.(float) $p->commission_rate.'%)' : '–' }}</td>
                <td class="font-bold">{{ $p->status === 'confirmed' ? zmw($p->provider_amount) : '–' }}</td>
                <td><x-status-badge :status="$p->status" :label="ucfirst($p->status)" /> <x-demo-badge :show="$p->is_demo" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-8 text-center text-navy-500">No payments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $payments->links() }}</div>
<h2 class="mt-8 font-display text-lg font-bold">Completed jobs</h2>
<div class="mt-3 space-y-3">
    @forelse($completedJobs as $sr)
        @include('partials.request-row', ['sr' => $sr, 'href' => route('provider.jobs.show', $sr)])
    @empty
        <p class="text-sm text-navy-500">No completed jobs yet.</p>
    @endforelse
</div>
@endsection
