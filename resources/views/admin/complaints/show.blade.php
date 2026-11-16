@extends('layouts.dashboard', ['title' => 'Complaint '.$complaint->reference])
@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_400px]">
    <div class="card card-pad">
        <x-status-badge :status="$complaint->status" :label="\App\Models\Complaint::STATUSES[$complaint->status]" />
        <h2 class="mt-3 font-display text-2xl font-bold">{{ $complaint->subject }}</h2>
        <p class="text-sm text-navy-500">By {{ $complaint->user->name }} ({{ $complaint->user->phone }}) · {{ $complaint->created_at->format('d M Y H:i') }}</p>
        <p class="mt-4 whitespace-pre-line">{{ $complaint->description }}</p>
        @if($complaint->serviceRequest)
            <div class="mt-5 rounded-xl bg-mist p-4 text-sm">
                Related request <a href="{{ route('admin.requests.show', $complaint->serviceRequest) }}" class="font-bold text-brand-600">{{ $complaint->serviceRequest->reference }}</a> · {{ $complaint->serviceRequest->category->name }} · provider {{ $complaint->serviceRequest->provider?->name ?? '—' }}
                @if($complaint->againstUser)<a href="{{ route('admin.providers.show', $complaint->againstUser) }}" class="ml-2 font-semibold text-brand-600">View provider →</a>@endif
            </div>
        @endif
    </div>
    <form method="POST" action="{{ route('admin.complaints.update', $complaint) }}" class="card card-pad h-fit space-y-4">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Resolution</h2>
        <div><label class="label" for="st">Status</label><select id="st" name="status" class="input">@foreach(\App\Models\Complaint::STATUSES as $k => $l)<option value="{{ $k }}" @selected($complaint->status === $k)>{{ $l }}</option>@endforeach</select></div>
        <x-field name="admin_response" type="textarea" label="Response to complainant" :value="$complaint->admin_response" rows="5" />
        <button class="btn btn-primary w-full">Save & notify</button>
        @if($complaint->handler)<p class="text-xs text-navy-500">Last handled by {{ $complaint->handler->name }}</p>@endif
    </form>
</div>
@endsection
