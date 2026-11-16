@extends('layouts.dashboard', ['title' => 'Jobs'])
@section('content')
<div class="flex gap-2 overflow-x-auto pb-2">
    @foreach(['new' => 'New alerts', 'quoted' => 'Quoted', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $l)
        <a href="{{ route('provider.jobs.index', ['tab' => $k]) }}" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="mt-4 space-y-3">
    @forelse($jobs as $sr)
        @include('partials.request-row', ['sr' => $sr, 'href' => route('provider.jobs.show', $sr)])
    @empty
        <div class="card card-pad text-center text-navy-500">Nothing here yet.</div>
    @endforelse
</div>
<div class="mt-6">{{ $jobs->links() }}</div>
@endsection
