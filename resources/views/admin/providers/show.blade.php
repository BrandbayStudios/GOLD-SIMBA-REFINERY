@extends('layouts.dashboard', ['title' => $provider->name])
@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_400px]">
    <div class="space-y-6">
        <div class="card card-pad">
            <div class="flex flex-wrap items-center gap-4">
                <img src="{{ $provider->avatarUrl() }}" alt="" class="h-16 w-16 rounded-2xl">
                <div class="flex-1">
                    <p class="font-display text-xl font-bold">{{ $provider->name }} <x-demo-badge :show="$provider->is_demo" /></p>
                    <p class="text-sm text-navy-500">{{ $profile->business_name }} · {{ ucfirst($profile->provider_type) }} · joined {{ $provider->created_at->format('d M Y') }}</p>
                </div>
                <x-status-badge :status="$profile->verification_status" :label="ucfirst($profile->verification_status)" />
            </div>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase text-navy-300">Phone / WhatsApp</dt><dd>{{ $provider->phone }} / {{ $profile->whatsapp }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Email</dt><dd>{{ $provider->email }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Categories</dt><dd>{{ $profile->categories->pluck('name')->join(', ') }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Coverage</dt><dd>{{ collect($profile->operating_areas)->join(', ') }} · {{ $profile->service_radius_km }} km</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Experience</dt><dd>{{ $profile->years_experience }} years</dd></div>
                <div><dt class="text-xs font-bold uppercase text-navy-300">Performance</dt><dd>{{ $profile->jobs_completed }} jobs · {{ number_format($profile->rating_avg, 1) }}★ ({{ $profile->rating_count }})</dd></div>
            </dl>
            @if($profile->bio)<p class="mt-4 rounded-xl bg-mist p-3 text-sm">{{ $profile->bio }}</p>@endif
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Recent jobs</h2>
            <div class="mt-3 space-y-2">@forelse($jobs as $sr)@include('partials.request-row', ['sr' => $sr, 'href' => route('admin.requests.show', $sr)])@empty<p class="text-sm text-navy-500">No jobs yet.</p>@endforelse</div>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Reviews</h2>
            @forelse($reviews as $r)<div class="mt-3 border-t border-navy-50 pt-3 text-sm"><x-stars :rating="$r->rating" /> <span class="text-xs text-navy-300">{{ $r->customer?->name }}</span><p>{{ $r->comment }}</p></div>@empty<p class="mt-2 text-sm text-navy-500">No reviews.</p>@endforelse
        </div>
    </div>
    <aside class="space-y-6">
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Verification</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt>NRC number</dt><dd class="font-semibold">{{ $profile->nrc_number ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt>PACRA no.</dt><dd class="font-semibold">{{ $profile->business_reg_number ?? '—' }}</dd></div>
            </dl>
            <div class="mt-3 grid gap-2">
                @if($profile->nrc_document)<a href="{{ route('admin.providers.document', [$provider, 'nrc_document']) }}" target="_blank" class="btn btn-outline btn-sm"><x-icon name="file" class="h-4 w-4" /> View NRC document</a>@else<p class="text-sm text-red-600">No NRC document uploaded{{ $provider->is_demo ? ' (demo account)' : '' }}.</p>@endif
                @if($profile->business_document)<a href="{{ route('admin.providers.document', [$provider, 'business_document']) }}" target="_blank" class="btn btn-outline btn-sm"><x-icon name="file" class="h-4 w-4" /> View business certificate</a>@endif
            </div>
            @if($profile->verified_at)<p class="mt-3 text-xs text-navy-500">Approved {{ $profile->verified_at->format('d M Y') }}</p>@endif
            <form method="POST" action="{{ route('admin.providers.verify', $provider) }}" class="mt-5 space-y-3" x-data="{ d: 'approved' }">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer rounded-xl border-2 p-2.5 text-center text-sm font-bold" :class="d === 'approved' ? 'border-emerald-500 bg-emerald-50' : 'border-navy-50'"><input type="radio" name="decision" value="approved" x-model="d" class="sr-only">Approve</label>
                    <label class="cursor-pointer rounded-xl border-2 p-2.5 text-center text-sm font-bold" :class="d === 'rejected' ? 'border-red-500 bg-red-50' : 'border-navy-50'"><input type="radio" name="decision" value="rejected" x-model="d" class="sr-only">Reject</label>
                </div>
                <textarea name="notes" rows="3" class="input" placeholder="Notes (required when rejecting – shown to the provider)">{{ old('notes', $profile->verification_notes) }}</textarea>
                <button class="btn btn-navy w-full">Save decision</button>
            </form>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Account</h2>
            <p class="mt-1 text-sm">Status: <x-status-badge :status="$provider->status" :label="ucfirst($provider->status)" /></p>
            <form method="POST" action="{{ route('admin.users.status', $provider) }}" class="mt-3">@csrf<button class="btn w-full {{ $provider->status === 'active' ? 'btn-danger' : 'btn-success' }}" onclick="return confirm('Are you sure?')">{{ $provider->status === 'active' ? 'Suspend account' : 'Reactivate account' }}</button></form>
        </div>
    </aside>
</div>
@endsection
