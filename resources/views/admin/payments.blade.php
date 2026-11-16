@extends('layouts.dashboard', ['title' => 'Payments'])
@section('content')
<div class="grid grid-cols-2 gap-4 lg:grid-cols-3"><x-stat label="Job payments confirmed" :value="zmw($totals->gross)" icon="wallet" tone="green" /><x-stat label="Commission earned" :value="zmw($totals->commission)" icon="tag" tone="violet" /><x-stat label="Booking fees received" :value="zmw($fees)" icon="check-circle" tone="orange" /></div>
<form method="GET" class="mt-6 flex flex-wrap gap-2">
    <select name="status" class="input w-auto"><option value="">All statuses</option>@foreach(['pending', 'confirmed', 'failed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
    <select name="kind" class="input w-auto"><option value="">Jobs & booking fees</option><option value="job" @selected(request('kind') === 'job')>Job payments</option><option value="booking_fee" @selected(request('kind') === 'booking_fee')>Booking fees</option></select>
    <select name="method" class="input w-auto"><option value="">All methods</option>@foreach(\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select>
    <button class="btn btn-navy">Filter</button>
</form>
<div class="card mt-4 overflow-x-auto">
    <table class="table-x"><thead><tr><th>Date</th><th>Request</th><th>Customer</th><th>Provider</th><th>Method</th><th>Amount</th><th>Commission</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($payments as $p)
            <tr>
                <td class="text-xs">{{ $p->created_at->format('d M Y H:i') }}</td>
                <td><a href="{{ route('admin.requests.show', $p->serviceRequest) }}" class="font-semibold text-brand-600">{{ $p->serviceRequest->reference }}</a></td>
                <td>{{ $p->customer?->name ?? $p->serviceRequest->contact_name }}</td>
                <td>{{ $p->isBookingFee() ? 'HANDY NOW' : $p->provider?->name }}</td>
                <td>@if($p->isBookingFee())<span class="badge badge-orange">Booking fee</span> @endif{{ $p->methodLabel() }}<br><span class="text-xs text-navy-300">{{ $p->transaction_reference }}</span></td>
                <td class="font-bold">{{ zmw($p->amount) }}</td>
                <td>{{ $p->status === 'confirmed' ? zmw($p->commission_amount) : '—' }}</td>
                <td><x-status-badge :status="$p->status" :label="ucfirst($p->status)" /> <x-demo-badge :show="$p->is_demo" /></td>
                <td class="whitespace-nowrap">@if($p->status === 'pending')<form method="POST" action="{{ route('admin.payments.confirm', $p) }}" class="inline">@csrf<button class="btn btn-sm btn-success">Confirm</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-8 text-center text-navy-500">No payments.</td></tr>
        @endforelse
    </tbody></table>
</div>
<div class="mt-6">{{ $payments->links() }}</div>
@endsection
