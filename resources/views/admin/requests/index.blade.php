@extends('layouts.dashboard', ['title' => 'Service requests'])
@section('content')
<div class="card overflow-hidden" x-data="pointsMap({ points: @js($mapPoints->map(fn ($p) => $p + ['title' => $p['ref'], 'subtitle' => $p['cat'].' · '.$p['status']])->values()), providers: @js($providerPoints) })">
    <div class="flex flex-wrap items-center justify-between gap-2 p-4"><h2 class="font-display text-lg font-bold">Live map</h2><p class="text-xs text-navy-500"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-600"></span> emergency · <span class="inline-block h-2.5 w-2.5 rounded-full bg-brand-500"></span> active request · <span class="inline-block h-2.5 w-2.5 rounded-full bg-navy-900"></span> online provider</p></div>
    <div x-ref="map" class="h-80"></div>
</div>
<form method="GET" class="mt-6 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input w-full sm:w-64" placeholder="Reference, name, phone, area">
    <select name="status" class="input w-auto"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>All active</option>@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="priority" class="input w-auto"><option value="">Any priority</option>@foreach(\App\Models\ServiceRequest::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ ucfirst($k) }}</option>@endforeach</select>
    <select name="category" class="input w-auto"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
    <button class="btn btn-navy">Filter</button>
</form>
<div class="card mt-4 overflow-x-auto">
    <table class="table-x">
        <thead><tr><th>Ref</th><th>Service</th><th>Customer</th><th>Area</th><th>Priority</th><th>Status</th><th>Provider</th><th>Amount</th><th>Created</th></tr></thead>
        <tbody>
        @forelse($requests as $sr)
            <tr>
                <td><a href="{{ route('admin.requests.show', $sr) }}" class="font-bold text-brand-600">{{ $sr->reference }}</a> <x-demo-badge :show="$sr->is_demo" /></td>
                <td>{{ $sr->category->name }}</td>
                <td>{{ $sr->contact_name }}<br><span class="text-xs text-navy-300">{{ $sr->contact_phone }}{{ $sr->customer ? '' : ' · guest' }}</span></td>
                <td class="max-w-48 truncate">{{ $sr->address }}</td>
                <td><x-priority-badge :priority="$sr->priority" /></td>
                <td><x-status-badge :status="$sr->status" /></td>
                <td>{{ $sr->provider?->name ?? '—' }}</td>
                <td>{{ $sr->agreed_amount ? zmw($sr->agreed_amount, 0) : '—' }}</td>
                <td class="whitespace-nowrap text-xs">{{ $sr->created_at->format('d M H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-8 text-center text-navy-500">No requests found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $requests->links() }}</div>
@endsection
