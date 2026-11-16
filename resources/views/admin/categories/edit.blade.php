@extends('layouts.dashboard', ['title' => $category->name])
@section('content')
<div class="grid gap-6 xl:grid-cols-[380px_1fr]">
    <div class="space-y-6">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <h2 class="font-display text-lg font-bold">Category details</h2>
            <x-field name="name" label="Name" :value="$category->name" :required="true" />
            <div><label class="label" for="icon">Icon</label><select id="icon" name="icon" class="input">@foreach($icons as $i)<option @selected($category->icon === $i)>{{ $i }}</option>@endforeach</select></div>
            <x-field name="short_description" label="Short description" :value="$category->short_description" />
            <x-field name="description" type="textarea" label="Description" :value="$category->description" rows="3" />
            <x-field name="sort_order" type="number" label="Display order" :value="$category->sort_order" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="accent-brand-500"> Visible to customers</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_emergency" value="1" @checked($category->is_emergency) class="accent-brand-500"> Emergency category</label>
            <div class="rounded-xl bg-mist p-3">
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="license_required" value="1" @checked($category->license_required) class="accent-brand-500"> Providers must upload a licence / certificate for this service</label>
                <x-field name="license_hint" label="What to upload (shown to providers)" :value="$category->license_hint" />
            </div>
            <button class="btn btn-primary w-full">Save category</button>
        </form>
    </div>
    <div class="min-w-0 space-y-6">
        <div class="card card-pad">
            <h2 class="font-display text-lg font-bold">Services, pricing rules & booking fees</h2>
            <p class="mt-1 text-sm text-navy-500"><b>Open</b> keeps provider quotes free. <b>Price ceiling</b> blocks quotes above a maximum. <b>Fixed price</b> shows one price, skips quotes and gives the job to the first provider who accepts. All prices are in ZMW.</p>
            <div class="mt-4 space-y-4">
                @foreach($category->services as $s)
                    <details class="rounded-2xl border border-navy-50" @if($errors->any() && old('_service') == $s->id) open @endif>
                        <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 p-4">
                            <span class="font-bold">{{ $s->name }} @unless($s->is_active)<span class="badge badge-grey">Hidden</span>@endunless</span>
                            <span class="badge {{ $s->pricing_mode === 'fixed' ? 'badge-green' : ($s->pricing_mode === 'capped' ? 'badge-amber' : 'badge-grey') }}">{{ $s->pricing_mode === 'fixed' ? 'Fixed '.zmw($s->fixed_price, 0) : ($s->pricing_mode === 'capped' ? 'Ceiling '.zmw($s->max_price, 0) : 'Open quotes') }}</span>
                        </summary>
                        <form method="POST" action="{{ route('admin.services.update', $s) }}" class="space-y-3 border-t border-navy-50 p-4">
                            @csrf @method('PUT') <input type="hidden" name="_service" value="{{ $s->id }}">
                            @include('admin.categories.service-fields', ['s' => $s])
                            <button class="btn btn-sm btn-primary">Save "{{ $s->name }}"</button>
                        </form>
                    </details>
                @endforeach
            </div>
        </div>
        <form method="POST" action="{{ route('admin.services.store', $category) }}" class="card card-pad space-y-3">
            @csrf
            <h2 class="font-display text-lg font-bold">Add a service</h2>
            @include('admin.categories.service-fields', ['s' => null])
            <button class="btn btn-navy">Add service</button>
        </form>
    </div>
</div>
@endsection
