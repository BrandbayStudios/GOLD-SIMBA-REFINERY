@extends('layouts.dashboard', ['title' => 'Disputes & complaints'])
@section('content')
<div class="flex flex-wrap gap-2">
    <a href="{{ route('admin.complaints.index') }}" class="tab {{ ! $status ? 'active' : '' }}">All</a>
    @foreach(\App\Models\Complaint::STATUSES as $k => $l)<a href="{{ route('admin.complaints.index', ['status' => $k]) }}" class="tab {{ $status === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach
</div>
<div class="card mt-4 overflow-x-auto">
    <table class="table-x"><thead><tr><th>Ref</th><th>Subject</th><th>From</th><th>Against</th><th>Request</th><th>Status</th><th>Date</th></tr></thead><tbody>
        @forelse($complaints as $c)
            <tr>
                <td><a href="{{ route('admin.complaints.show', $c) }}" class="font-bold text-brand-600">{{ $c->reference }}</a></td>
                <td>{{ $c->subject }} <x-demo-badge :show="$c->is_demo" /></td>
                <td>{{ $c->user->name }}</td>
                <td>{{ $c->againstUser?->name ?? '—' }}</td>
                <td>{{ $c->serviceRequest?->reference ?? '—' }}</td>
                <td><x-status-badge :status="$c->status" :label="\App\Models\Complaint::STATUSES[$c->status]" /></td>
                <td class="text-xs">{{ $c->created_at->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-8 text-center text-navy-500">No complaints.</td></tr>
        @endforelse
    </tbody></table>
</div>
<div class="mt-6">{{ $complaints->links() }}</div>
@endsection
