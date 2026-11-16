@extends('layouts.dashboard', ['title' => $user->name])
@section('content')
<div class="grid gap-6 xl:grid-cols-[400px_1fr]">
    <div class="space-y-6">
        <div class="card card-pad">
            <div class="flex items-center gap-4"><img src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-2xl"><div><p class="font-display text-xl font-bold">{{ $user->name }} <x-demo-badge :show="$user->is_demo" /></p><x-status-badge :status="$user->status" :label="ucfirst($user->status)" /></div></div>
            <dl class="mt-5 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt>Phone</dt><dd class="font-semibold">{{ $user->phone }}</dd></div>
                <div class="flex justify-between gap-3"><dt>NRC</dt><dd class="font-semibold">{{ $user->nrc_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt>Email</dt><dd class="font-semibold">{{ $user->email ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt>Joined</dt><dd class="font-semibold">{{ $user->created_at->format('d M Y') }}</dd></div>
                <div><dt>Address</dt><dd class="mt-1 font-semibold">{{ $user->address ?? '—' }}</dd></div>
            </dl>
            @if($user->id !== auth()->id())
                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="mt-5">@csrf<button class="btn w-full {{ $user->status === 'active' ? 'btn-danger' : 'btn-success' }}" onclick="return confirm('Are you sure?')">{{ $user->status === 'active' ? 'Suspend account' : 'Reactivate account' }}</button></form>
            @endif
        </div>
    </div>
    <div class="space-y-6">
        @if($user->latitude)
            <div class="card overflow-hidden" x-data="pointsMap({ points: [{ lat: {{ $user->latitude }}, lng: {{ $user->longitude }}, title: @js($user->name), subtitle: @js($user->address), color: '#FF8A24' }], zoom: 15 })"><div x-ref="map" class="h-72"></div></div>
        @endif
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Recent requests</h2>
            <div class="mt-3 space-y-2">@forelse($requests as $sr)@include('partials.request-row', ['sr' => $sr, 'href' => route('admin.requests.show', $sr)])@empty<p class="text-sm text-navy-500">No requests yet.</p>@endforelse</div>
        </div>
    </div>
</div>
@endsection
