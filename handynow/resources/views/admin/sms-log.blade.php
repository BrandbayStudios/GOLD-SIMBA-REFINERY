@extends('layouts.dashboard', ['title' => 'SMS log'])
@section('content')
<p class="mb-4 text-sm text-navy-500">Driver: <b>{{ $driver }}</b>. {{ $driver === 'log' ? 'Messages are recorded here but not delivered. Set SMS_DRIVER=africastalking to send real SMS.' : '' }}</p>
<div class="card overflow-x-auto">
    <table class="table-x"><thead><tr><th>Time</th><th>To</th><th>Message</th><th>Status</th></tr></thead><tbody>
        @forelse($logs as $l)<tr><td class="whitespace-nowrap text-xs">{{ $l->created_at->format('d M H:i:s') }}</td><td>+{{ $l->to }}</td><td class="max-w-xl text-xs">{{ $l->message }}</td><td><x-status-badge :status="$l->status === 'failed' ? 'failed' : 'confirmed'" :label="$l->status" /></td></tr>@empty<tr><td colspan="4" class="py-8 text-center text-navy-500">No SMS yet.</td></tr>@endforelse
    </tbody></table>
</div>
<div class="mt-6">{{ $logs->links() }}</div>
@endsection
