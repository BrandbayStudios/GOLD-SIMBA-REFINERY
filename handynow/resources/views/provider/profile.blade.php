@extends('layouts.dashboard', ['title' => 'Profile & verification'])
@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_400px]">
    <form method="POST" action="{{ route('provider.profile.update') }}" enctype="multipart/form-data" class="card card-pad space-y-5">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Public profile</h2>
        <div class="flex items-center gap-4"><img src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-2xl"><x-field name="avatar" type="file" accept="image/*" /></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Full name" :value="$user->name" :required="true" />
            <x-field name="business_name" label="Business name" :value="$profile->business_name" />
            <x-field name="email" type="email" label="Email" :value="$user->email" :required="true" />
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
                    <label class="flex items-center gap-2 rounded-xl border border-navy-50 p-3 text-sm font-semibold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"><input type="checkbox" name="categories[]" value="{{ $c->id }}" class="accent-brand-500" @checked(in_array($c->id, $mine))> {{ $c->name }}</label>
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

    <div class="space-y-6">
        <form method="POST" action="{{ route('provider.profile.documents') }}" enctype="multipart/form-data" class="card card-pad space-y-4">
            @csrf
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-bold">Verification</h2><x-status-badge :status="$profile->verification_status" :label="ucfirst($profile->verification_status)" /></div>
            @if($profile->verification_notes)<p class="rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ $profile->verification_notes }}</p>@endif
            <x-field name="nrc_number" label="NRC number" :value="$profile->nrc_number" :required="true" />
            <div class="text-sm">@if($profile->nrc_document)<a href="{{ route('provider.documents.view', 'nrc_document') }}" target="_blank" class="font-semibold text-brand-600"><x-icon name="file" class="inline h-4 w-4" /> View uploaded NRC</a>@else<span class="text-navy-500">No NRC uploaded</span>@endif</div>
            <x-field name="nrc_document" type="file" label="Replace NRC copy" accept="image/*,.pdf" />
            <x-field name="business_reg_number" label="PACRA registration no." :value="$profile->business_reg_number" />
            <div class="text-sm">@if($profile->business_document)<a href="{{ route('provider.documents.view', 'business_document') }}" target="_blank" class="font-semibold text-brand-600"><x-icon name="file" class="inline h-4 w-4" /> View business certificate</a>@endif</div>
            <x-field name="business_document" type="file" label="Business certificate" accept="image/*,.pdf" />
            <button class="btn btn-navy w-full">{{ $profile->verification_status === 'rejected' ? 'Resubmit for verification' : 'Update documents' }}</button>
        </form>
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
