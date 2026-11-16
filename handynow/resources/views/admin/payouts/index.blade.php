@extends('layouts.dashboard', ['title' => 'Payouts & balances'])
@section('header-actions')<a href="{{ route('admin.payouts.export') }}" class="btn btn-sm btn-outline"><x-icon name="download" class="h-4 w-4" /> Export CSV</a>@endsection
@section('content')
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Owed to providers" :value="zmw($summary['owed_to_providers'], 0)" icon="wallet" tone="orange" hint="paid through the app" />
    <x-stat label="Due now" :value="zmw($summary['due_now_amount'], 0)" icon="alert" tone="red" :hint="$summary['due_now_count'].' provider(s) over '.zmw($summary['min_payout'], 0)" />
    <x-stat label="Owed by providers" :value="zmw($summary['owed_by_providers'], 0)" icon="tag" tone="violet" hint="commission on direct payments" />
    <x-stat label="Paid out this month" :value="zmw($summary['paid_this_month'], 0)" icon="check-circle" tone="green" :hint="zmw($summary['pending_payouts'], 0).' waiting to be sent'" />
</div>

@if($pending->isNotEmpty())
<section class="card mt-6 overflow-x-auto">
    <h2 class="p-5 font-display text-lg font-bold">Payouts to send ({{ $pending->count() }})</h2>
    <table class="table-x">
        <thead><tr><th>Ref</th><th>Provider</th><th>Send</th><th>To</th><th>Created</th><th>Confirm</th></tr></thead>
        <tbody>
        @foreach($pending as $po)
            <tr x-data="{ cancel: false }">
                <td class="font-bold">{{ $po->reference }}</td>
                <td><a href="{{ route('admin.payouts.provider', $po->provider) }}" class="font-semibold text-brand-600">{{ $po->provider->name }}</a></td>
                <td class="font-bold">{{ zmw($po->amount) }}</td>
                <td class="text-xs">{{ $po->destination }}<br><span class="text-navy-300">{{ $po->account_name }}</span></td>
                <td class="text-xs">{{ $po->created_at->format('d M H:i') }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.payouts.paid', $po) }}" class="flex gap-2">@csrf
                        <input name="transaction_reference" class="input w-44 py-2" placeholder="Transaction ref" required>
                        <button class="btn btn-sm btn-success">Mark paid</button>
                        <button type="button" @click="cancel = !cancel" class="btn btn-sm btn-outline">Cancel</button>
                    </form>
                    <form x-show="cancel" x-cloak method="POST" action="{{ route('admin.payouts.cancel', $po) }}" class="mt-2 flex gap-2">@csrf
                        <input name="reason" class="input py-2" placeholder="Why cancel?" required><button class="btn btn-sm btn-danger">Cancel payout</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif

<div class="mt-6 flex flex-wrap gap-2">
    @foreach(['all' => 'All balances', 'due' => 'Due to pay', 'owing' => 'Owe commission'] as $k => $l)<a href="{{ route('admin.payouts.index', ['show' => $k]) }}" class="tab {{ $show === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach
</div>
<div class="card mt-3 overflow-x-auto">
    <table class="table-x">
        <thead><tr><th>Provider</th><th>Paid via</th><th>Earned in app</th><th>Commission owed</th><th>Sent to provider</th><th>Balance</th><th></th></tr></thead>
        <tbody>
        @forelse($providers as $u)
            @php $profile = $u->providerProfile; @endphp
            <tr>
                <td><a href="{{ route('admin.payouts.provider', $u) }}" class="font-bold text-navy-900">{{ $u->name }}</a> <x-demo-badge :show="$u->is_demo" /></td>
                <td class="text-xs">{{ $profile?->payoutLabel() }}</td>
                <td>{{ zmw($u->earned ?? 0) }}</td>
                <td>{{ zmw(abs($u->commission_owed ?? 0)) }}</td>
                <td>{{ zmw(abs($u->sent ?? 0)) }}</td>
                <td class="font-bold {{ $u->balance > 0 ? 'text-emerald-700' : ($u->balance < 0 ? 'text-red-600' : '') }}">
                    {{ $u->balance < 0 ? '−' : '' }}{{ zmw(abs($u->balance)) }}
                    <span class="block text-[11px] font-semibold text-navy-300">{{ $u->balance > 0 ? 'we owe them' : ($u->balance < 0 ? 'they owe us' : 'settled') }}</span>
                </td>
                <td><a href="{{ route('admin.payouts.provider', $u) }}" class="btn btn-sm {{ $u->balance >= $summary['min_payout'] ? 'btn-primary' : 'btn-outline' }}">{{ $u->balance > 0 ? 'Pay' : ($u->balance < 0 ? 'Collect' : 'Ledger') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-8 text-center text-navy-500">No provider balances yet. They appear when a customer's payment is confirmed.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($recent->isNotEmpty())
<section class="card mt-6 overflow-x-auto">
    <h2 class="p-5 font-display text-lg font-bold">Recent payouts</h2>
    <table class="table-x"><thead><tr><th>Ref</th><th>Provider</th><th>Amount</th><th>Paid to</th><th>Transaction</th><th>Status</th><th>Date</th></tr></thead><tbody>
        @foreach($recent as $po)
            <tr><td class="font-bold">{{ $po->reference }}</td><td>{{ $po->provider->name }}</td><td>{{ zmw($po->amount) }}</td><td class="text-xs">{{ $po->destination }}</td><td class="text-xs">{{ $po->transaction_reference ?? '—' }}</td>
                <td><x-status-badge :status="$po->status === 'paid' ? 'confirmed' : 'cancelled'" :label="ucfirst($po->status)" /></td><td class="text-xs">{{ ($po->paid_at ?? $po->cancelled_at ?? $po->created_at)->format('d M Y') }}</td></tr>
        @endforeach
    </tbody></table>
</section>
@endif
@endsection
