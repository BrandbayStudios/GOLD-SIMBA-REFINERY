@extends('layouts.app', ['title' => $profile->displayName()])
@section('content')
<section class="bg-navy-900">
    <div class="container-x flex flex-col gap-6 py-12 sm:flex-row sm:items-center">
        <img src="{{ $user->avatarUrl() }}" alt="" class="h-24 w-24 rounded-3xl ring-4 ring-white/10">
        <div class="flex-1 text-white">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-display text-3xl font-extrabold">{{ $user->name }}</h1>
                <span class="badge bg-sky-500/20 text-sky-200"><x-icon name="shield" class="h-3 w-3" /> Verified</span>
                <span class="badge {{ $profile->is_online ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-navy-200' }}">{{ $profile->is_online ? 'Online now' : 'Offline' }}</span>
                <x-demo-badge :show="$user->is_demo" />
            </div>
            @if($profile->business_name)<p class="mt-1 text-navy-200">{{ $profile->business_name }}</p>@endif
            <p class="mt-2 flex items-center gap-2"><x-stars :rating="$profile->rating_avg" /> <b>{{ number_format($profile->rating_avg, 1) }}</b> <span class="text-navy-300">({{ $profile->rating_count }} reviews · {{ $profile->jobs_completed }} jobs on HANDY NOW)</span></p>
        </div>
        <a href="{{ route('requests.create', ['category' => $profile->categories->first()?->slug]) }}" class="btn btn-primary btn-lg">Request a service</a>
    </div>
</section>
<section class="container-x grid gap-8 py-10 lg:grid-cols-[1fr_340px]">
    <div class="space-y-6">
        <div class="card card-pad">
            <h2 class="font-display text-xl font-bold">About</h2>
            <p class="mt-3 leading-relaxed text-navy-700">{{ $profile->bio ?: 'No description yet.' }}</p>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-xl font-bold">Customer reviews</h2>
            <div class="mt-4 divide-y divide-navy-50">
                @forelse($reviews as $r)
                    <div class="py-4">
                        <div class="flex items-center justify-between"><x-stars :rating="$r->rating" /><span class="text-xs text-navy-300">{{ $r->created_at->diffForHumans() }}</span></div>
                        <p class="mt-2 text-navy-700">{{ $r->comment ?: 'No comment left.' }}</p>
                        <p class="mt-1 text-xs font-semibold text-navy-300">{{ $r->customer ? explode(' ', $r->customer->name)[0] : 'Customer' }}</p>
                    </div>
                @empty
                    <p class="py-4 text-navy-500">No reviews yet.</p>
                @endforelse
            </div>
        </div>
    </div>
    <aside class="card card-pad h-fit space-y-4 text-sm">
        <div><p class="text-xs font-bold uppercase text-navy-300">Services</p><div class="mt-2 flex flex-wrap gap-1.5">@foreach($profile->categories as $c)<span class="badge badge-orange">{{ $c->name }}</span>@endforeach</div></div>
        <div><p class="text-xs font-bold uppercase text-navy-300">Areas covered</p><p class="mt-1 font-semibold">{{ collect($profile->operating_areas)->join(', ') }} (up to {{ $profile->service_radius_km }} km)</p></div>
        <div><p class="text-xs font-bold uppercase text-navy-300">Experience</p><p class="mt-1 font-semibold">{{ $profile->years_experience }} years</p></div>
        <div><p class="text-xs font-bold uppercase text-navy-300">Typical response</p><p class="mt-1 font-semibold">~{{ $profile->avg_response_minutes }} minutes</p></div>
        <p class="rounded-xl bg-mist p-3 text-xs text-navy-500">For your safety, contact details are shared once you accept this provider's quotation.</p>
    </aside>
</section>
@endsection
