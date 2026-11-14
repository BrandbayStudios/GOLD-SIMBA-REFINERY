@extends('layouts.dashboard', ['title' => 'My requests'])
@section('content')
<div class="flex gap-2 overflow-x-auto pb-2">
    @foreach(['all' => 'All', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $l)
        <a href="{{ route('customer.requests.index', ['filter' => $k]) }}" class="tab {{ $filter === $k ? 'active' : '' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="mt-4 space-y-3">
    @forelse($requests as $sr)
        @include('partials.request-row', ['sr' => $sr, 'href' => route('customer.requests.show', $sr)])
    @empty
        <div class="card card-pad text-center text-navy-500">No requests here. <a href="{{ route('requests.create') }}" class="font-semibold text-brand-600">Request a service →</a></div>
    @endforelse
</div>
<div class="mt-6">{{ $requests->links() }}</div>
@endsection
