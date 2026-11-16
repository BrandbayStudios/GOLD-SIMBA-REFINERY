@extends('layouts.dashboard', ['title' => 'Payment history'])
@section('content')
@if($outstanding->isNotEmpty())
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-bold">Outstanding payments</p>
        @foreach($outstanding as $sr)
            <a href="{{ route('customer.requests.show', $sr) }}" class="mt-1 block underline">{{ $sr->reference }} – {{ zmw($sr->agreed_amount) }} due</a>
        @endforeach
    </div>
@endif
<div class="card overflow-x-auto">
    <table class="table-x">
        <thead><tr><th>Date</th><th>Request</th><th>Provider</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td>{{ $p->created_at->format('d M Y') }}</td>
                <td><a href="{{ route('customer.requests.show', $p->serviceRequest) }}" class="font-semibold text-brand-600">{{ $p->serviceRequest->reference }}</a><br><span class="text-xs text-navy-300">{{ $p->serviceRequest->category->name }}</span></td>
                <td>{{ $p->provider?->name }}</td>
                <td>{{ $p->methodLabel() }}@if($p->transaction_reference)<br><span class="text-xs text-navy-300">{{ $p->transaction_reference }}</span>@endif</td>
                <td class="font-bold">{{ zmw($p->amount) }}</td>
                <td><x-status-badge :status="$p->status" :label="ucfirst($p->status)" /> <x-demo-badge :show="$p->is_demo" /></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-8 text-center text-navy-500">No payments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $payments->links() }}</div>
@endsection
