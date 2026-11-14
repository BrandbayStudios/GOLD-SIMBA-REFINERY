@extends('layouts.app', ['title' => 'Professionals'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Verified professionals', 'heading' => 'Meet the people who show up', 'sub' => 'Every provider is checked against their NRC and, for businesses, PACRA registration before they can accept jobs.'])
<section class="container-x py-10" x-data="nearby({ url: '{{ route('providers.nearby') }}' })">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="category" class="input w-auto" onchange="this.form.submit()" aria-label="Category">
            <option value="">All categories</option>
            @foreach($categories as $c)<option value="{{ $c->slug }}" @selected(request('category') === $c->slug)>{{ $c->name }}</option>@endforeach
        </select>
        <label class="flex items-center gap-2 rounded-xl border border-navy-100 px-4 py-3 text-sm font-semibold">
            <input type="checkbox" name="online" value="1" @checked(request()->boolean('online')) onchange="this.form.submit()" class="accent-brand-500"> Online now
        </label>
        <button type="button" @click="locate" class="btn btn-outline"><x-icon name="crosshair" class="h-4 w-4" /> <span x-text="busy ? 'Locating…' : 'Distance from me'"></span></button>
        <span x-show="error" x-text="error" x-cloak class="text-sm text-red-600"></span>
    </form>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse($providers as $p)
            @include('partials.provider-card', ['p' => $p])
        @empty
            <p class="col-span-full rounded-2xl bg-mist p-8 text-center text-navy-500">No providers match these filters.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $providers->links() }}</div>
</section>
@endsection
