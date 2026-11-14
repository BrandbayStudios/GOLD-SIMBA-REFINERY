@extends('layouts.dashboard', ['title' => 'Report a complaint'])
@section('content')
<form method="POST" action="{{ route('customer.complaints.store') }}" class="card card-pad max-w-2xl space-y-4">
    @csrf
    <div>
        <label class="label" for="sr">Related request (optional)</label>
        <select id="sr" name="service_request_id" class="input">
            <option value="">General complaint</option>
            @foreach($requests as $r)<option value="{{ $r->id }}" @selected(old('service_request_id', $selected) == $r->id)>{{ $r->reference }} – {{ $r->created_at->format('d M Y') }}</option>@endforeach
        </select>
    </div>
    <x-field name="subject" label="Subject" :required="true" />
    <x-field name="description" type="textarea" label="What happened?" :required="true" rows="6" />
    <button class="btn btn-primary">Submit complaint</button>
</form>
@endsection
