@extends('layouts.dashboard', ['title' => 'Job '.$sr->reference])
@php
    $labels = ['accepted' => 'Confirm & accept job', 'en_route' => "I'm on my way", 'arrived' => "I've arrived", 'in_progress' => 'Start work', 'completed' => 'Mark job completed'];
    $pendingPayment = $sr->payments->where('kind', 'job')->firstWhere('status', 'pending');
    $awaitingPrompt = $pendingPayment && $pendingPayment->isOnline();
    if ($awaitingPrompt) { $pendingPayment = null; }
@endphp
@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="space-y-6">
        <div class="card card-pad">
            <div class="flex flex-wrap items-center gap-2"><x-priority-badge :priority="$sr->priority" /><x-status-badge :status="$sr->status" /><x-demo-badge :show="$sr->is_demo" /></div>
            <h2 class="mt-3 font-display text-2xl font-bold">{{ $sr->service?->name ?? $sr->category->name }}</h2>
            <p class="text-sm text-navy-500">{{ $sr->category->name }} · {{ $sr->created_at->format('d M Y, H:i') }} @if($distance !== null)· <b>{{ $distance }} km from you</b>@endif</p>
            <p class="mt-4 whitespace-pre-line">{{ $sr->description }}</p>
            @if($sr->dest_address)<p class="mt-3 rounded-xl bg-mist p-3 text-sm"><b>Drop-off:</b> {{ $sr->dest_address }}@if($sr->route_km) · about {{ $sr->route_km }} km by road @endif</p>@endif
            @if($sr->photos)<div class="mt-4 flex flex-wrap gap-2">@foreach($sr->photoUrls() as $ph)<a href="{{ $ph }}" target="_blank"><img src="{{ $ph }}" alt="" class="h-24 w-24 rounded-xl object-cover"></a>@endforeach</div>@endif
        </div>

        @if($sr->latitude)
            <div class="card overflow-hidden" x-data="pointsMap({ points: [{ lat: {{ $sr->latitude }}, lng: {{ $sr->longitude }}, title: 'Customer location', subtitle: @js($sr->address), priority: '{{ $sr->priority }}' }] })">
                <div x-ref="map" class="h-72"></div>
                <div class="flex flex-wrap items-center justify-between gap-2 p-4 text-sm">
                    <span><x-icon name="map-pin" class="inline h-4 w-4 text-brand-500" /> {{ $sr->address }}@if($sr->directions) – {{ $sr->directions }}@endif</span>
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $sr->latitude }},{{ $sr->longitude }}" target="_blank" rel="noopener" class="btn btn-sm btn-navy"><x-icon name="navigation" class="h-4 w-4" /> Directions</a>
                </div>
            </div>
        @else
            <div class="card card-pad text-sm"><x-icon name="map-pin" class="inline h-4 w-4 text-brand-500" /> {{ $sr->address }}@if($sr->directions)<br><span class="text-navy-500">{{ $sr->directions }}</span>@endif</div>
        @endif

        @if($isAssigned && $sr->isActive() && $sr->contactUnlocked())
            @include('partials.chat', ['sr' => $sr])
        @endif
    </div>

    <aside class="space-y-6">
        {{-- Quote / workflow actions --}}
        @if($isAssigned)
            <div class="card card-pad">
                <h2 class="font-display text-lg font-bold">Customer</h2>
                @unless($sr->contactUnlocked())
                    <div class="mt-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-bold">Waiting for the booking fee</p>
                        <p class="mt-1">The customer's name and phone number unlock as soon as they pay the {{ zmw($sr->booking_fee_amount) }} booking fee. We will alert you, and you can then head out.</p>
                    </div>
                @else
                <p class="mt-2 font-semibold">{{ $sr->contact_name }}</p>
                <p class="text-sm text-navy-500">{{ $sr->contact_phone }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="tel:+{{ zm_phone($sr->contact_phone) }}" class="btn btn-navy"><x-icon name="phone" class="h-4 w-4" /> Call</a>
                    <a href="{{ whatsapp_link($sr->contact_phone, 'Hello '.$sr->contact_name.', this is '.auth()->user()->name.' from HANDY NOW about request '.$sr->reference) }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-icon name="whatsapp" class="h-4 w-4" /> WhatsApp</a>
                </div>
                @endunless
                @if($sr->agreed_amount)<p class="mt-4 rounded-xl bg-mist p-3 text-sm">{{ $sr->isFixedPrice() ? 'Fixed price' : 'Agreed price' }}: <b>{{ zmw($sr->agreed_amount) }}</b></p>@endif
                @foreach($sr->contactUnlocked() ? $sr->nextProviderStatuses() : [] as $next)
                    <form method="POST" action="{{ route('provider.jobs.status', $sr) }}" class="mt-4 space-y-2">
                        @csrf <input type="hidden" name="status" value="{{ $next }}">
                        @if($next === 'completed')<input name="note" class="input" placeholder="Work summary (optional)">@endif
                        <button class="btn btn-primary btn-lg w-full">{{ $labels[$next] ?? $next }}</button>
                    </form>
                @endforeach
                @if(in_array($sr->status, ['en_route', 'accepted']))
                    <div class="mt-3" x-data="shareLocation({ url: '{{ route('provider.location') }}', auto: true })">
                        <p class="text-xs text-navy-500" x-show="status === 'sharing'">📍 Sharing live location with the customer</p>
                        <p class="text-xs text-red-600" x-show="status === 'denied'" x-cloak>Allow location access so the customer can track your arrival.</p>
                    </div>
                @endif
                @if(in_array($sr->status, ['provider_assigned', 'accepted']))
                    <details class="mt-4"><summary class="cursor-pointer text-sm font-semibold text-red-600">Can't do this job?</summary>
                        <form method="POST" action="{{ route('provider.jobs.release', $sr) }}" class="mt-2 space-y-2">@csrf<input name="reason" class="input" required placeholder="Reason"><button class="btn btn-sm btn-danger w-full">Withdraw from job</button></form>
                    </details>
                @endif
            </div>
            @if($sr->contactUnlocked() && ($sr->variations->isNotEmpty() || in_array($sr->status, ['arrived', 'in_progress'])) && $sr->payment_status === 'unpaid')
                @php $pendingVar = $sr->variations->firstWhere('status', 'pending'); @endphp
                <div class="card card-pad">
                    <h2 class="font-display text-lg font-bold">Extra costs</h2>
                    @foreach($sr->variations as $v)
                        <p class="mt-2 flex items-center justify-between gap-2 text-sm"><span>+{{ zmw($v->amount) }} – {{ $v->reason }}</span><x-status-badge :status="$v->status === 'approved' ? 'confirmed' : ($v->status === 'declined' ? 'cancelled' : 'pending')" :label="ucfirst($v->status)" /></p>
                    @endforeach
                    @if($pendingVar)
                        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Waiting for the customer to approve. They have been notified.</p>
                    @elseif(in_array($sr->status, ['arrived', 'in_progress']))
                        <p class="mt-2 text-xs text-navy-500">Found something that costs more than agreed? The customer must approve it in the app before it counts. Don't agree extra cash on the side.</p>
                        <form method="POST" action="{{ route('provider.jobs.variation', $sr) }}" class="mt-3 space-y-2">
                            @csrf
                            <x-field name="amount" type="number" step="0.01" min="1" label="Extra amount (ZMW)" :required="true" />
                            <x-field name="reason" label="What is the extra work or part?" :required="true" placeholder="e.g. Replace burst valve" />
                            <button class="btn btn-navy btn-sm w-full">Ask customer to approve</button>
                        </form>
                    @endif
                </div>
            @endif
            @if($pendingPayment)
                <div class="card card-pad">
                    <h2 class="font-display text-lg font-bold">Confirm payment</h2>
                    <p class="mt-2 text-sm">Customer recorded <b>{{ zmw($pendingPayment->amount) }}</b> via {{ $pendingPayment->methodLabel() }} @if($pendingPayment->transaction_reference)(ref {{ $pendingPayment->transaction_reference }})@endif.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <form method="POST" action="{{ route('provider.payments.confirm', $pendingPayment) }}">@csrf<button class="btn btn-success w-full">Received</button></form>
                        <form method="POST" action="{{ route('provider.payments.reject', $pendingPayment) }}">@csrf<button class="btn btn-outline w-full">Not received</button></form>
                    </div>
                </div>
            @elseif($awaitingPrompt)
                <div class="card card-pad text-sm"><p class="font-bold text-sky-700">Waiting for the customer to approve the mobile money prompt…</p><p class="mt-1 text-navy-500">It confirms automatically. No action needed.</p></div>
            @elseif($sr->payment_status === 'paid')
                <div class="card card-pad text-sm"><p class="font-bold text-emerald-700">✓ Paid</p><p class="mt-1">Commission: {{ zmw($sr->commission_amount) }} · You earn <b>{{ zmw($sr->provider_earnings) }}</b></p></div>
            @endif
        @elseif($sr->isOpen() && $sr->isFixedPrice())
            @php $rate = (float) \App\Models\Setting::get('commission_rate', 10); $net = $sr->fixed_price * (1 - $rate / 100); @endphp
            <div class="card card-pad border-2 border-emerald-400">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Fixed-price job</p>
                <p class="mt-1 font-display text-4xl font-extrabold">{{ zmw($sr->fixed_price, 0) }}</p>
                <p class="mt-1 text-sm text-navy-500">Customer pays this price. If paid through the app you earn <b>{{ zmw($net) }}</b> after the {{ $rate }}% commission. The first provider to accept gets the job.</p>
                <form method="POST" action="{{ route('provider.jobs.accept', $sr) }}" class="mt-4">@csrf<button class="btn btn-success btn-lg w-full">Accept this job</button></form>
                <form method="POST" action="{{ route('provider.jobs.decline', $sr) }}" class="mt-2">@csrf<button class="btn btn-ghost w-full text-sm">Not interested</button></form>
                <p class="mt-3 text-xs text-navy-300">Customer contact details unlock once they pay the booking fee.</p>
            </div>
        @elseif($sr->isOpen())
            <div class="card card-pad">
                <h2 class="font-display text-lg font-bold">{{ $myQuote ? 'Update your quotation' : 'Send a quotation' }}</h2>
                @if($myQuote)<p class="mt-1 text-sm text-navy-500">Current quote: <b>{{ zmw($myQuote->total()) }}</b> – <x-status-badge :status="$myQuote->status" :label="ucfirst($myQuote->status)" /></p>@endif
                @if($sr->service)<p class="mt-1 text-xs text-navy-300">Guide price: {{ $sr->service->priceRange() }}</p>@endif
                @if($ceiling)<p class="mt-1 text-xs font-semibold text-amber-700">Price ceiling for this service: {{ zmw($ceiling, 0) }} in total (work plus call-out). Quotes above it are not accepted.</p>@endif
                <form method="POST" action="{{ route('provider.jobs.quote', $sr) }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="amount" type="number" step="0.01" min="1" label="Work (ZMW)" :required="true" :value="$myQuote?->amount" />
                        <x-field name="callout_fee" type="number" step="0.01" min="0" label="Call-out (ZMW)" :value="$myQuote?->callout_fee ?? $sr->service?->callout_fee" />
                    </div>
                    <x-field name="eta_minutes" type="number" min="5" label="Arrival time (minutes)" :required="true" :value="$myQuote?->eta_minutes ?? 30" />
                    <x-field name="message" type="textarea" rows="3" label="Message to customer" :value="$myQuote?->message" placeholder="What's included, parts needed…" />
                    <button class="btn btn-primary w-full">{{ $myQuote ? 'Update quote' : 'Send quote' }}</button>
                </form>
                <form method="POST" action="{{ route('provider.jobs.decline', $sr) }}" class="mt-2">@csrf<button class="btn btn-ghost w-full text-sm">Not interested</button></form>
                <p class="mt-3 text-xs text-navy-300">Customer contact details are shared once your quote is accepted and the booking fee is paid.</p>
            </div>
        @else
            <div class="card card-pad text-sm text-navy-500">This job is no longer open{{ $myQuote ? ' – your quote was '.$myQuote->status : '' }}.</div>
        @endif

        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Timeline</h2>
            <ol class="mt-3 space-y-3 text-sm">
                @foreach($sr->statusLogs->reverse() as $log)
                    <li><b>{{ $log->label() }}</b> <span class="text-xs text-navy-300">{{ $log->created_at->format('d M H:i') }}</span>@if($log->note)<br><span class="text-xs text-navy-500">{{ $log->note }}</span>@endif</li>
                @endforeach
            </ol>
        </div>
        @if($sr->review)
            <div class="card card-pad"><h2 class="font-display text-lg font-bold">Customer review</h2><div class="mt-2"><x-stars :rating="$sr->review->rating" /></div><p class="mt-2 text-sm">{{ $sr->review->comment }}</p></div>
        @endif
    </aside>
</div>
@endsection
