@extends('layouts.dashboard', ['title' => 'Notifications'])
@section('header-actions')<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-outline">Mark all read</button></form>@endsection
@section('content')
<div class="card divide-y divide-navy-50">
    @forelse($notifications as $n)
        <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 p-4 hover:bg-mist {{ $n->read_at ? '' : 'bg-brand-50/50' }}">
            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : (($n->data['kind'] ?? '') === 'emergency' ? 'bg-red-500' : 'bg-brand-500') }}"></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold">{{ $n->data['title'] ?? 'Notification' }}</span>
                <span class="block text-sm text-navy-500">{{ $n->data['body'] ?? '' }}</span>
                <span class="mt-0.5 block text-xs text-navy-300">{{ $n->created_at->diffForHumans() }}</span>
            </span>
        </a>
    @empty
        <p class="p-8 text-center text-navy-500">You're all caught up.</p>
    @endforelse
</div>
<div class="mt-6">{{ $notifications->links() }}</div>
@endsection
