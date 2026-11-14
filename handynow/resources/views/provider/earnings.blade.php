@extends('layouts.dashboard', ['title' => 'Earnings & commissions'])
@section('content')
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
