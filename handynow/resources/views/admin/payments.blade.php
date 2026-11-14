@extends('layouts.dashboard', ['title' => 'Payments'])
@section('content')
<div class="grid grid-cols-2 gap-4"><x-stat label="Confirmed revenue" :value="zmw($totals->gross)" icon="wallet" tone="green" /><x-stat label="Commission earned" :value="zmw($totals->commission)" icon="tag" tone="violet" /></div>
<form method="GET" class="mt-6 flex flex-wrap gap-2">
    <select name="status" class="input w-auto"><option value="">All statuses</option>@foreach(['pending', 'confirmed', 'failed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
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
                <td>{{ $p->provider?->name }}</td>
                <td>{{ $p->methodLabel() }}<br><span class="text-xs text-navy-300">{{ $p->transaction_reference }}</span></td>
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
