@extends($guest ? 'layouts.app' : 'layouts.dashboard', ['title' => 'Request '.$sr->reference])
@section('heading', 'Request '.$sr->reference)
@php
    $r = $guest
        ? ['accept' => fn ($q) => route('track.quotes.accept', [$sr->reference, $q]), 'reject' => fn ($q) => route('track.quotes.reject', [$sr->reference, $q]),
           'cancel' => route('track.cancel', $sr->reference), 'pay' => route('track.pay', $sr->reference), 'review' => route('track.review', $sr->reference), 'live' => route('track.live', $sr->reference)]
        : ['accept' => fn ($q) => route('customer.quotes.accept', [$sr, $q]), 'reject' => fn ($q) => route('customer.quotes.reject', [$sr, $q]),
           'cancel' => route('customer.requests.cancel', $sr), 'pay' => route('customer.requests.pay', $sr), 'review' => route('customer.requests.review', $sr), 'live' => route('track.live', $sr->reference)];
    $steps = ['Submitted', 'Searching', 'Assigned', 'Accepted', 'En route', 'Arrived', 'In progress', 'Completed'];
    $pendingQuotes = $sr->quotations->where('status', 'pending');
    $provider = $sr->provider;
    $pendingPayment = $sr->payments->firstWhere('status', 'pending');
@endphp
@section('content')
<div class="{{ $guest ? 'container-x py-8' : '' }}">

    {{-- Header --}}
    <div class="card card-pad">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-priority-badge :priority="$sr->priority" />
                    <x-status-badge :status="$sr->status" />
                    <x-demo-badge :show="$sr->is_demo" />
                </div>
                <h1 class="mt-3 font-display text-2xl font-bold">{{ $sr->service?->name ?? $sr->category->name }}</h1>
                <p class="text-sm text-navy-500">Ref <b>{{ $sr->reference }}</b> · {{ $sr->category->name }} · submitted {{ $sr->created_at->diffForHumans() }}</p>
            </div>
            @if($sr->agreed_amount)
                <div class="text-right"><p class="text-xs font-semibold uppercase text-navy-300">Agreed price</p><p class="font-display text-2xl font-bold">{{ zmw($sr->agreed_amount) }}</p><x-status-badge :status="$sr->payment_status" :label="ucfirst($sr->payment_status)" /></div>
            @endif
        </div>

        @unless($sr->isCancelled())
        <ol class="mt-6 grid grid-cols-4 gap-y-4 sm:grid-cols-8">
            @foreach($steps as $i => $s)
                <li class="flex flex-col items-center text-center">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold {{ $i < $sr->progressStep() ? 'bg-emerald-500 text-white' : ($i === $sr->progressStep() ? 'bg-brand-500 text-white ring-4 ring-brand-100' : 'bg-mist text-navy-300') }}">
                        @if($i < $sr->progressStep())<x-icon name="check" class="h-4 w-4" :stroke="3" />@else{{ $i + 1 }}@endif
                    </span>
                    <span class="mt-1.5 text-[11px] font-semibold {{ $i <= $sr->progressStep() ? 'text-navy-900' : 'text-navy-300' }}">{{ $s }}</span>
                </li>
            @endforeach
        </ol>
        @else
            <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-medium text-red-700">This request was cancelled. {{ $sr->cancel_reason }}</p>
        @endunless
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            {{-- Searching state --}}
            @if($sr->isOpen())
                <div class="card card-pad">
                    <div class="flex items-center gap-3">
                        <span class="pulse-dot text-brand-500"></span>
                        <h2 class="font-display text-lg font-bold">{{ $pendingQuotes->count() ? $pendingQuotes->count().' quotation(s) received' : 'Finding available providers near you…' }}</h2>
                    </div>
                    <p class="mt-1 text-sm text-navy-500">This page updates automatically. Compare quotes and accept the one you prefer.</p>
                    <div class="mt-5 space-y-3">
                        @forelse($pendingQuotes as $q)
                            @php $pp = $q->provider->providerProfile; @endphp
                            <div class="rounded-2xl border border-navy-50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $q->provider->avatarUrl() }}" alt="" class="h-12 w-12 rounded-xl">
                                        <div>
                                            <p class="flex items-center gap-1 font-bold">{{ $q->provider->name }} <x-icon name="shield" class="h-4 w-4 text-sky-500" /></p>
                                            <p class="flex items-center gap-1 text-xs text-navy-500"><x-stars :rating="$pp?->rating_avg ?? 0" size="h-3 w-3" /> {{ number_format($pp?->rating_avg ?? 0, 1) }} · {{ $pp?->jobs_completed ?? 0 }} jobs</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-display text-2xl font-bold">{{ zmw($q->total()) }}</p>
                                        <p class="text-xs text-navy-500">Arrives in ~{{ $q->eta_minutes }} min</p>
                                    </div>
                                </div>
                                @if($q->callout_fee > 0)<p class="mt-2 text-xs text-navy-500">Includes call-out fee of {{ zmw($q->callout_fee) }} + work {{ zmw($q->amount) }}</p>@endif
                                @if($q->message)<p class="mt-2 rounded-xl bg-mist p-3 text-sm">“{{ $q->message }}”</p>@endif
                                <div class="mt-3 flex gap-2">
                                    <form method="POST" action="{{ $r['accept']($q) }}" class="flex-1">@csrf<button class="btn btn-success w-full">Accept quote</button></form>
                                    <form method="POST" action="{{ $r['reject']($q) }}">@csrf<button class="btn btn-outline">Decline</button></form>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-mist p-6 text-center text-sm text-navy-500">
                                <x-icon name="bell" class="mx-auto h-8 w-8 text-navy-300" />
                                <p class="mt-2">{{ $sr->status === 'searching' ? 'Nearby providers have been alerted. Quotes usually arrive within a few minutes.' : 'No providers are online near you yet. Our support team has been alerted and will assign someone.' }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- Assigned provider --}}
            @if($provider && ! $sr->isCancelled())
                <div class="card card-pad">
                    <h2 class="font-display text-lg font-bold">Your provider</h2>
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <img src="{{ $provider->avatarUrl() }}" alt="" class="h-16 w-16 rounded-2xl">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1 text-lg font-bold">{{ $provider->name }} <x-icon name="shield" class="h-4 w-4 text-sky-500" /></p>
                            @if($provider->providerProfile?->business_name)<p class="text-sm text-navy-500">{{ $provider->providerProfile->business_name }}</p>@endif
                            <p class="flex items-center gap-1 text-sm"><x-stars :rating="$provider->providerProfile?->rating_avg ?? 0" /> {{ number_format($provider->providerProfile?->rating_avg ?? 0, 1) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="tel:+{{ zm_phone($provider->phone) }}" class="btn btn-navy"><x-icon name="phone" class="h-4 w-4" /> Call</a>
                            <a href="{{ whatsapp_link($provider->providerProfile?->whatsapp ?: $provider->phone, 'Hi '.$provider->name.', this is about HANDY NOW request '.$sr->reference) }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-icon name="whatsapp" class="h-4 w-4" /> WhatsApp</a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Live map --}}
            @if($sr->latitude && $sr->isActive())
                <div class="card overflow-hidden" x-data="trackMap({ url: '{{ $r['live'] }}', customer: { lat: {{ $sr->latitude }}, lng: {{ $sr->longitude }} } })">
                    <div class="flex items-center justify-between p-4">
                        <h2 class="font-display text-lg font-bold">Live tracking</h2>
                        <p class="text-sm font-semibold text-brand-600" x-show="data?.provider" x-cloak>
                            <span x-text="data?.provider?.distance_km !== null ? data?.provider?.distance_km + ' km away' : ''"></span>
                            <span x-show="data?.provider?.eta_minutes"> · ETA ~<span x-text="data?.provider?.eta_minutes"></span> min</span>
                        </p>
                    </div>
                    <div x-ref="map" class="h-80"></div>
                    <p class="p-3 text-xs text-navy-300" x-show="data?.provider?.updated" x-cloak>Provider location updated <span x-text="data?.provider?.updated"></span></p>
                </div>
            @endif

            {{-- Chat (logged-in customers) --}}
            @if(! $guest && $provider && $sr->isActive())
                @include('partials.chat', ['sr' => $sr])
            @endif

            {{-- Payment --}}
            @if($provider && $sr->agreed_amount && $sr->payment_status !== 'paid' && in_array($sr->status, ['arrived', 'in_progress', 'completed']))
                <div class="card card-pad">
                    <h2 class="font-display text-lg font-bold">Payment</h2>
                    @if($pendingPayment)
                        <p class="mt-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">You recorded a {{ $pendingPayment->methodLabel() }} payment of <b>{{ zmw($pendingPayment->amount) }}</b>. Waiting for {{ $provider->name }} to confirm receipt.</p>
                    @else
                        <p class="mt-1 text-sm text-navy-500">Pay <b>{{ zmw($sr->agreed_amount) }}</b> to your provider using any method below, then record it here.</p>
                        <form method="POST" action="{{ $r['pay'] }}" class="mt-4 grid gap-4 sm:grid-cols-2" x-data="{ method: '{{ old('method', 'mtn_momo') }}' }">
                            @csrf
                            <div class="sm:col-span-2 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                @foreach(\App\Models\Payment::METHODS as $k => $label)
                                    <label class="cursor-pointer rounded-xl border-2 p-3 text-center text-xs font-bold" :class="method === '{{ $k }}' ? 'border-brand-500 bg-brand-50' : 'border-navy-50'">
                                        <input type="radio" name="method" value="{{ $k }}" x-model="method" class="sr-only"> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <div x-show="['mtn_momo','airtel_money','zamtel_kwacha'].includes(method)"><x-field name="payer_phone" type="tel" label="Number you paid from" :value="$sr->contact_phone" /></div>
                            <div x-show="method !== 'cash'"><x-field name="transaction_reference" label="Transaction ID / reference" placeholder="From your confirmation SMS" /></div>
                            <div class="sm:col-span-2"><button class="btn btn-primary w-full sm:w-auto">Record payment of {{ zmw($sr->agreed_amount) }}</button></div>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Review --}}
            @if($sr->isCompleted() && $provider)
                <div class="card card-pad">
                    @if($sr->review)
                        <h2 class="font-display text-lg font-bold">Your rating</h2>
                        <div class="mt-2"><x-stars :rating="$sr->review->rating" size="h-6 w-6" /></div>
                        @if($sr->review->comment)<p class="mt-2 text-navy-700">“{{ $sr->review->comment }}”</p>@endif
                    @else
                        <h2 class="font-display text-lg font-bold">How did {{ $provider->name }} do?</h2>
                        <form method="POST" action="{{ $r['review'] }}" class="mt-4 space-y-4" x-data="{ rating: {{ (int) old('rating', 0) }} }">
                            @csrf
                            <input type="hidden" name="rating" :value="rating">
                            <div class="flex gap-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <button type="button" @click="rating = {{ $i }}" aria-label="{{ $i }} stars"><svg class="h-9 w-9" :class="rating >= {{ $i }} ? 'text-brand-500' : 'text-navy-100'" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
                                @endfor
                            </div>
                            <x-field name="comment" type="textarea" rows="3" placeholder="Tell others about your experience (optional)" />
                            <button class="btn btn-primary" :disabled="!rating">Submit rating</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <aside class="space-y-6">
            <div class="card card-pad text-sm">
                <h2 class="font-display text-lg font-bold">Request details</h2>
                <p class="mt-3 whitespace-pre-line text-navy-700">{{ $sr->description }}</p>
                <dl class="mt-4 space-y-2">
                    <div class="flex gap-2"><x-icon name="map-pin" class="h-4 w-4 shrink-0 text-navy-300" /><dd>{{ $sr->address }}@if($sr->directions)<br><span class="text-navy-500">{{ $sr->directions }}</span>@endif</dd></div>
                    <div class="flex gap-2"><x-icon name="user" class="h-4 w-4 shrink-0 text-navy-300" /><dd>{{ $sr->contact_name }} · {{ $sr->contact_phone }}</dd></div>
                </dl>
                @if($sr->photos)
                    <div class="mt-4 grid grid-cols-3 gap-2">@foreach($sr->photoUrls() as $ph)<a href="{{ $ph }}" target="_blank"><img src="{{ $ph }}" alt="Problem photo" class="aspect-square rounded-lg object-cover"></a>@endforeach</div>
                @endif
            </div>

            <div class="card card-pad">
                <h2 class="font-display text-lg font-bold">Timeline</h2>
                <ol class="mt-4 space-y-4 border-l-2 border-navy-50 pl-4">
                    @foreach($sr->statusLogs->reverse() as $log)
                        <li class="relative">
                            <span class="absolute -left-[23px] top-1 h-3 w-3 rounded-full border-2 border-white bg-brand-500"></span>
                            <p class="text-sm font-bold">{{ $log->label() }}</p>
                            @if($log->note)<p class="text-xs text-navy-500">{{ $log->note }}</p>@endif
                            <p class="text-[11px] text-navy-300">{{ $log->created_at->format('d M Y, H:i') }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if($sr->canBeCancelledByCustomer())
                <details class="card card-pad">
                    <summary class="cursor-pointer text-sm font-bold text-red-600">Cancel this request</summary>
                    <form method="POST" action="{{ $r['cancel'] }}" class="mt-4 space-y-3">
                        @csrf
                        <x-field name="reason" label="Reason" :required="true" placeholder="e.g. Problem solved" />
                        <button class="btn btn-danger w-full" onclick="return confirm('Cancel this request?')">Cancel request</button>
                    </form>
                </details>
            @endif
            @unless($guest)
                <a href="{{ route('customer.complaints.create', ['request' => $sr->id]) }}" class="block text-center text-sm font-semibold text-navy-500 hover:text-brand-600">Report a problem with this job</a>
            @else
                <p class="text-center text-xs text-navy-300">Keep this link private – anyone with it can manage this request. <a href="{{ route('register') }}" class="font-semibold text-brand-600">Create an account</a> for a full history.</p>
            @endunless
        </aside>
    </div>
</div>
@endsection
