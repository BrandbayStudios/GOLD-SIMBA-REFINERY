@extends('layouts.app')

@section('content')
{{-- HERO --}}
<section class="relative overflow-hidden bg-navy-900">
    <div class="absolute inset-0">
        <img src="/images/roadside.webp" alt="" class="h-full w-full object-cover opacity-30">
        <div class="absolute inset-0 bg-gradient-to-r from-navy-900 via-navy-900/90 to-navy-900/40"></div>
    </div>
    <div class="container-x relative grid gap-10 py-14 sm:py-20 lg:grid-cols-12 lg:py-24">
        <div class="lg:col-span-7">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-brand-200 ring-1 ring-white/15">
                <span class="pulse-dot text-emerald-400"></span> {{ $stats['online'] }} professionals online now
            </span>
            <h1 class="mt-5 font-display text-4xl font-extrabold leading-[1.05] text-white sm:text-5xl lg:text-6xl">Need Help?<br><span class="text-brand-500">We've Got You Covered.</span></h1>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-navy-100">From emergency towing to plumbing, electrical and mechanical repairs, find trusted professionals ready to help.</p>

            <form action="{{ route('requests.create') }}" method="GET" class="mt-8 rounded-2xl bg-white p-2 shadow-2xl">
                <div class="grid gap-2 sm:grid-cols-[1.3fr_1fr_auto]">
                    <label class="flex items-center gap-2 rounded-xl px-3 sm:border-r sm:border-navy-50">
                        <x-icon name="search" class="h-5 w-5 shrink-0 text-navy-300" />
                        <input name="q" list="service-suggestions" class="w-full border-0 bg-transparent py-3 text-[15px] placeholder:text-navy-300 focus:outline-none" placeholder="What service do you need?" aria-label="What service do you need?">
                        <datalist id="service-suggestions">
                            @foreach($categories as $c)<option value="{{ $c->name }}">@endforeach
                        </datalist>
                    </label>
                    <label class="flex items-center gap-2 rounded-xl px-3">
                        <x-icon name="map-pin" class="h-5 w-5 shrink-0 text-navy-300" />
                        <input name="location" class="w-full border-0 bg-transparent py-3 text-[15px] placeholder:text-navy-300 focus:outline-none" placeholder="Your area, e.g. Kabulonga" aria-label="Location">
                    </label>
                    <button class="btn btn-navy">Find help <x-icon name="arrow-right" class="h-4 w-4" /></button>
                </div>
            </form>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('requests.emergency') }}" class="btn btn-primary btn-lg"><x-icon name="siren" /> Emergency Service</a>
                <a href="{{ route('services.index') }}" class="btn btn-lg border border-white/20 bg-white/5 text-white hover:bg-white/10">Browse Services</a>
            </div>

            <dl class="mt-10 grid max-w-xl grid-cols-3 gap-4 border-t border-white/10 pt-6">
                <div><dt class="flex items-center gap-1.5 text-xs font-semibold text-navy-200"><x-icon name="shield" class="h-4 w-4 text-brand-400" /> Verified pros</dt><dd class="mt-1 font-display text-2xl font-bold text-white">{{ $stats['providers'] }}+</dd></div>
                <div><dt class="flex items-center gap-1.5 text-xs font-semibold text-navy-200"><x-icon name="clock" class="h-4 w-4 text-brand-400" /> Avg response</dt><dd class="mt-1 font-display text-2xl font-bold text-white">~{{ $stats['avg_response'] }} min</dd></div>
                <div><dt class="flex items-center gap-1.5 text-xs font-semibold text-navy-200"><x-icon name="check-circle" class="h-4 w-4 text-brand-400" /> Jobs done</dt><dd class="mt-1 font-display text-2xl font-bold text-white">{{ $stats['completed'] }}</dd></div>
            </dl>
        </div>

        <div class="hidden lg:col-span-5 lg:block">
            <div class="relative ml-auto max-w-md">
                <img src="/images/electrical-solar.webp" alt="Verified electrician installing a solar inverter" class="aspect-[4/5] w-full rounded-3xl object-cover shadow-2xl ring-1 ring-white/10">
                <div class="absolute -left-10 bottom-10 w-64 rounded-2xl bg-white p-4 shadow-2xl">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="navigation" /></span>
                        <div><p class="text-xs font-semibold text-navy-300">Provider en route</p><p class="text-sm font-bold">Arriving in 12 min</p></div>
                    </div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-mist"><div class="h-full w-2/3 rounded-full bg-brand-500"></div></div>
                </div>
                <div class="absolute -right-4 top-8 rounded-2xl bg-white px-4 py-3 shadow-2xl">
                    <x-stars :rating="5" /><p class="mt-1 text-xs font-bold">Verified & rated</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CATEGORIES --}}
<section class="py-16 sm:py-20">
    <div class="container-x">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-brand-600">Service categories</p>
                <h2 class="mt-2 font-display text-3xl font-bold sm:text-4xl">What do you need help with?</h2>
            </div>
            <a href="{{ route('services.index') }}" class="btn btn-outline">View all services <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($categories as $c)
                <div class="card group flex flex-col p-5 transition hover:-translate-y-1 hover:shadow-xl">
                    <a href="{{ route('services.category', $c) }}" class="flex-1">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white"><x-icon :name="$c->icon" class="h-7 w-7" /></span>
                        <h3 class="mt-4 font-display text-lg font-bold">{{ $c->name }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-navy-500">{{ $c->short_description }}</p>
                        <p class="mt-3 text-xs font-semibold text-navy-300">{{ $c->services_count }} services</p>
                    </a>
                    <a href="{{ route('requests.create', ['category' => $c->slug]) }}" class="btn btn-sm btn-outline mt-4 w-full group-hover:border-brand-500 group-hover:text-brand-600">Request now</a>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- HOW IT WORKS --}}
