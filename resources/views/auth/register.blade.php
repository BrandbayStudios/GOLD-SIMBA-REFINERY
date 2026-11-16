@extends('layouts.auth', ['title' => 'Create account', 'image' => '/images/plumbing.webp'])
@section('content')
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">Create your account</h1>
    <p class="mt-1 text-sm text-navy-500">Just the basics, so providers know who they are helping and where to find you.</p>
    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <x-field name="name" label="Full name (as on your NRC)" :required="true" autocomplete="name" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="phone" type="tel" label="Phone number" :required="true" placeholder="097 123 4567" autocomplete="tel" />
            <x-field name="nrc_number" label="NRC number" :required="true" placeholder="123456/78/1" hint="Kept private. Only HANDY NOW staff can see it." />
        </div>

        <div x-data="locationPicker({ lat: @js(old('latitude') ? (float) old('latitude') : null), lng: @js(old('longitude') ? (float) old('longitude') : null), addressField: '#f-address' })">
            <p class="label">Pin your address on the map <span class="text-brand-600">*</span></p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="locate" :disabled="busy" class="btn btn-sm btn-navy"><x-icon name="crosshair" class="h-4 w-4" /> <span x-text="busy ? 'Finding you…' : 'Use my location'"></span></button>
                <span class="text-xs text-navy-500">or tap the map where you live or work. Drag the pin to adjust.</span>
            </div>
            <p x-show="error" x-text="error" x-cloak class="mt-1 text-sm font-medium text-red-600"></p>
            <div x-ref="map" class="mt-2 h-64 overflow-hidden rounded-xl border border-navy-50"></div>
            <input type="hidden" name="latitude" :value="lat ?? ''"><input type="hidden" name="longitude" :value="lng ?? ''">
            <p x-show="lat" x-cloak class="mt-1 text-sm font-semibold text-emerald-700">✓ Address pinned</p>
            @error('latitude')<p class="field-error">{{ $message }}</p>@enderror
            <div class="mt-3">
                <x-field name="address" label="Address or landmark" :required="true" placeholder="e.g. Plot 12, Leopards Hill Road, Kabulonga" oninput="this.dataset.touched='1'" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="password" type="password" label="Password" :required="true" autocomplete="new-password" hint="8+ characters, letters and numbers" />
            <x-field name="password_confirmation" type="password" label="Confirm password" :required="true" autocomplete="new-password" />
        </div>
        <details class="text-sm" @if(old('email')) open @endif><summary class="cursor-pointer font-semibold text-navy-500">Add an email (optional)</summary><div class="mt-2"><x-field name="email" type="email" autocomplete="email" /></div></details>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="mt-1 accent-brand-500" @checked(old('terms'))> <span>I agree to the <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-brand-600">terms</a> and <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-brand-600">privacy policy</a>.</span></label>
        @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn btn-primary w-full">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-navy-500">Already registered? <a href="{{ route('login') }}" class="font-semibold text-brand-600">Log in</a></p>
</div>
@endsection
