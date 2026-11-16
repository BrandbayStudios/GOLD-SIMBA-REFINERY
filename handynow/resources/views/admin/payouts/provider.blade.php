@extends('layouts.dashboard', ['title' => $provider->name.' – ledger'])
@section('header-actions')<a href="{{ route('admin.providers.show', $provider) }}" class="btn btn-sm btn-outline">Provider profile</a>@endsection
@section('content')
<div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
    <div class="space-y-6">
        <div class="card card-pad">
            <p class="text-xs font-bold uppercase tracking-wider text-navy-300">Balance</p>
            <p class="mt-1 font-display text-4xl font-extrabold {{ $balance > 0 ? 'text-emerald-700' : ($balance < 0 ? 'text-red-600' : '') }}">{{ $balance < 0 ? '−' : '' }}{{ zmw(abs($balance)) }}</p>
            <p class="text-sm font-semibold text-navy-500">{{ $balance > 0 ? 'HANDY NOW owes '.$provider->name : ($balance < 0 ? $provider->name.' owes HANDY NOW (commission)' : 'All settled') }}</p>
            <div class="mt-4 rounded-xl bg-mist p-3 text-sm">
                <p class="text-xs font-bold uppercase text-navy-300">Pay to</p>
                <p class="mt-1 font-semibold">{{ $profile->payoutLabel() }}</p>
                <p class="text-navy-500">{{ $profile->payout_account_name }}@if($profile->payout_method === 'bank_transfer') · acct {{ $profile->bank_account_number }}{{ $profile->bank_branch ? ' · '.$profile->bank_branch : '' }}@endif</p>
                @unless($profile->hasPayoutDetails())<p class="mt-1 font-semibold text-red-600">No payment method set.</p>@endunless
            </div>
        </div>

        @if($balance > 0)
        <form method="POST" action="{{ route('admin.payouts.create', $provider) }}" class="card card-pad space-y-3">
            @csrf
            <h2 class="font-display text-lg font-bold">Create a payout</h2>
            <p class="text-sm text-navy-500">Reserves the amount. Send the money to the account above, then confirm it on the Payouts page.</p>
            <x-field name="amount" type="number" step="0.01" min="0.01" :max="$balance" label="Amount (ZMW)" :value="number_format($balance, 2, '.', '')" :required="true" />
            <x-field name="note" label="Note (optional)" />
            <button class="btn btn-primary w-full" @disabled(! $profile->hasPayoutDetails())>Create payout</button>
        </form>
        @elseif($balance < 0)
        <form method="POST" action="{{ route('admin.payouts.commission', $provider) }}" class="card card-pad space-y-3">
            @csrf
            <h2 class="font-display text-lg font-bold">Record commission received</h2>
            <x-field name="amount" type="number" step="0.01" min="0.01" :max="abs($balance)" label="Amount (ZMW)" :value="number_format(abs($balance), 2, '.', '')" :required="true" />
            <div class="grid grid-cols-2 gap-3"><x-field name="method" label="Paid by" placeholder="MTN MoMo / cash" /><x-field name="reference" label="Reference" /></div>
            <button class="btn btn-success w-full">Record payment</button>
        </form>
        @endif

        <details class="card card-pad">
            <summary class="cursor-pointer text-sm font-bold">Make an adjustment</summary>
            <form method="POST" action="{{ route('admin.payouts.adjust', $provider) }}" class="mt-3 space-y-3">
                @csrf
                <p class="text-xs text-navy-500">For refunds, disputes or corrections. Positive means HANDY NOW owes the provider more; negative means less.</p>
                <x-field name="amount" type="number" step="0.01" label="Amount (ZMW, + or −)" :required="true" />
                <x-field name="reason" label="Reason" :required="true" />
                <button class="btn btn-navy w-full">Record adjustment</button>
            </form>
        </details>
    </div>

    <div class="min-w-0 space-y-6">
        <div class="card overflow-x-auto">
            <h2 class="p-5 font-display text-lg font-bold">Ledger</h2>
            <table class="table-x"><thead><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead><tbody>
                @forelse($entries as $e)
                    <tr>
                        <td class="whitespace-nowrap text-xs">{{ $e->created_at->format('d M Y H:i') }}</td>
                        <td><span class="badge {{ $e->amount > 0 ? 'badge-green' : 'badge-grey' }}">{{ $e->label() }}</span></td>
                        <td class="min-w-64 text-xs">{{ $e->description }}@if($e->creator)<span class="text-navy-300"> · {{ $e->creator->name }}</span>@endif</td>
                        <td class="text-right font-bold {{ $e->amount > 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $e->amount > 0 ? '+' : '−' }}{{ zmw(abs($e->amount)) }}</td>
                        <td class="text-right">{{ $e->running < 0 ? '−' : '' }}{{ zmw(abs($e->running)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-navy-500">Nothing yet.</td></tr>
                @endforelse
            </tbody></table>
        </div>
        @if($payouts->isNotEmpty())
        <div class="card overflow-x-auto">
            <h2 class="p-5 font-display text-lg font-bold">Payouts</h2>
            <table class="table-x"><thead><tr><th>Ref</th><th>Amount</th><th>Paid to</th><th>Transaction</th><th>Status</th></tr></thead><tbody>
                @foreach($payouts as $po)<tr><td class="font-bold">{{ $po->reference }}</td><td>{{ zmw($po->amount) }}</td><td class="text-xs">{{ $po->destination }}</td><td class="text-xs">{{ $po->transaction_reference ?? '—' }}</td><td><x-status-badge :status="$po->status === 'paid' ? 'confirmed' : ($po->status === 'pending' ? 'pending' : 'cancelled')" :label="ucfirst($po->status)" /></td></tr>@endforeach
            </tbody></table>
        </div>
        @endif
    </div>
</div>
@endsection
