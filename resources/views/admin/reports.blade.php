@extends('layouts.dashboard', ['title' => 'Reports & analytics'])
@section('content')
<form method="GET" class="card card-pad flex flex-wrap items-end gap-3">
    <div><label class="label" for="from">From</label><input id="from" type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input"></div>
    <div><label class="label" for="to">To</label><input id="to" type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input"></div>
    <button class="btn btn-navy">Apply</button>
    <a href="{{ route('admin.reports.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="btn btn-outline"><x-icon name="download" class="h-4 w-4" /> Export CSV</a>
</form>
<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
    <x-stat label="Requests" :value="$summary['requests']" icon="list" />
    <x-stat label="Completed" :value="$summary['completed']" icon="check-circle" tone="green" />
    <x-stat label="Completion rate" :value="$summary['completion_rate'].'%'" icon="chart" />
    <x-stat label="Emergencies" :value="$summary['emergency']" icon="siren" tone="red" />
    <x-stat label="Revenue" :value="zmw($summary['revenue'], 0)" icon="wallet" tone="green" />
    <x-stat label="Commission" :value="zmw($summary['commission'], 0)" icon="tag" tone="violet" />
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="card card-pad"><h2 class="font-display text-lg font-bold">Requests per day</h2><div class="mt-6">@include('partials.bar-chart', ['data' => $trend, 'key' => 'requests', 'color' => 'bg-navy-900'])</div></div>
    <div class="card card-pad"><h2 class="font-display text-lg font-bold">Revenue per day</h2><div class="mt-6">@include('partials.bar-chart', ['data' => $trend, 'key' => 'revenue', 'format' => fn ($v) => zmw($v, 0)])</div></div>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="card overflow-x-auto">
        <h2 class="p-5 font-display text-lg font-bold">By category</h2>
        <table class="table-x"><thead><tr><th>Category</th><th>Requests</th><th>Completed</th><th>Paid value</th><th>Commission</th></tr></thead><tbody>
            @foreach($byCategory as $c)<tr><td class="font-semibold">{{ $c->name }}</td><td>{{ $c->total }}</td><td>{{ $c->completed }}</td><td>{{ zmw($c->revenue ?? 0, 0) }}</td><td>{{ zmw($c->commission ?? 0, 0) }}</td></tr>@endforeach
        </tbody></table>
    </div>
    <div class="space-y-6">
        <div class="card overflow-x-auto">
            <h2 class="p-5 font-display text-lg font-bold">Payment methods</h2>
            <table class="table-x"><thead><tr><th>Method</th><th>Payments</th><th>Total</th></tr></thead><tbody>
                @forelse($byMethod as $m)<tr><td>{{ \App\Models\Payment::METHODS[$m->method] ?? $m->method }}</td><td>{{ $m->c }}</td><td>{{ zmw($m->total, 0) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-navy-500">No payments in this period.</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="card overflow-x-auto">
            <h2 class="p-5 font-display text-lg font-bold">Top providers</h2>
            <table class="table-x"><thead><tr><th>Provider</th><th>Jobs</th><th>Value</th><th>Rating</th></tr></thead><tbody>
                @forelse($topProviders as $u)<tr><td><a href="{{ route('admin.providers.show', $u) }}" class="font-semibold text-brand-600">{{ $u->name }}</a></td><td>{{ $u->jobs }}</td><td>{{ zmw($u->earned ?? 0, 0) }}</td><td>{{ number_format($u->providerProfile?->rating_avg ?? 0, 1) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-navy-500">No completed jobs in this period.</td></tr>@endforelse
            </tbody></table>
        </div>
    </div>
</div>
@endsection
