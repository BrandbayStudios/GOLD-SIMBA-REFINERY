@extends('layouts.dashboard', ['title' => 'Service providers'])
@section('content')
<div class="flex flex-wrap items-center gap-2">
    @foreach(['all' => 'All', 'pending' => 'Pending verification', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l)
        <a href="{{ route('admin.providers.index', ['status' => $k]) }}" class="tab {{ $status === $k ? 'active' : '' }}">{{ $l }}</a>
    @endforeach
    <form method="GET" class="ml-auto flex gap-2"><input type="hidden" name="status" value="{{ $status }}"><input name="q" value="{{ request('q') }}" class="input" placeholder="Name, phone, NRC"><button class="btn btn-navy">Search</button></form>
</div>
<div class="card mt-4 overflow-x-auto">
    <table class="table-x"><thead><tr><th>Provider</th><th>Categories</th><th>NRC</th><th>Verification</th><th>Availability</th><th>Rating</th><th>Account</th></tr></thead><tbody>
        @forelse($providers as $u)
            @php $p = $u->providerProfile; @endphp
            <tr>
                <td><a href="{{ route('admin.providers.show', $u) }}" class="flex items-center gap-3"><img src="{{ $u->avatarUrl() }}" class="h-9 w-9 rounded-lg" alt=""><span><span class="block font-bold text-navy-900">{{ $u->name }}</span><span class="text-xs text-navy-300">{{ $p?->business_name ?? $u->phone }}</span></span></a></td>
                <td class="max-w-56 text-xs">{{ $p?->categories->pluck('name')->join(', ') }}</td>
                <td class="text-xs">{{ $p?->nrc_number }}</td>
                <td><x-status-badge :status="$p?->verification_status ?? 'pending'" :label="ucfirst($p?->verification_status ?? 'pending')" /></td>
                <td>{{ $p?->is_online ? 'Online' : 'Offline' }}</td>
                <td>{{ number_format($p?->rating_avg ?? 0, 1) }}</td>
                <td><x-status-badge :status="$u->status" :label="ucfirst($u->status)" /> <x-demo-badge :show="$u->is_demo" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-8 text-center text-navy-500">No providers.</td></tr>
        @endforelse
    </tbody></table>
</div>
<div class="mt-6">{{ $providers->links() }}</div>
@endsection
