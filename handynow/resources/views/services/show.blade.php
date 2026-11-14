@extends('layouts.app', ['title' => $service->name])
@section('content')
<section class="bg-navy-900">
    <div class="container-x py-12 sm:py-16">
        <a href="{{ route('services.category', $category) }}" class="text-sm font-semibold text-navy-200 hover:text-white">← {{ $category->name }}</a>
        <h1 class="mt-4 font-display text-3xl font-extrabold text-white sm:text-5xl">{{ $service->name }}</h1>
        <p class="mt-3 max-w-2xl text-lg text-navy-100">{{ $service->description }}</p>
    </div>
</section>
<section class="container-x grid gap-8 py-12 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
        <div class="card card-pad">
            <h2 class="font-display text-xl font-bold">How pricing works</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-mist p-4"><dt class="text-xs font-semibold text-navy-300">Typical price</dt><dd class="mt-1 font-display text-lg font-bold">{{ $service->priceRange() }}</dd></div>
                <div class="rounded-xl bg-mist p-4"><dt class="text-xs font-semibold text-navy-300">Call-out fee from</dt><dd class="mt-1 font-display text-lg font-bold">{{ $service->callout_fee > 0 ? zmw($service->callout_fee, 0) : 'None' }}</dd></div>
                <div class="rounded-xl bg-mist p-4"><dt class="text-xs font-semibold text-navy-300">Payment</dt><dd class="mt-1 text-sm font-bold">Mobile money, bank or cash</dd></div>
            </dl>
            <p class="mt-4 text-sm text-navy-500">These are indicative ranges. Providers send you a firm quotation in ZMW after seeing your description and photos – nothing is charged until you accept a quote.</p>
        </div>
        @if($related->isNotEmpty())
        <div>
            <h2 class="font-display text-xl font-bold">Related services</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($related as $r)<a href="{{ route('services.show', [$category, $r]) }}" class="rounded-full border border-navy-100 px-4 py-2 text-sm font-semibold hover:border-brand-500 hover:text-brand-600">{{ $r->name }}</a>@endforeach
            </div>
        </div>
        @endif
    </div>
    <aside class="card card-pad h-fit">
        <h2 class="font-display text-xl font-bold">Need this now?</h2>
        <p class="mt-2 text-sm text-navy-500">Nearby verified professionals will be alerted immediately.</p>
        <a href="{{ route('requests.emergency', ['category' => $category->slug, 'service' => $service->slug]) }}" class="btn btn-primary mt-5 w-full"><x-icon name="siren" class="h-4 w-4" /> Emergency request</a>
        <a href="{{ route('requests.create', ['category' => $category->slug, 'service' => $service->slug]) }}" class="btn btn-outline mt-2 w-full">Standard request</a>
    </aside>
</section>
@endsection
