@extends('layouts.auth', ['title' => 'Become a provider', 'image' => '/images/electrical.webp', 'wide' => true])
@section('content')
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">Join HANDY NOW as a service provider</h1>
    <p class="mt-1 text-sm text-navy-500">Get verified, set your availability and receive paying jobs near you. Verification usually takes under 24 hours.</p>
    <form method="POST" action="{{ route('provider.register') }}" enctype="multipart/form-data" class="mt-6 space-y-8" x-data="{ type: '{{ old('provider_type', 'individual') }}' }">
        @csrf
        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">1. Account</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Full name (as on NRC)" :required="true" />
                <x-field name="email" type="email" label="Email" :required="true" />
                <x-field name="phone" type="tel" label="Phone" :required="true" placeholder="097 123 4567" />
                <x-field name="whatsapp" type="tel" label="WhatsApp number" placeholder="Leave blank if same as phone" />
                <x-field name="password" type="password" label="Password" :required="true" hint="8+ characters, letters and numbers" />
                <x-field name="password_confirmation" type="password" label="Confirm password" :required="true" />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">2. Verification documents</legend>
            <div class="grid grid-cols-2 gap-2">
                <label class="cursor-pointer rounded-xl border-2 p-3 text-sm font-semibold" :class="type === 'individual' ? 'border-brand-500 bg-brand-50' : 'border-navy-50'"><input type="radio" name="provider_type" value="individual" x-model="type" class="mr-2 accent-brand-500">Individual technician</label>
                <label class="cursor-pointer rounded-xl border-2 p-3 text-sm font-semibold" :class="type === 'business' ? 'border-brand-500 bg-brand-50' : 'border-navy-50'"><input type="radio" name="provider_type" value="business" x-model="type" class="mr-2 accent-brand-500">Registered business</label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="nrc_number" label="NRC number" :required="true" placeholder="123456/78/1" />
                <x-field name="nrc_document" type="file" label="NRC copy (photo or PDF)" :required="true" accept="image/*,.pdf" hint="Stored privately – only verification staff can see it." />
            </div>
            <div class="grid gap-4 sm:grid-cols-2" x-show="type === 'business'" x-cloak>
                <x-field name="business_name" label="Business name" />
                <x-field name="business_reg_number" label="PACRA registration number" />
                <x-field name="business_document" type="file" label="Certificate of incorporation / registration" accept="image/*,.pdf" />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">3. Services & coverage</legend>
            <div>
                <p class="label">Service categories <span class="text-brand-600">*</span></p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($categories as $c)
                        <label class="flex items-center gap-2 rounded-xl border border-navy-50 p-3 text-sm font-semibold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="checkbox" name="categories[]" value="{{ $c->id }}" class="accent-brand-500" @checked(in_array($c->id, old('categories', [])))> <x-icon :name="$c->icon" class="h-4 w-4 text-brand-600" /> {{ $c->name }}
                        </label>
                    @endforeach
                </div>
                @error('categories')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="city" label="Town / city" :required="true" value="Lusaka" />
                <x-field name="service_radius_km" type="number" label="Max travel distance (km)" :required="true" value="25" min="1" max="200" />
                <x-field name="years_experience" type="number" label="Years of experience" min="0" max="60" />
            </div>
            <x-field name="operating_areas" label="Areas you cover (comma separated)" :required="true" placeholder="Kabulonga, Woodlands, Chelstone" />
            <div x-data="locationPicker({ lat: @js(old('base_latitude') ? (float) old('base_latitude') : null), lng: @js(old('base_longitude') ? (float) old('base_longitude') : null) })">
                <p class="label">Your base location (workshop or home)</p>
                <button type="button" @click="locate" class="btn btn-sm btn-outline"><x-icon name="crosshair" class="h-4 w-4" /> <span x-text="busy ? 'Locating…' : 'Use my location'"></span></button>
                <p x-show="error" x-text="error" class="mt-1 text-sm text-red-600" x-cloak></p>
                <div x-ref="map" class="mt-2 h-56 rounded-xl border border-navy-50"></div>
                <input type="hidden" name="base_latitude" :value="lat ?? ''"><input type="hidden" name="base_longitude" :value="lng ?? ''">
                <p class="hint">Used to match you with nearby customers. Tap the map to set it.</p>
            </div>
            <x-field name="bio" type="textarea" label="Short description of your services" rows="3" />
        </fieldset>

        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="mt-1 accent-brand-500" @checked(old('terms'))> <span>I confirm my documents are genuine and I agree to the <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-brand-600">provider terms</a>, including the platform commission on completed jobs.</span></label>
        <button class="btn btn-primary btn-lg w-full">Submit application</button>
    </form>
</div>
@endsection
