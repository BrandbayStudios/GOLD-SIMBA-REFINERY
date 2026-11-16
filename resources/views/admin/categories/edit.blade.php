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
            <button class="btn btn-primary w-full">Save category</button>
        </form>
        <form method="POST" action="{{ route('admin.services.store', $category) }}" class="card card-pad space-y-3">
            @csrf
            <h2 class="font-display text-lg font-bold">Add a service</h2>
            <x-field name="name" label="Service name" :required="true" />
            <div class="grid grid-cols-3 gap-2">
                <x-field name="callout_fee" type="number" step="0.01" label="Call-out K" />
                <x-field name="min_price" type="number" step="0.01" label="Min K" />
                <x-field name="max_price" type="number" step="0.01" label="Max K" />
            </div>
            <x-field name="description" type="textarea" label="Description" rows="2" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="accent-brand-500"> Active</label>
            <button class="btn btn-navy w-full">Add service</button>
        </form>
    </div>
    <div class="card card-pad">
        <h2 class="font-display text-lg font-bold">Services & indicative pricing (ZMW)</h2>
        <p class="mt-1 text-sm text-navy-500">Shown to customers as guide prices and pre-filled as call-out fees on provider quotes.</p>
        <div class="mt-4 space-y-3">
            @foreach($category->services as $s)
                <form method="POST" action="{{ route('admin.services.update', $s) }}" class="grid items-end gap-2 rounded-xl border border-navy-50 p-3 sm:grid-cols-[1.5fr_repeat(3,1fr)_auto_auto]">
                    @csrf @method('PUT')
                    <div><label class="text-[11px] font-bold uppercase text-navy-300">Name</label><input name="name" value="{{ $s->name }}" class="input py-2" required><input type="hidden" name="description" value="{{ $s->description }}"></div>
                    <div><label class="text-[11px] font-bold uppercase text-navy-300">Call-out</label><input name="callout_fee" type="number" step="0.01" value="{{ $s->callout_fee }}" class="input py-2"></div>
                    <div><label class="text-[11px] font-bold uppercase text-navy-300">Min</label><input name="min_price" type="number" step="0.01" value="{{ $s->min_price }}" class="input py-2"></div>
                    <div><label class="text-[11px] font-bold uppercase text-navy-300">Max</label><input name="max_price" type="number" step="0.01" value="{{ $s->max_price }}" class="input py-2"></div>
                    <label class="flex items-center gap-1 pb-3 text-xs"><input type="checkbox" name="is_active" value="1" @checked($s->is_active) class="accent-brand-500"> Active</label>
                    <button class="btn btn-sm btn-outline mb-1">Save</button>
                </form>
            @endforeach
        </div>
    </div>
</div>
@endsection
