@extends('layouts.auth', ['title' => 'Become a provider', 'image' => '/images/electrical.webp', 'wide' => true])
@section('content')
@php $catData = $categories->map(fn ($c) => ['id' => (string) $c->id, 'required' => $c->license_required])->values(); @endphp
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">Join HANDY NOW as a service provider</h1>
    <p class="mt-1 text-sm text-navy-500">Customers trust verified providers. Upload your documents once, and our team usually approves within 24 hours. Photos or PDFs up to 5 MB each. Your documents are private and only visible to our verification team.</p>

    <form method="POST" action="{{ route('provider.register') }}" enctype="multipart/form-data" class="mt-6 space-y-8"
          x-data="{ type: '{{ old('provider_type', 'individual') }}', cats: @js(array_map('strval', old('categories', []))) }">
        @csrf

        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">1. About you</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Full name (as on your NRC)" :required="true" />
                <x-field name="phone" type="tel" label="Phone" :required="true" placeholder="097 123 4567" />
                <x-field name="whatsapp" type="tel" label="WhatsApp number" placeholder="Leave blank if the same" />
                <x-field name="email" type="email" label="Email (optional)" />
                <x-field name="password" type="password" label="Password" :required="true" hint="8+ characters, letters and numbers" />
                <x-field name="password_confirmation" type="password" label="Confirm password" :required="true" />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">2. Identity</legend>
            <div class="grid grid-cols-2 gap-2">
                <label class="cursor-pointer rounded-xl border-2 p-3 text-sm font-semibold" :class="type === 'individual' ? 'border-brand-500 bg-brand-50' : 'border-navy-50'"><input type="radio" name="provider_type" value="individual" x-model="type" class="mr-2 accent-brand-500">Individual technician</label>
                <label class="cursor-pointer rounded-xl border-2 p-3 text-sm font-semibold" :class="type === 'business' ? 'border-brand-500 bg-brand-50' : 'border-navy-50'"><input type="radio" name="provider_type" value="business" x-model="type" class="mr-2 accent-brand-500">Registered business</label>
            </div>
            <x-field name="nrc_number" label="NRC number" :required="true" placeholder="123456/78/1" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="nrc_front" type="file" label="NRC – front" :required="true" accept="image/*,.pdf" capture="environment" />
                <x-field name="nrc_back" type="file" label="NRC – back" :required="true" accept="image/*,.pdf" capture="environment" />
                <x-field name="selfie" type="file" label="Selfie holding your NRC" :required="true" accept="image/*" hint="Your face and the NRC must both be clear." />
            </div>
            <div class="grid gap-4 rounded-2xl bg-mist p-4 sm:grid-cols-2" x-show="type === 'business'" x-cloak>
                <x-field name="business_name" label="Business name" />
                <x-field name="business_reg_number" label="PACRA registration number" />
                <x-field name="business_cert" type="file" label="Certificate of incorporation / registration" accept="image/*,.pdf" />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">3. Services, licences & certificates</legend>
            <div>
                <p class="label">What do you offer? <span class="text-brand-600">*</span></p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($categories as $c)
                        <label class="flex items-center gap-2 rounded-xl border border-navy-50 p-3 text-sm font-semibold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="checkbox" name="categories[]" value="{{ $c->id }}" x-model="cats" class="accent-brand-500"> <x-icon :name="$c->icon" class="h-4 w-4 text-brand-600" /> {{ $c->name }}
                        </label>
                    @endforeach
                </div>
                @error('categories')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            @foreach($categories as $c)
                <div x-show="cats.includes('{{ $c->id }}')" x-cloak class="rounded-2xl border border-navy-50 p-4">
                    <p class="flex flex-wrap items-center gap-2 font-bold">{{ $c->name }} <span class="badge {{ $c->license_required ? 'badge-orange' : 'badge-grey' }}">{{ $c->license_required ? 'Licence required' : 'Optional' }}</span></p>
                    @if($c->license_hint)<p class="mt-1 text-sm text-navy-500">{{ $c->license_hint }}</p>@endif
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <x-field name="licences[{{ $c->id }}][file]" type="file" label="Licence / certificate" accept="image/*,.pdf" />
                        <x-field name="licences[{{ $c->id }}][number]" label="Licence number" :value="old('licences.'.$c->id.'.number')" />
                        <x-field name="licences[{{ $c->id }}][expiry]" type="date" label="Expiry date" :value="old('licences.'.$c->id.'.expiry')" />
                    </div>
                    @error('licences.'.$c->id.'.file')<p class="field-error">{{ $message }}</p>@enderror
                    @error('licences.'.$c->id.'.expiry')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <details class="rounded-2xl border border-navy-50 p-4">
                <summary class="cursor-pointer text-sm font-bold">Add other certificates (optional)</summary>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    @for($i = 0; $i < 3; $i++)
                        <x-field name="extra_docs[{{ $i }}][title]" label="Certificate name" :value="old('extra_docs.'.$i.'.title')" placeholder="e.g. Solar installer course" />
                        <x-field name="extra_docs[{{ $i }}][file]" type="file" label="File" accept="image/*,.pdf" />
                    @endfor
                </div>
            </details>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">4. Where you work</legend>
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
                <p class="hint">Used to send you nearby jobs. Tap the map to set it.</p>
            </div>
            <x-field name="bio" type="textarea" label="Short description of your services" rows="3" />
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="font-display text-lg font-bold">5. Payment method</legend>
            @include('partials.payout-fields', ['p' => null])
        </fieldset>

        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="mt-1 accent-brand-500" @checked(old('terms'))> <span>I confirm my documents are genuine and I agree to the <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-brand-600">provider terms</a>, including the platform commission on completed jobs.</span></label>
        @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn btn-primary btn-lg w-full">Submit application</button>
    </form>
</div>
@endsection
