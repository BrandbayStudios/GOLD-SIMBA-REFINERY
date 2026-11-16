@extends('layouts.app', ['title' => $emergency ? 'Emergency request' : 'Request a service'])
@php
    $catJson = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'icon' => $c->icon, 'services' => $c->activeServices->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])])->values();
    $u = auth()->user();
@endphp
@section('content')
<section class="{{ $emergency ? 'bg-gradient-to-br from-red-700 to-brand-600' : 'bg-navy-900' }}">
    <div class="container-x py-10 sm:py-12">
        <p class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-white/80">@if($emergency)<span class="pulse-dot text-white"></span> Emergency request @else Service request @endif</p>
        <h1 class="mt-2 font-display text-3xl font-extrabold text-white sm:text-4xl">{{ $emergency ? 'Get urgent help now' : 'Tell us what you need' }}</h1>
        <p class="mt-2 max-w-2xl text-white/85">No account needed. We'll notify available verified providers near you and SMS you a tracking link. If anyone is in danger, call emergency services (991 / 993) first.</p>
    </div>
</section>

<section class="container-x py-8">
    @include('partials.flash')
    <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[1fr_380px]"
          x-data="requestForm({ categories: @js($catJson), categoryId: @js(old('category_id', $selectedCategory?->id)), serviceId: @js(old('service_id', $selectedService?->id)), priority: @js(old('priority', $emergency ? 'emergency' : 'normal')) })">
        @csrf
        <div class="space-y-6">
            <div class="card card-pad">
                <h2 class="flex items-center gap-2 font-display text-lg font-bold"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-navy-900 text-xs text-white">1</span> What's the problem?</h2>
                <p class="label mt-5">Service category <span class="text-brand-600">*</span></p>
                <input type="hidden" name="category_id" :value="categoryId">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <template x-for="c in categories" :key="c.id">
                        <button type="button" @click="pickCategory(c.id)" class="rounded-xl border-2 p-3 text-left text-sm font-semibold transition"
                                :class="String(c.id) === categoryId ? 'border-brand-500 bg-brand-50 text-navy-900' : 'border-navy-50 hover:border-navy-200'">
                            <span x-text="c.name"></span>
                        </button>
                    </template>
                </div>
                @error('category_id')<p class="field-error">{{ $message }}</p>@enderror

                <div class="mt-5" x-show="services.length" x-cloak>
                    <label for="service_id" class="label">Specific problem</label>
                    <select id="service_id" name="service_id" x-model="serviceId" class="input">
                        <option value="">Not sure / other</option>
                        <template x-for="s in services" :key="s.id"><option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === serviceId"></option></template>
                    </select>
                    @error('service_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="mt-5">
                    <x-field name="description" type="textarea" label="Describe the problem" :required="true" :value="$prefillProblem" placeholder="e.g. My car won't start, the battery seems dead. I'm parked outside Manda Hill, silver Toyota Corolla." />
                </div>
                <div class="mt-5">
                    <p class="label">Photos (optional, up to 5)</p>
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-navy-100 p-6 text-center text-sm text-navy-500 hover:border-brand-500">
                        <x-icon name="camera" class="h-7 w-7 text-navy-300" />
                        <span><b class="text-brand-600">Take or upload photos</b> – helps providers quote accurately</span>
                        <input type="file" name="photos[]" accept="image/*" multiple class="sr-only" @change="preview">
                    </label>
                    <div class="mt-3 flex flex-wrap gap-2" x-show="previews.length"><template x-for="p in previews"><img :src="p" class="h-16 w-16 rounded-lg object-cover"></template></div>
                    @error('photos')<p class="field-error">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="card card-pad" x-data="locationPicker({ lat: @js(old('latitude') ? (float) old('latitude') : null), lng: @js(old('longitude') ? (float) old('longitude') : null), addressField: '#f-address', auto: {{ $emergency ? 'true' : 'false' }} })">
                <h2 class="flex items-center gap-2 font-display text-lg font-bold"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-navy-900 text-xs text-white">2</span> Where are you?</h2>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" @click="locate" :disabled="busy" class="btn btn-navy"><x-icon name="crosshair" class="h-4 w-4" /> <span x-text="busy ? 'Finding you…' : 'Use my current location'"></span></button>
                    <span class="text-sm text-navy-500">or tap the map to drop a pin</span>
                </div>
                <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm font-medium text-red-600"></p>
                <p x-show="lat" x-cloak class="mt-2 text-sm font-semibold text-emerald-700">✓ Location pinned (<span x-text="lat"></span>, <span x-text="lng"></span>) – drag the pin to adjust.</p>
                <div x-ref="map" class="mt-4 h-72 overflow-hidden rounded-xl border border-navy-50"></div>
                <input type="hidden" name="latitude" :value="lat ?? ''">
                <input type="hidden" name="longitude" :value="lng ?? ''">
                <div class="mt-5 grid gap-4">
                    <x-field name="address" label="Address or landmark" :required="true" :value="$prefillAddress ?? ($u?->address)" placeholder="e.g. Plot 12, Leopards Hill Road, Kabulonga, Lusaka" oninput="this.dataset.touched='1'" />
                    <x-field name="directions" label="Directions for the provider" placeholder="e.g. Opposite the filling station, black gate, call when you arrive" />
                </div>
            </div>
        </div>

        <div class="space-y-6 lg:sticky lg:top-24 lg:h-fit">
            <div class="card card-pad">
                <h2 class="flex items-center gap-2 font-display text-lg font-bold"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-navy-900 text-xs text-white">3</span> Your details</h2>
                <div class="mt-4 grid gap-4">
                    <x-field name="contact_name" label="Your name" :required="true" :value="$u?->name" autocomplete="name" />
                    <x-field name="contact_phone" type="tel" label="Phone number" :required="true" :value="$u?->phone" placeholder="097 123 4567" autocomplete="tel" hint="We SMS updates to this number. Providers call you on it." />
                </div>
                <p class="label mt-5">Priority</p>
                <div class="grid gap-2">
                    @foreach($priorities as $k => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3 text-sm font-semibold" :class="priority === '{{ $k }}' ? '{{ $k === 'emergency' ? 'border-red-500 bg-red-50' : 'border-brand-500 bg-brand-50' }}' : 'border-navy-50'">
                            <input type="radio" name="priority" value="{{ $k }}" x-model="priority" class="accent-brand-500"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <button class="btn btn-lg mt-6 w-full" :class="priority === 'emergency' ? 'btn-danger' : 'btn-primary'">
                    <x-icon name="siren" /> <span x-text="priority === 'emergency' ? 'Submit emergency request' : 'Submit request'"></span>
                </button>
                <p class="mt-3 text-center text-xs text-navy-300">By submitting you agree to our <a href="{{ route('terms') }}" class="underline">terms</a>. You won't be charged until you accept a quote.</p>
            </div>
            @guest
                <p class="text-center text-sm text-navy-500">Have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600">Log in</a> to track all your requests.</p>
            @endguest
        </div>
    </form>
</section>
@endsection