<section class="bg-mist py-16 sm:py-20">
    <div class="container-x">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-bold uppercase tracking-wider text-brand-600">How it works</p>
            <h2 class="mt-2 font-display text-3xl font-bold sm:text-4xl">Help on the way in five simple steps</h2>
        </div>
        @php $steps = [
            ['grid', 'Select a service', 'Choose what you need – from towing to a burst pipe.'],
            ['map-pin', 'Share location & problem', 'Pin your spot on the map, describe the issue, add photos.'],
            ['bell', 'Receive responses', 'Nearby verified technicians send quotes in Kwacha.'],
            ['check-circle', 'Confirm your provider', 'Compare price, rating and arrival time, then accept.'],
            ['navigation', 'Track & pay', 'Follow arrival live, then pay by mobile money or cash.'],
        ]; @endphp
        <ol class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach($steps as $i => [$icon, $t, $d])
                <li class="card card-pad relative">
                    <span class="absolute right-5 top-5 font-display text-4xl font-extrabold text-navy-50">{{ $i + 1 }}</span>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-navy-900 text-brand-400"><x-icon :name="$icon" /></span>
                    <h3 class="mt-4 font-bold">{{ $t }}</h3>
                    <p class="mt-1 text-sm text-navy-500">{{ $d }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- EMERGENCY CTA with photos --}}
<section class="py-16 sm:py-20">
    <div class="container-x grid items-center gap-10 lg:grid-cols-2">
        <div class="grid grid-cols-2 gap-4">
            <img src="/images/plumbing.webp" alt="Plumber repairing pipes under a sink" class="aspect-[4/5] w-full rounded-3xl object-cover">
            <div class="grid gap-4">
                <img src="/images/mechanical.webp" alt="Mechanic working on a vehicle engine" class="aspect-square w-full rounded-3xl object-cover">
                <img src="/images/electrical.webp" alt="Electrician repairing a wall socket" class="aspect-square w-full rounded-3xl object-cover">
            </div>
        </div>
        <div>
            <p class="text-sm font-bold uppercase tracking-wider text-brand-600">Emergency request</p>
            <h2 class="mt-2 font-display text-3xl font-bold sm:text-4xl">Stuck right now? Send one request – we alert every nearby pro.</h2>
            <p class="mt-4 text-lg text-navy-500">No account needed. Tell us what happened and where you are. We'll SMS you a tracking link and notify available verified technicians within your area instantly.</p>
            <ul class="mt-6 space-y-3">
                @foreach(['Verified NRC and business registration for every provider', 'Quotes in Kwacha before any work starts', 'Live arrival tracking and in-app chat', 'Pay with MTN MoMo, Airtel Money, Zamtel Kwacha, bank or cash'] as $p)
                    <li class="flex gap-3"><span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check" class="h-4 w-4" :stroke="3" /></span><span class="font-medium">{{ $p }}</span></li>
                @endforeach
            </ul>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('requests.emergency') }}" class="btn btn-primary btn-lg"><x-icon name="siren" /> Request emergency help</a>
                <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="btn btn-outline btn-lg"><x-icon name="phone" /> Call {{ $supportPhone }}</a>
            </div>
        </div>
    </div>
</section>

{{-- FEATURED PROFESSIONALS --}}
<section class="bg-navy-900 py-16 text-white sm:py-20" x-data="nearby({ url: '{{ route('providers.nearby') }}' })">
    <div class="container-x">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-brand-400">Featured professionals</p>
                <h2 class="mt-2 font-display text-3xl font-bold sm:text-4xl">Trusted, verified and ready</h2>
            </div>
            <button type="button" @click="locate" :disabled="busy" class="btn border border-white/20 bg-white/5 text-white hover:bg-white/10">
                <x-icon name="crosshair" class="h-4 w-4" /> <span x-text="located ? 'Distances updated' : (busy ? 'Locating…' : 'Show distance from me')"></span>
            </button>
        </div>
        <p x-show="error" x-text="error" x-cloak class="mt-3 text-sm text-brand-200"></p>
        @if($featured->isEmpty())
            <p class="mt-10 rounded-2xl bg-white/5 p-8 text-center text-navy-200">Our first verified professionals are being onboarded. <a href="{{ route('provider.register') }}" class="font-bold text-brand-400">Join as a provider →</a></p>
        @else
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($featured as $p)
                @include('partials.provider-card', ['p' => $p, 'dark' => true])
            @endforeach
        </div>
        @endif
    </div>
</section>

{{-- PROVIDER CTA --}}
<section class="py-16 sm:py-20">
    <div class="container-x">
        <div class="relative overflow-hidden rounded-3xl bg-brand-500 px-6 py-12 sm:px-12">
            <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-white/10"></div>
            <div class="relative grid items-center gap-6 lg:grid-cols-[1fr_auto]">
                <div>
                    <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Are you a skilled technician?</h2>
                    <p class="mt-3 max-w-2xl text-lg text-white/90">Join HANDY NOW, get verified and receive paying jobs near you. Set your own availability, send your own quotes and get paid by mobile money.</p>
                </div>
                <a href="{{ route('provider.register') }}" class="btn btn-lg btn-navy">Become a provider <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </div>
        </div>
    </div>
</section>
@endsection
