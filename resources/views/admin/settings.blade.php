@extends('layouts.dashboard', ['title' => 'Platform settings'])
@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="card card-pad space-y-4">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Commission & matching</h2>
        <x-field name="commission_rate" type="number" step="0.1" label="Platform commission (%)" :value="$settings['commission_rate']" :required="true" hint="Applied automatically when a payment is confirmed." />
        <x-field name="min_payout" type="number" step="1" label="Payout threshold (ZMW)" :value="$settings['min_payout']" :required="true" hint="Providers owed at least this much are flagged as due on the Payouts page." />
        <x-field name="commission_instructions" type="textarea" rows="2" label="How providers pay you commission" :value="$settings['commission_instructions']" hint="Shown to providers who owe commission, e.g. 'Send to MTN MoMo 0972 015 689 (HANDY NOW) and quote your name.'" />
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
                <div class="flex justify-between"><dt>SMS gateway</dt><dd class="font-semibold">{{ $smsDriver instanceof \App\Services\Sms\AfricasTalkingSmsDriver ? ($smsDriver->isSandbox() ? "Africa's Talking (sandbox)" : "Africa's Talking (live)") : 'Log only (no SMS sent)' }}</dd></div>
                <div class="flex justify-between"><dt>MTN MoMo</dt><dd class="font-semibold">{{ $gatewayStatus['mtn_momo'] ? 'In-app prompt (automatic)' : 'Manual confirmation' }}</dd></div>
                <div class="flex justify-between"><dt>Airtel Money</dt><dd class="font-semibold">{{ $gatewayStatus['airtel_money'] ? 'In-app prompt (automatic)' : 'Manual confirmation' }}</dd></div>
                <div class="flex justify-between"><dt>Zamtel / bank / cash</dt><dd class="font-semibold">Manual confirmation</dd></div>
                <div class="flex justify-between"><dt>Maps</dt><dd class="font-semibold">OpenStreetMap / Leaflet</dd></div>
            </dl>

            <a href="{{ route('admin.sms-log') }}" class="btn btn-sm btn-outline mt-3">View SMS log</a>
        </div>
        <form method="POST" action="{{ route('admin.settings.migrate') }}" class="card card-pad space-y-2 text-sm">
            @csrf
            <h2 class="font-display text-lg font-bold">Database updates</h2>
            @if($pendingMigrations)
                <p class="text-amber-700">{{ $pendingMigrations }} update(s) waiting to be applied after the latest deployment.</p>
                <button class="btn btn-navy w-full">Apply database updates</button>
            @else
                <p class="text-emerald-700">Database is up to date ✓</p>
            @endif
        </form>
        <form method="POST" action="{{ route('admin.settings.password') }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <h2 class="font-display text-lg font-bold">My password</h2>
            <p class="text-sm text-navy-500">Signed in as {{ auth()->user()->email }}</p>
            <x-field name="current_password" type="password" label="Current password" :required="true" autocomplete="current-password" />
            <x-field name="password" type="password" label="New password" :required="true" autocomplete="new-password" hint="8+ characters with letters and numbers." />
            <x-field name="password_confirmation" type="password" label="Confirm new password" :required="true" autocomplete="new-password" />
            <button class="btn btn-navy w-full">Change password</button>
        </form>
        <form method="POST" action="{{ route('admin.settings.purge-demo') }}" class="card card-pad space-y-3 border-violet-200">
            @csrf
            <h2 class="font-display text-lg font-bold">Demonstration data</h2>
            <p class="text-sm text-navy-500">{{ $demo['users'] }} demo accounts and {{ $demo['requests'] }} demo requests are marked with a <span class="badge badge-demo">Demo</span> badge. Remove them before going live – live records are not affected.</p>
            <input name="confirm" class="input" placeholder="Type DELETE DEMO DATA">
            <button class="btn btn-danger w-full" @disabled(! $demo['users'] && ! $demo['requests'])>Remove all demo data</button>
        </form>
    </div>
