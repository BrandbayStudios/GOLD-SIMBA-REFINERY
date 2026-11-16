@extends('layouts.app', ['title' => $category->name])
@section('content')
<section class="relative overflow-hidden bg-navy-900">
    @if($category->image)<img src="{{ $category->image }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25">@endif
    <div class="container-x relative py-14 sm:py-20">
        <a href="{{ route('services.index') }}" class="text-sm font-semibold text-navy-200 hover:text-white">← All services</a>
        <span class="mt-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500 text-white"><x-icon :name="$category->icon" class="h-7 w-7" /></span>
        <h1 class="mt-4 font-display text-4xl font-extrabold text-white sm:text-5xl">{{ $category->name }}</h1>
        <p class="mt-3 max-w-2xl text-lg text-navy-100">{{ $category->description }}</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('requests.emergency', ['category' => $category->slug]) }}" class="btn btn-primary btn-lg"><x-icon name="siren" /> Emergency request</a>
            <a href="{{ route('requests.create', ['category' => $category->slug]) }}" class="btn btn-lg border border-white/20 text-white hover:bg-white/10">Schedule a job</a>
        </div>
    </div>
</section>
<section class="container-x grid gap-10 py-12 lg:grid-cols-[1fr_360px]">
    <div>
        <h2 class="font-display text-2xl font-bold">Services in this category</h2>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            @foreach($category->activeServices as $s)
                <div class="card card-pad flex flex-col">
                    <h3 class="font-bold">{{ $s->name }}</h3>
                    <p class="mt-1 flex-1 text-sm text-navy-500">{{ $s->description }}</p>
                    <div class="mt-4 flex items-center justify-between gap-2">
                        <div class="text-xs"><p class="font-bold text-navy-900">{{ $s->priceRange() }}</p>@if($s->callout_fee > 0)<p class="text-navy-300">Call-out from {{ zmw($s->callout_fee, 0) }}</p>@endif</div>
                        <a href="{{ route('requests.create', ['category' => $category->slug, 'service' => $s->slug]) }}" class="btn btn-sm btn-primary">Request</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <aside>
        <h2 class="font-display text-xl font-bold">Top professionals</h2>
        <div class="mt-5 space-y-3">
            @forelse($providers as $p)
                <a href="{{ route('providers.show', $p->user_id) }}" class="card flex items-center gap-3 p-3 hover:shadow-lg">
                    <img src="{{ $p->user->avatarUrl() }}" alt="" class="h-12 w-12 rounded-xl">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold">{{ $p->displayName() }}</p>
                        <p class="flex items-center gap-1 text-xs text-navy-500"><x-stars :rating="$p->rating_avg" size="h-3 w-3" /> {{ number_format($p->rating_avg, 1) }}</p>
                    </div>
                    <span class="badge {{ $p->is_online ? 'badge-green' : 'badge-grey' }}">{{ $p->is_online ? 'Online' : 'Offline' }}</span>
                </a>
            @empty
                <p class="text-sm text-navy-500">Providers for this category are being onboarded. Submit a request and our team will find someone for you.</p>
            @endforelse
        </div>
    </aside>
</section>
@endsection
