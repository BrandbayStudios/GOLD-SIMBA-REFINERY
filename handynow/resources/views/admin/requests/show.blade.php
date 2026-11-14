@extends('layouts.dashboard', ['title' => 'Request '.$sr->reference])
@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_400px]">
    <div class="space-y-6">
        <div class="card card-pad">
            <div class="flex flex-wrap items-center gap-2"><x-priority-badge :priority="$sr->priority" /><x-status-badge :status="$sr->status" /><x-status-badge :status="$sr->payment_status" :label="'Payment: '.$sr->payment_status" /><x-demo-badge :show="$sr->is_demo" /></div>
            <h2 class="mt-3 font-display text-2xl font-bold">{{ $sr->service?->name ?? $sr->category->name }}</h2>
            <p class="text-sm text-navy-500">{{ $sr->category->name }} · {{ $sr->created_at->format('d M Y H:i') }}</p>
            <p class="mt-4 whitespace-pre-line">{{ $sr->description }}</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase text-navy-300">Customer</dt><dd>{{ $sr->contact_name }} · <a href="tel:+{{ zm_phone($sr->contact_phone) }}" class="text-brand-600">{{ $sr->contact_phone }}</a> {{ $sr->customer ? '(registered)' : '(guest)' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Location</dt><dd>{{ $sr->address }}@if($sr->directions)<br>{{ $sr->directions }}@endif</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Provider</dt><dd>@if($sr->provider)<a href="{{ route('admin.providers.show', $sr->provider) }}" class="text-brand-600">{{ $sr->provider->name }}</a>@else — @endif</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Amount / commission</dt><dd>{{ $sr->agreed_amount ? zmw($sr->agreed_amount) : '—' }} @if($sr->commission_amount) / {{ zmw($sr->commission_amount) }} ({{ (float) $sr->commission_rate }}%)@endif</dd></div>
            </dl>
            @if($sr->photos)<div class="mt-4 flex gap-2">@foreach($sr->photoUrls() as $ph)<a href="{{ $ph }}" target="_blank"><img src="{{ $ph }}" alt="" class="h-20 w-20 rounded-lg object-cover"></a>@endforeach</div>@endif
        </div>
        @if($sr->latitude)
            <div class="card overflow-hidden" x-data="pointsMap({ points: [{ lat: {{ $sr->latitude }}, lng: {{ $sr->longitude }}, title: '{{ $sr->reference }}', priority: '{{ $sr->priority }}' }], providers: @js($eligible->filter(fn ($e) => $e['profile']->latitude())->map(fn ($e) => ['lat' => $e['profile']->latitude(), 'lng' => $e['profile']->longitude(), 'name' => $e['profile']->displayName()])->values()) })"><div x-ref="map" class="h-72"></div></div>
        @endif
        <div class="card overflow-x-auto">
            <h2 class="p-5 font-display text-lg font-bold">Quotations</h2>
            <table class="table-x"><thead><tr><th>Provider</th><th>Total</th><th>ETA</th><th>Status</th><th>Sent</th></tr></thead><tbody>
                @forelse($sr->quotations as $q)<tr><td>{{ $q->provider->name }}</td><td>{{ zmw($q->total()) }}</td><td>{{ $q->eta_minutes }} min</td><td><x-status-badge :status="$q->status" :label="ucfirst($q->status)" /></td><td class="text-xs">{{ $q->created_at->format('d M H:i') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-navy-500">No quotations.</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="card overflow-x-auto">
            <h2 class="p-5 font-display text-lg font-bold">Payments</h2>
            <table class="table-x"><thead><tr><th>Method</th><th>Amount</th><th>Reference</th><th>Status</th><th></th></tr></thead><tbody>
                @forelse($sr->payments as $p)
                    <tr><td>{{ $p->methodLabel() }}</td><td>{{ zmw($p->amount) }}</td><td>{{ $p->transaction_reference ?? '—' }}</td><td><x-status-badge :status="$p->status" :label="ucfirst($p->status)" /></td>
                        <td class="whitespace-nowrap">@if($p->status === 'pending')<form method="POST" action="{{ route('admin.payments.confirm', $p) }}" class="inline">@csrf<button class="btn btn-sm btn-success">Confirm</button></form> <form method="POST" action="{{ route('admin.payments.reject', $p) }}" class="inline">@csrf<button class="btn btn-sm btn-outline">Reject</button></form>@endif</td></tr>
                @empty<tr><td colspan="5" class="text-center text-navy-500">No payments recorded.</td></tr>@endforelse
            </tbody></table>
        </div>
        @if($sr->messages->isNotEmpty())
            <div class="card card-pad"><h2 class="font-display text-lg font-bold">Messages</h2>
                <div class="mt-3 space-y-2 text-sm">@foreach($sr->messages as $m)<p><b>{{ $m->sender->name }}:</b> {{ $m->body }} <span class="text-xs text-navy-300">{{ $m->created_at->format('d M H:i') }}</span></p>@endforeach</div>
            </div>
        @endif
    </div>
    <aside class="space-y-6">
        @if($sr->isOpen())
            <div class="card card-pad">
                <h2 class="font-display text-lg font-bold">Dispatch</h2>
                <p class="mt-1 text-sm text-navy-500">{{ $sr->matches->count() }} provider(s) notified so far.</p>
                <form method="POST" action="{{ route('admin.requests.rematch', $sr) }}" class="mt-3">@csrf<button class="btn btn-outline w-full"><x-icon name="refresh" class="h-4 w-4" /> Re-run matching</button></form>
                <form method="POST" action="{{ route('admin.requests.assign', $sr) }}" class="mt-4 space-y-2">
                    @csrf
                    <label class="label" for="pid">Assign directly</label>
                    <select id="pid" name="provider_id" class="input" required>
                        <option value="">Select verified provider…</option>
                        @foreach($eligible as $e)<option value="{{ $e['profile']->user_id }}">{{ $e['profile']->displayName() }} {{ $e['distance'] !== null ? '· '.$e['distance'].' km' : '' }} {{ $e['profile']->is_online ? '· online' : '· offline' }}</option>@endforeach
                    </select>
                    <button class="btn btn-navy w-full">Assign provider</button>
                </form>
            </div>
        @endif
        @if(! $sr->isCompleted() && ! $sr->isCancelled())
            <details class="card card-pad"><summary class="cursor-pointer font-bold text-red-600">Cancel request</summary>
                <form method="POST" action="{{ route('admin.requests.cancel', $sr) }}" class="mt-3 space-y-2">@csrf<input name="reason" class="input" required placeholder="Reason (sent to customer & provider)"><button class="btn btn-danger w-full">Cancel request</button></form>
            </details>
        @endif
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Matched providers</h2>
            <ul class="mt-3 space-y-1 text-sm">@forelse($sr->matches as $m)<li class="flex justify-between"><span>{{ $m->provider->name }}</span><span class="text-navy-500">{{ $m->distance_km ? $m->distance_km.' km · ' : '' }}{{ $m->status }}</span></li>@empty<li class="text-navy-500">None yet.</li>@endforelse</ul>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Audit trail</h2>
            <ol class="mt-3 space-y-3 text-sm">@foreach($sr->statusLogs->reverse() as $log)<li><b>{{ $log->label() }}</b> <span class="text-xs text-navy-300">{{ $log->created_at->format('d M H:i') }} {{ $log->user ? '· '.$log->user->name : '' }}</span>@if($log->note)<br><span class="text-xs text-navy-500">{{ $log->note }}</span>@endif</li>@endforeach</ol>
        </div>
        @if($sr->review)<div class="card card-pad"><h2 class="font-display text-lg font-bold">Review</h2><x-stars :rating="$sr->review->rating" /><p class="mt-2 text-sm">{{ $sr->review->comment }}</p></div>@endif
    </aside>
</div>
@endsection
