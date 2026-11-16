@extends('layouts.dashboard', ['title' => 'Profile & verification'])
@section('content')
@php
    $docs = $profile->documents;
    $slots = collect($profile->requiredDocumentTypes())->mapWithKeys(fn ($t) => [$t => $docs->firstWhere('type', $t)]);
@endphp
<div class="grid gap-6 xl:grid-cols-[1fr_440px]">
    <div class="space-y-6">
        <form method="POST" action="{{ route('provider.profile.update') }}" enctype="multipart/form-data" class="card card-pad space-y-5">
            @csrf @method('PUT')
            <h2 class="font-display text-lg font-bold">Public profile</h2>
            <div class="flex items-center gap-4"><img src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-2xl"><x-field name="avatar" type="file" accept="image/*" /></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Full name" :value="$user->name" :required="true" />
                <x-field name="business_name" label="Business name" :value="$profile->business_name" />
                <x-field name="email" type="email" label="Email (optional)" :value="$user->email" />
                <x-field name="phone" type="tel" label="Phone" :value="$user->phone" :required="true" />
                <x-field name="whatsapp" type="tel" label="WhatsApp" :value="$profile->whatsapp" />
                <x-field name="city" label="Town / city" :value="$user->city" :required="true" />
                <x-field name="years_experience" type="number" label="Years of experience" :value="$profile->years_experience" />
                <x-field name="service_radius_km" type="number" label="Max travel distance (km)" :value="$profile->service_radius_km" :required="true" />
            </div>
            <x-field name="operating_areas" label="Areas covered (comma separated)" :value="collect($profile->operating_areas)->join(', ')" :required="true" />
            <div>
                <p class="label">Service categories</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @php $mine = old('categories', $profile->categories->pluck('id')->all()); @endphp
                    @foreach($categories as $c)
                        <label class="flex items-center gap-2 rounded-xl border border-navy-50 p-3 text-sm font-semibold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"><input type="checkbox" name="categories[]" value="{{ $c->id }}" class="accent-brand-500" @checked(in_array($c->id, $mine))> {{ $c->name }} @if($c->license_required)<span class="badge badge-orange">Licence</span>@endif</label>
                    @endforeach
                </div>
            </div>
            <div x-data="locationPicker({ lat: @js($profile->base_latitude), lng: @js($profile->base_longitude) })">
                <p class="label">Base location</p>
                <button type="button" @click="locate" class="btn btn-sm btn-outline"><x-icon name="crosshair" class="h-4 w-4" /> Use my location</button>
                <div x-ref="map" class="mt-2 h-56 rounded-xl border border-navy-50"></div>
                <input type="hidden" name="base_latitude" :value="lat ?? ''"><input type="hidden" name="base_longitude" :value="lng ?? ''">
            </div>
            <x-field name="bio" type="textarea" label="About your services" :value="$profile->bio" />
            <button class="btn btn-primary">Save profile</button>
        </form>

        <form method="POST" action="{{ route('provider.payout.update') }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-bold">Payment method</h2><span class="text-xs text-navy-500">Now: {{ $profile->payoutLabel() }}</span></div>
            @include('partials.payout-fields', ['p' => $profile])
            <button class="btn btn-navy">Save payment method</button>
        </form>
    </div>

    <div class="space-y-6">
        <div class="card card-pad">
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-bold">Verification</h2><x-status-badge :status="$profile->verification_status" :label="ucfirst($profile->verification_status)" /></div>
            @if($profile->verification_notes)<p class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ $profile->verification_notes }}</p>@endif
            @if($missing)
                <p class="mt-4 text-sm font-bold">Still needed for approval</p>
                <ul class="mt-1 space-y-1 text-sm text-navy-700">@foreach($missing as $m)<li class="flex gap-2"><x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" /> {{ $m }}</li>@endforeach</ul>
            @else
                <p class="mt-4 flex items-center gap-2 text-sm font-semibold text-emerald-700"><x-icon name="check-circle" class="h-5 w-5" /> Everything is checked and approved.</p>
            @endif
            <form method="POST" action="{{ route('provider.profile.identity') }}" class="mt-5 space-y-3">
                @csrf @method('PUT')
                <x-field name="nrc_number" label="NRC number" :value="$profile->nrc_number" :required="true" />
                @if($profile->provider_type === 'business')<x-field name="business_reg_number" label="PACRA registration no." :value="$profile->business_reg_number" />@endif
                <button class="btn btn-outline btn-sm">Save identity details</button>
            </form>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Your documents</h2>
            @error('document')<p class="field-error">{{ $message }}</p>@enderror
            <ul class="mt-3 divide-y divide-navy-50">
                @foreach($slots as $type => $doc)
                    @include('provider.partials.document-row', ['label' => $docTypes[$type], 'doc' => $doc, 'type' => $type, 'categoryId' => null])
                @endforeach
                @foreach($profile->categories as $c)
                    @php $lic = $docs->first(fn ($d) => $d->type === 'licence' && $d->category_id === $c->id); @endphp
                    @if($c->license_required || $lic)
                        @include('provider.partials.document-row', ['label' => 'Licence – '.$c->name.($c->license_required ? '' : ' (optional)'), 'doc' => $lic, 'type' => 'licence', 'categoryId' => $c->id, 'hint' => $c->license_hint])
                    @endif
                @endforeach
                @foreach($docs->filter(fn ($d) => in_array($d->type, ['certificate', 'other'])) as $doc)
                    @include('provider.partials.document-row', ['label' => $doc->label(), 'doc' => $doc, 'type' => $doc->type, 'categoryId' => null, 'removable' => true])
                @endforeach
            </ul>
            <form method="POST" action="{{ route('provider.documents.store') }}" enctype="multipart/form-data" class="mt-5 space-y-3 rounded-2xl bg-mist p-4">
                @csrf <input type="hidden" name="type" value="certificate">
                <p class="text-sm font-bold">Add a certificate or other document</p>
                <x-field name="title" label="Name" placeholder="e.g. Solar installer course" />
                <div class="grid grid-cols-2 gap-3"><x-field name="number" label="Number (optional)" /><x-field name="expires_at" type="date" label="Expires (optional)" /></div>
                <x-field name="file" type="file" label="Photo or PDF" accept="image/*,.pdf" />
                <button class="btn btn-navy btn-sm">Upload</button>
            </form>
        </div>

        <form method="POST" action="{{ route('provider.profile.password') }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <h2 class="font-display text-lg font-bold">Change password</h2>
            <x-field name="current_password" type="password" label="Current password" :required="true" />
            <x-field name="password" type="password" label="New password" :required="true" />
            <x-field name="password_confirmation" type="password" label="Confirm" :required="true" />
            <button class="btn btn-outline w-full">Update password</button>
        </form>
    </div>
</div>
@endsection
