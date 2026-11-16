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
            <h2 class="font-display text-lg font-bold">Approval checklist</h2>
            @if($missing)
                <ul class="mt-3 space-y-1 text-sm">@foreach($missing as $m)<li class="flex gap-2 text-amber-800"><x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $m }}</li>@endforeach</ul>
            @else
                <p class="mt-3 flex items-center gap-2 text-sm font-semibold text-emerald-700"><x-icon name="check-circle" class="h-5 w-5" /> Every requirement is met.</p>
            @endif
            @if($profile->verified_at)<p class="mt-3 text-xs text-navy-500">Approved {{ $profile->verified_at->format('d M Y') }}</p>@endif
            <form method="POST" action="{{ route('admin.providers.verify', $provider) }}" class="mt-4 space-y-3" x-data="{ d: 'approved' }">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer rounded-xl border-2 p-2.5 text-center text-sm font-bold" :class="d === 'approved' ? 'border-emerald-500 bg-emerald-50' : 'border-navy-50'"><input type="radio" name="decision" value="approved" x-model="d" class="sr-only">Approve</label>
                    <label class="cursor-pointer rounded-xl border-2 p-2.5 text-center text-sm font-bold" :class="d === 'rejected' ? 'border-red-500 bg-red-50' : 'border-navy-50'"><input type="radio" name="decision" value="rejected" x-model="d" class="sr-only">Reject</label>
                </div>
                @error('decision')<p class="field-error">{{ $message }}</p>@enderror
                <textarea name="notes" rows="2" class="input" placeholder="Notes (required when rejecting – shown to the provider)">{{ old('notes', $profile->verification_notes) }}</textarea>
                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn btn-navy w-full">Save decision</button>
            </form>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Identity & payment</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt>NRC number</dt><dd class="font-semibold">{{ $profile->nrc_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt>PACRA no.</dt><dd class="font-semibold">{{ $profile->business_reg_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt>Paid via</dt><dd class="text-right font-semibold">{{ $profile->payoutLabel() }}</dd></div>
                <div class="flex justify-between gap-3"><dt>Account name</dt><dd class="font-semibold">{{ $profile->payout_account_name ?? '—' }}</dd></div>
                @if($profile->payout_method === 'bank_transfer')
                    <div class="flex justify-between gap-3"><dt>Account no. / branch</dt><dd class="font-semibold">{{ $profile->bank_account_number }} · {{ $profile->bank_branch ?: '—' }}</dd></div>
                @endif
            </dl>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Documents</h2>
            <ul class="mt-3 divide-y divide-navy-50">
                @forelse($profile->documents as $doc)
                    <li class="py-3" x-data="{ reject: false }">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold">{{ $doc->label() }}</p>
                                <p class="truncate text-xs text-navy-500">{{ $doc->original_name }}@if($doc->number) · No. {{ $doc->number }}@endif · {{ number_format($doc->size / 1024) }} KB</p>
                                @if($doc->expires_at)<p class="text-xs {{ $doc->isExpired() ? 'font-bold text-red-600' : 'text-navy-500' }}">{{ $doc->isExpired() ? 'EXPIRED ' : 'Expires ' }}{{ $doc->expires_at->format('d M Y') }}</p>@endif
                                @if($doc->review_note)<p class="mt-1 text-xs text-red-700">{{ $doc->review_note }}</p>@endif
                            </div>
                            <x-status-badge :status="$doc->status" :label="ucfirst($doc->status)" />
                        </div>
                        @if($doc->isImage())<a href="{{ route('admin.documents.view', $doc) }}" target="_blank"><img src="{{ route('admin.documents.view', $doc) }}" alt="{{ $doc->label() }}" class="mt-2 max-h-40 rounded-lg border border-navy-50 object-cover"></a>@endif
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.documents.view', $doc) }}" target="_blank" class="btn btn-sm btn-outline"><x-icon name="file" class="h-4 w-4" /> Open</a>
                            @if($doc->status !== 'approved')
                                <form method="POST" action="{{ route('admin.documents.review', $doc) }}">@csrf <input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-success">Approve</button></form>
                            @endif
                            @if($doc->status !== 'rejected')<button type="button" @click="reject = !reject" class="btn btn-sm btn-outline text-red-600">Reject</button>@endif
                        </div>
                        <form x-show="reject" x-cloak method="POST" action="{{ route('admin.documents.review', $doc) }}" class="mt-2 flex gap-2">
                            @csrf <input type="hidden" name="decision" value="rejected">
                            <input name="note" class="input py-2" placeholder="Why? e.g. Photo is blurry" required>
                            <button class="btn btn-sm btn-danger">Reject</button>
                        </form>
                    </li>
                @empty
                    <li class="py-3 text-sm text-red-600">No documents uploaded.</li>
                @endforelse
            </ul>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Account</h2>
            <p class="mt-1 text-sm">Status: <x-status-badge :status="$provider->status" :label="ucfirst($provider->status)" /></p>
            <form method="POST" action="{{ route('admin.users.status', $provider) }}" class="mt-3">@csrf<button class="btn w-full {{ $provider->status === 'active' ? 'btn-danger' : 'btn-success' }}" onclick="return confirm('Are you sure?')">{{ $provider->status === 'active' ? 'Suspend account' : 'Reactivate account' }}</button></form>
        </div>
    </aside>
</div>
@endsection
