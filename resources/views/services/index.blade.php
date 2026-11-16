@extends('layouts.app', ['title' => 'All services'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Service directory', 'heading' => 'Every kind of help, one platform', 'sub' => 'Browse all services offered by verified HANDY NOW professionals. Indicative prices are in Zambian Kwacha – you always get a quote before work starts.'])
<section class="container-x py-10">
    <form method="GET" class="flex max-w-2xl gap-2">
        <input name="q" value="{{ $q }}" class="input" placeholder="Search e.g. tyre, geyser, burst pipe…" aria-label="Search services">
        <button class="btn btn-navy"><x-icon name="search" class="h-4 w-4" /> Search</button>
    </form>

    @if($results !== null)
        <div class="mt-8">
            <h2 class="font-display text-xl font-bold">{{ $results->count() }} result(s) for “{{ $q }}”</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($results as $s)
                    <a href="{{ route('services.show', [$s->category, $s]) }}" class="card card-pad hover:shadow-lg">
                        <p class="text-xs font-bold uppercase text-brand-600">{{ $s->category->name }}</p>
                        <p class="mt-1 font-bold">{{ $s->name }}</p>
                        <p class="mt-1 text-sm text-navy-500">{{ $s->priceRange() }}</p>
                    </a>
                @empty
                    <p class="text-navy-500">No matching services. <a href="{{ route('requests.create', ['q' => $q]) }}" class="font-semibold text-brand-600">Describe your problem anyway →</a></p>
                @endforelse
            </div>
        </div>
    @endif

    <div class="mt-10 space-y-6">
        @foreach($categories as $c)
            <div id="{{ $c->slug }}" class="card overflow-hidden">
                <div class="grid lg:grid-cols-[320px_1fr]">
                    <div class="relative flex flex-col justify-end bg-navy-900 p-6 text-white">
                        @if($c->image)<img src="{{ $c->image }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30">@endif
                        <div class="relative">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500"><x-icon :name="$c->icon" /></span>
                            <h2 class="mt-4 font-display text-2xl font-bold">{{ $c->name }}</h2>
                            <p class="mt-1 text-sm text-navy-100">{{ $c->short_description }}</p>
                            <div class="mt-4 flex gap-2">
                                <a href="{{ route('services.category', $c) }}" class="btn btn-sm bg-white/10 text-white hover:bg-white/20">Details</a>
                                <a href="{{ route('requests.create', ['category' => $c->slug]) }}" class="btn btn-sm btn-primary">Request</a>
                            </div>
                        </div>
                    </div>
                    <ul class="grid gap-px bg-navy-50 sm:grid-cols-2">
                        @foreach($c->activeServices as $s)
                            <li class="bg-white">
                                <a href="{{ route('services.show', [$c, $s]) }}" class="flex items-center justify-between gap-3 p-4 hover:bg-mist">
                                    <span><span class="block font-semibold">{{ $s->name }}</span><span class="text-xs text-navy-300">{{ $s->priceRange() }}</span></span>
                                    <x-icon name="arrow-right" class="h-4 w-4 text-navy-200" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection
