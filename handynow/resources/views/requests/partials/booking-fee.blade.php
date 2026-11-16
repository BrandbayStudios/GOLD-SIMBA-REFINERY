{{-- Customer pays HANDY NOW's booking fee to unlock the provider's details. Needs $sr, $r, $statusUrl --}}
@php
    $onlineMethods = app(\App\Services\Payments\PaymentGatewayManager::class)->onlineMethods();
    $feePayments = $sr->payments->where('kind', 'booking_fee');
    $feePending = $feePayments->firstWhere('status', 'pending');
    $feeLast = $feePayments->first();
    $feeWaiting = $feePending && $feePending->isOnline();
    $instructions = \App\Models\Setting::get('booking_fee_instructions');
@endphp
<div class="card card-pad border-2 border-brand-500" x-data="{ retry: false }">
    <p class="text-xs font-bold uppercase tracking-wider text-brand-600">Booking fee</p>
    <h2 class="mt-1 font-display text-xl font-bold">Confirm your booking: {{ zmw($sr->booking_fee_amount) }}</h2>
    <p class="mt-1 text-sm text-navy-500">{{ $sr->provider->name }} is ready for your job. Paying the booking fee confirms the booking and unlocks their contact details so they can head to you. It is refunded if the provider cancels.</p>

    @if($feeWaiting)
        <div class="mt-3 rounded-xl bg-sky-50 p-4 text-sm text-sky-900"
             x-data="{ init() { const t = setInterval(async () => { try { const r = await fetch('{{ $statusUrl }}', { headers: { Accept: 'application/json' } }); const d = await r.json(); if (d.payment && d.payment.status !== 'pending') { clearInterval(t); location.reload(); } } catch (e) {} }, 5000); } }">
            <p class="flex items-center gap-2 font-bold"><span class="pulse-dot text-sky-600"></span> Check your phone</p>
            <p class="mt-1">We sent a {{ $feePending->methodLabel() }} prompt for <b>{{ zmw($feePending->amount) }}</b> to <b>{{ $feePending->payer_phone }}</b>. Enter your PIN to approve. This page updates by itself.</p>
            <button type="button" @click="retry = !retry" class="mt-2 text-xs font-semibold text-sky-700 underline">Didn't get the prompt? Try again or use another method</button>
        </div>
    @elseif($feePending)
        <p class="mt-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">We have your {{ $feePending->methodLabel() }} booking fee{{ $feePending->transaction_reference ? ' (ref '.$feePending->transaction_reference.')' : '' }}. Our team is confirming it, usually within a few minutes.</p>
    @elseif($feeLast && $feeLast->status === 'failed' && $feeLast->failure_reason && ! in_array($feeLast->failure_reason, ['Superseded by a new payment attempt', 'Fee waived', 'Request cancelled'], true))
        <p class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-800">Your last payment didn't go through ({{ $feeLast->failure_reason }}). Please try again.</p>
    @endif

    @if(! $feePending || $feeWaiting)
        <div @if($feeWaiting) x-show="retry" x-cloak @endif>
        <form method="POST" action="{{ $r['fee'] }}" class="mt-4 grid gap-4 sm:grid-cols-2" x-data="{ method: '{{ old('method', 'mtn_momo') }}', online: @js($onlineMethods) }">
            @csrf
            <div class="sm:col-span-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach(array_intersect_key(\App\Models\Payment::METHODS, array_flip(\App\Models\Payment::FEE_METHODS)) as $k => $label)
                    <label class="cursor-pointer rounded-xl border-2 p-3 text-center text-xs font-bold" :class="method === '{{ $k }}' ? 'border-brand-500 bg-brand-50' : 'border-navy-50'">
                        <input type="radio" name="method" value="{{ $k }}" x-model="method" class="sr-only"> {{ $label }}
                        @if(in_array($k, $onlineMethods, true))<span class="mt-1 block text-[10px] font-semibold text-emerald-600">Pay in app</span>@endif
                    </label>
                @endforeach
            </div>
            <div x-show="['mtn_momo','airtel_money','zamtel_kwacha'].includes(method)"><x-field name="payer_phone" type="tel" label="Mobile money number" :value="$sr->contact_phone" /></div>
            <div x-show="!online.includes(method)"><x-field name="transaction_reference" label="Transaction ID / reference" placeholder="From your confirmation SMS" /></div>
            <div class="sm:col-span-2 text-xs text-navy-500">
                <p x-show="online.includes(method)">You'll get a PIN prompt on this number. Your booking is confirmed as soon as you approve it.</p>
                <div x-show="!online.includes(method)">
                    @if($instructions)<p class="rounded-xl bg-mist p-3 text-sm font-semibold text-navy-800">{{ $instructions }}</p>@else<p>Pay HANDY NOW, then enter the transaction reference here. Our team will confirm it.</p>@endif
                </div>
            </div>
            <div class="sm:col-span-2"><button class="btn btn-primary w-full sm:w-auto"><span x-text="online.includes(method) ? 'Send payment prompt – {{ zmw($sr->booking_fee_amount) }}' : 'I have paid {{ zmw($sr->booking_fee_amount) }}'"></span></button></div>
        </form>
        </div>
    @endif
</div>
