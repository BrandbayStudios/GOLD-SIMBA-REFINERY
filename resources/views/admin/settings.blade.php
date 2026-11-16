@extends('layouts.dashboard', ['title' => 'Platform settings'])
@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="card card-pad space-y-4">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Commission & matching</h2>
        <x-field name="commission_rate" type="number" step="0.1" label="Platform commission (%)" :value="$settings['commission_rate']" :required="true" hint="Applied automatically when a payment is confirmed." />
        <x-field name="match_radius_km" type="number" label="Maximum matching radius (km)" :value="$settings['match_radius_km']" :required="true" hint="Providers further than this (or their own radius) are not alerted." />
        <h2 class="pt-2 font-display text-lg font-bold">Support contacts</h2>
        <x-field name="support_phone" label="Support phone" :value="$settings['support_phone']" :required="true" />
        <x-field name="support_whatsapp" label="Support WhatsApp (digits, e.g. 260970000000)" :value="$settings['support_whatsapp']" :required="true" />
        <x-field name="support_email" type="email" label="Support email" :value="$settings['support_email']" :required="true" />
        <button class="btn btn-primary">Save settings</button>
    </form>
    <div class="space-y-6">
        <div class="card card-pad text-sm">
            <h2 class="font-display text-lg font-bold">Integrations</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between"><dt>SMS gateway</dt><dd class="font-semibold">{{ config('services.sms.driver') === 'africastalking' ? "Africa's Talking (live)" : 'Log only (no SMS sent)' }}</dd></div>
                <div class="flex justify-between"><dt>Payments</dt><dd class="font-semibold">Manual confirmation</dd></div>
                <div class="flex justify-between"><dt>Maps</dt><dd class="font-semibold">OpenStreetMap / Leaflet</dd></div>
            </dl>
            <p class="mt-3 text-xs text-navy-500">Configure SMS in the server <code>.env</code> file (SMS_DRIVER, AFRICASTALKING_*). See README.</p>
            <a href="{{ route('admin.sms-log') }}" class="btn btn-sm btn-outline mt-3">View SMS log</a>
        </div>
        <form method="POST" action="{{ route('admin.settings.purge-demo') }}" class="card card-pad space-y-3 border-violet-200">
            @csrf
            <h2 class="font-display text-lg font-bold">Demonstration data</h2>
            <p class="text-sm text-navy-500">{{ $demo['users'] }} demo accounts and {{ $demo['requests'] }} demo requests are marked with a <span class="badge badge-demo">Demo</span> badge. Remove them before going live – live records are not affected.</p>
            <input name="confirm" class="input" placeholder="Type DELETE DEMO DATA">
            <button class="btn btn-danger w-full" @disabled(! $demo['users'] && ! $demo['requests'])>Remove all demo data</button>
        </form>
    </div>
</div>
@endsection
