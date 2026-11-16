@extends('layouts.dashboard', ['title' => 'My profile'])
@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_380px]">
    <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="card card-pad space-y-4">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Personal details</h2>
        <div class="flex items-center gap-4"><img src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-2xl"><x-field name="avatar" type="file" accept="image/*" /></div>
        <x-field name="name" label="Full name" :value="$user->name" :required="true" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="phone" type="tel" label="Phone number" :value="$user->phone" :required="true" />
            <x-field name="nrc_number" label="NRC number" :value="$user->nrc_number" :required="true" placeholder="123456/78/1" />
        </div>
        <x-field name="email" type="email" label="Email (optional)" :value="$user->email" />

        <div x-data="locationPicker({ lat: @js(old('latitude', $user->latitude)), lng: @js(old('longitude', $user->longitude)), addressField: '#f-address' })">
            <p class="label">Your pinned address <span class="text-brand-600">*</span></p>
            <button type="button" @click="locate" :disabled="busy" class="btn btn-sm btn-navy"><x-icon name="crosshair" class="h-4 w-4" /> <span x-text="busy ? 'Finding you…' : 'Use my location'"></span></button>
            <span class="ml-2 text-xs text-navy-500">or tap the map. Drag the pin to adjust.</span>
            <p x-show="error" x-text="error" x-cloak class="mt-1 text-sm font-medium text-red-600"></p>
            <div x-ref="map" class="mt-2 h-64 overflow-hidden rounded-xl border border-navy-50"></div>
            <input type="hidden" name="latitude" :value="lat ?? ''"><input type="hidden" name="longitude" :value="lng ?? ''">
            @error('latitude')<p class="field-error">{{ $message }}</p>@enderror
            <div class="mt-3"><x-field name="address" label="Address or landmark" :value="$user->address" :required="true" oninput="this.dataset.touched='1'" /></div>
        </div>
        <button class="btn btn-primary">Save changes</button>
    </form>
    <form method="POST" action="{{ route('customer.profile.password') }}" class="card card-pad h-fit space-y-4">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Change password</h2>
        <x-field name="current_password" type="password" label="Current password" :required="true" />
        <x-field name="password" type="password" label="New password" :required="true" />
        <x-field name="password_confirmation" type="password" label="Confirm new password" :required="true" />
        <button class="btn btn-navy">Update password</button>
    </form>
</div>
@endsection
