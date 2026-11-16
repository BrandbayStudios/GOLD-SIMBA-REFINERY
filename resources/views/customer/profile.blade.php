@extends('layouts.dashboard', ['title' => 'My profile'])
@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="card card-pad space-y-4">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Personal details</h2>
        <div class="flex items-center gap-4"><img src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-2xl"><x-field name="avatar" type="file" accept="image/*" /></div>
        <x-field name="name" label="Full name" :value="$user->name" :required="true" />
        <x-field name="email" type="email" label="Email" :value="$user->email" :required="true" />
        <x-field name="phone" type="tel" label="Phone" :value="$user->phone" :required="true" />
        <x-field name="address" label="Default address" :value="$user->address" />
        <x-field name="city" label="Town / city" :value="$user->city" />
        <button class="btn btn-primary">Save changes</button>
    </form>
    <form method="POST" action="{{ route('customer.profile.password') }}" class="card card-pad h-fit space-y-4">
        @csrf @method('PUT')
        <h2 class="font-display text-lg font-bold">Change password</h2>
        <x-field name="current_password" type="password" label="Current password" :required="true" />
        <x-field name="password" type="password" label="New password" :required="true" />
        <x-field name="password_confirmation" type="password" label="Confirm new password" :required="true" />
        <button class="btn btn-navy">Update password</button>
    </form>
</div>
@endsection
