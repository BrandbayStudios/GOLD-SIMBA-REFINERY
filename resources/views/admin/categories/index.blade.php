@extends('layouts.dashboard', ['title' => 'Services & pricing'])
@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="card overflow-x-auto">
        <table class="table-x"><thead><tr><th>Category</th><th>Services</th><th>Providers</th><th>Requests</th><th>Status</th><th></th></tr></thead><tbody>
            @foreach($categories as $c)
                <tr>
                    <td><span class="flex items-center gap-2 font-semibold"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$c->icon" class="h-4 w-4" /></span>{{ $c->name }}</span></td>
                    <td>{{ $c->services_count }}</td><td>{{ $c->providers_count }}</td><td>{{ $c->requests_count }}</td>
                    <td><x-status-badge :status="$c->is_active ? 'active' : 'suspended'" :label="$c->is_active ? 'Active' : 'Hidden'" /></td>
                    <td><a href="{{ route('admin.categories.edit', $c) }}" class="btn btn-sm btn-outline">Edit & pricing</a></td>
                </tr>
            @endforeach
        </tbody></table>
    </div>
    <form method="POST" action="{{ route('admin.categories.store') }}" class="card card-pad h-fit space-y-4">
        @csrf
        <h2 class="font-display text-lg font-bold">New category</h2>
        <x-field name="name" label="Name" :required="true" />
        <div><label class="label" for="icon">Icon</label><select id="icon" name="icon" class="input">@foreach($icons as $i)<option>{{ $i }}</option>@endforeach</select></div>
        <x-field name="short_description" label="Short description" />
        <x-field name="sort_order" type="number" label="Display order" value="99" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="accent-brand-500"> Visible to customers</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_emergency" value="1" checked class="accent-brand-500"> Available for emergency requests</label>
        <button class="btn btn-primary w-full">Create category</button>
    </form>
</div>
@endsection
