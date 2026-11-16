{{-- A provider asks for extra money on site. Needs $sr, $r --}}
@php $pendingVar = $sr->variations->firstWhere('status', 'pending'); @endphp
@if($pendingVar && $sr->isActive())
    <div class="card card-pad border-2 border-amber-400">
        <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Needs your approval</p>
        <h2 class="mt-1 font-display text-xl font-bold">{{ $sr->provider->name }} asks to add an extra cost</h2>
        <p class="mt-2 font-display text-4xl font-extrabold">+ {{ zmw($pendingVar->amount) }}</p>
        <p class="mt-2 rounded-xl bg-mist p-3 text-sm">“{{ $pendingVar->reason }}”</p>
        <p class="mt-2 text-sm text-navy-500">Price now {{ zmw($sr->agreed_amount) }}. If you approve it becomes <b class="text-navy-900">{{ zmw($sr->agreed_amount + $pendingVar->amount) }}</b>. Nothing is added unless you approve.</p>
        <div class="mt-4 flex gap-2">
            <form method="POST" action="{{ $r['approveV']($pendingVar) }}" class="flex-1">@csrf<button class="btn btn-success w-full">Approve extra cost</button></form>
            <form method="POST" action="{{ $r['declineV']($pendingVar) }}">@csrf<button class="btn btn-outline">Decline</button></form>
        </div>
    </div>
@endif