</div>
<form method="POST" action="{{ route('admin.settings.sms') }}" class="card card-pad mt-6 max-w-5xl space-y-5">
    @csrf @method('PUT')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-display text-lg font-bold">SMS – Africa's Talking</h2>
            <p class="mt-1 max-w-2xl text-sm text-navy-500">Sends job alerts to providers and request updates to customers on MTN, Airtel and Zamtel. Use the username <b>sandbox</b> with your sandbox API key to test (messages appear in the Africa's Talking simulator), then your live app username and key.</p>
        </div>
        <div class="text-right text-sm">
            <x-status-badge :status="$smsDriver instanceof \App\Services\Sms\AfricasTalkingSmsDriver ? 'active' : 'pending'" :label="$smsDriver instanceof \App\Services\Sms\AfricasTalkingSmsDriver ? ($smsDriver->isSandbox() ? 'Sandbox' : 'Live') : 'Off – log only'" />
            @if($smsBalance)<p class="mt-1 font-semibold">Balance: {{ $smsBalance }}</p>@endif
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="at_enabled" value="1" class="accent-brand-500" @checked(filter_var($sms['at_enabled'], FILTER_VALIDATE_BOOLEAN))> Send real SMS through Africa's Talking</label>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="at_username" label="Username" :value="$sms['at_username']" placeholder="sandbox" autocomplete="off" />
        <x-field name="at_api_key" type="password" label="API key" :placeholder="$sms['at_api_key'] ? '•••••••• saved' : ''" autocomplete="off" />
        <x-field name="at_sender_id" label="Sender ID (optional)" :value="$sms['at_sender_id']" placeholder="HANDYNOW" hint="Must be approved by Africa's Talking first. Leave blank to use their default." />
    </div>
    <button class="btn btn-primary">Save SMS settings</button>
</form>
<form method="POST" action="{{ route('admin.settings.sms-test') }}" class="card card-pad mt-4 flex max-w-5xl flex-wrap items-end gap-3">
    @csrf
    <div class="min-w-56 flex-1"><x-field name="test_phone" type="tel" label="Send a test SMS to" placeholder="097 123 4567" /></div>
    <button class="btn btn-navy">Send test SMS</button>
    <a href="{{ route('admin.sms-log') }}" class="btn btn-outline">View SMS log</a>
</form>
<form method="POST" action="{{ route('admin.settings.gateways') }}" class="card card-pad mt-6 max-w-5xl space-y-6">
    @csrf @method('PUT')
    <div>
        <h2 class="font-display text-lg font-bold">Mobile money payments</h2>
        <p class="mt-1 text-sm text-navy-500">When a network is switched on, customers paying with it get a PIN prompt on their phone and the payment confirms itself, with commission calculated automatically. Switched off, the customer records the payment and the provider confirms it. Zamtel Kwacha, bank and cash always use manual confirmation. Keys are stored encrypted. Leave a key field blank to keep the saved value.</p>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <fieldset class="space-y-3 rounded-2xl border border-navy-50 p-4">
            <legend class="flex items-center gap-2 px-1 font-bold">MTN MoMo <x-status-badge :status="$gatewayStatus['mtn_momo'] ? 'active' : 'pending'" :label="$gatewayStatus['mtn_momo'] ? 'Live in app' : 'Manual'" /></legend>
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="mtn_enabled" value="1" class="accent-brand-500" @checked(filter_var($gateway['mtn_enabled'], FILTER_VALIDATE_BOOLEAN))> Enable automatic MTN MoMo payments</label>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="mtn_env">Environment</label><select id="mtn_env" name="mtn_environment" class="input"><option value="sandbox" @selected(($gateway['mtn_environment'] ?? 'sandbox') !== 'production')>Sandbox (testing)</option><option value="production" @selected($gateway['mtn_environment'] === 'production')>Production (live)</option></select></div>
                <x-field name="mtn_currency" label="Currency" :value="$gateway['mtn_currency']" placeholder="EUR sandbox / ZMW live" />
            </div>
            <x-field name="mtn_target_environment" label="Target environment" :value="$gateway['mtn_target_environment']" placeholder="sandbox / mtnzambia" hint="Given by MTN when you go live. Leave blank for the default." />
            <x-field name="mtn_subscription_key" type="password" label="Collections subscription key (Ocp-Apim)" :placeholder="$gateway['mtn_subscription_key'] ? '•••••••• saved' : ''" autocomplete="off" />
            <x-field name="mtn_api_user" label="API user (UUID)" :value="$gateway['mtn_api_user']" autocomplete="off" />
            <x-field name="mtn_api_key" type="password" label="API key" :placeholder="$gateway['mtn_api_key'] ? '•••••••• saved' : ''" autocomplete="off" />
            <p class="text-xs text-navy-300">Callback URL to register with MTN: <code>{{ route('webhooks.payments', 'mtn_momo') }}</code></p>
        </fieldset>
        <fieldset class="space-y-3 rounded-2xl border border-navy-50 p-4">
            <legend class="flex items-center gap-2 px-1 font-bold">Airtel Money <x-status-badge :status="$gatewayStatus['airtel_money'] ? 'active' : 'pending'" :label="$gatewayStatus['airtel_money'] ? 'Live in app' : 'Manual'" /></legend>
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="airtel_enabled" value="1" class="accent-brand-500" @checked(filter_var($gateway['airtel_enabled'], FILTER_VALIDATE_BOOLEAN))> Enable automatic Airtel Money payments</label>
            <div><label class="label" for="airtel_env">Environment</label><select id="airtel_env" name="airtel_environment" class="input"><option value="sandbox" @selected(($gateway['airtel_environment'] ?? 'sandbox') !== 'production')>Sandbox / UAT (testing)</option><option value="production" @selected($gateway['airtel_environment'] === 'production')>Production (live)</option></select></div>
            <x-field name="airtel_client_id" label="Client ID" :value="$gateway['airtel_client_id']" autocomplete="off" />
            <x-field name="airtel_client_secret" type="password" label="Client secret" :placeholder="$gateway['airtel_client_secret'] ? '•••••••• saved' : ''" autocomplete="off" />
            <p class="text-xs text-navy-300">Callback URL to register with Airtel: <code>{{ route('webhooks.payments', 'airtel_money') }}</code></p>
        </fieldset>
    </div>
    <button class="btn btn-primary">Save mobile money settings</button>
</form>
@endsection
