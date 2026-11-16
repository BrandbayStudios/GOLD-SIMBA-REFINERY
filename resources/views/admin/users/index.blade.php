@extends('layouts.dashboard', ['title' => 'Users'])
@section('header-actions')<a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-navy">Add admin</a>@endsection
@section('content')
<div class="flex flex-wrap items-center gap-2">
    @foreach(['customer' => 'Customers', 'provider' => 'Providers', 'admin' => 'Admins'] as $k => $l)<a href="{{ route('admin.users.index', ['role' => $k]) }}" class="tab {{ $role === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach
    <form method="GET" class="ml-auto flex gap-2"><input type="hidden" name="role" value="{{ $role }}"><select name="status" class="input w-auto"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select><input name="q" value="{{ request('q') }}" class="input" placeholder="Search"><button class="btn btn-navy">Go</button></form>
</div>
<div class="card mt-4 overflow-x-auto">
    <table class="table-x"><thead><tr><th>Name</th><th>Contact</th><th>City</th><th>Requests / jobs</th><th>Joined</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($users as $u)
            <tr>
                <td class="font-semibold">@if($u->isProvider())<a href="{{ route('admin.providers.show', $u) }}" class="text-brand-600">{{ $u->name }}</a>@else{{ $u->name }}@endif <x-demo-badge :show="$u->is_demo" /></td>
                <td class="text-xs">{{ $u->email }}<br>{{ $u->phone }}</td>
                <td>{{ $u->city ?? '—' }}</td>
                <td>{{ $u->isProvider() ? $u->provider_jobs_count : $u->customer_requests_count }}</td>
                <td class="text-xs">{{ $u->created_at->format('d M Y') }}</td>
                <td><x-status-badge :status="$u->status" :label="ucfirst($u->status)" /></td>
                <td>@if($u->id !== auth()->id())<form method="POST" action="{{ route('admin.users.status', $u) }}">@csrf<button class="btn btn-sm {{ $u->status === 'active' ? 'btn-outline text-red-600' : 'btn-success' }}" onclick="return confirm('Change account status for {{ addslashes($u->name) }}?')">{{ $u->status === 'active' ? 'Suspend' : 'Reactivate' }}</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-8 text-center text-navy-500">No users.</td></tr>
        @endforelse
    </tbody></table>
</div>
<div class="mt-6">{{ $users->links() }}</div>
@endsection
