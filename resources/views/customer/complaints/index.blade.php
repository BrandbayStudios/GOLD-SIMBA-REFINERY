@extends('layouts.dashboard', ['title' => 'Complaints'])
@section('header-actions')<a href="{{ route('customer.complaints.create') }}" class="btn btn-sm btn-primary">New complaint</a>@endsection
@section('content')
<div class="space-y-3">
    @forelse($complaints as $c)
        <div class="card card-pad">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div><p class="font-bold">{{ $c->subject }}</p><p class="text-xs text-navy-300">{{ $c->reference }} · {{ $c->created_at->format('d M Y') }} @if($c->serviceRequest)· {{ $c->serviceRequest->reference }}@endif</p></div>
                <x-status-badge :status="$c->status" :label="\App\Models\Complaint::STATUSES[$c->status]" />
            </div>
            <p class="mt-3 text-sm text-navy-700">{{ $c->description }}</p>
            @if($c->admin_response)<div class="mt-3 rounded-xl bg-mist p-3 text-sm"><p class="text-xs font-bold uppercase text-navy-300">HANDY NOW response</p><p class="mt-1">{{ $c->admin_response }}</p></div>@endif
        </div>
    @empty
        <div class="card card-pad text-center text-navy-500">You haven't filed any complaints.</div>
    @endforelse
</div>
<div class="mt-6">{{ $complaints->links() }}</div>
@endsection
