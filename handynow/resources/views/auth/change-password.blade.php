@extends('layouts.auth', ['title' => 'Change password'])
@section('content')
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">{{ $forced ? 'Choose your own password' : 'Change password' }}</h1>
    @if($forced)<p class="mt-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Support gave you a temporary password. Please choose a new one to continue.</p>@endif
    <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-4">
        @csrf @method('PUT')
        <x-field name="current_password" type="password" :label="$forced ? 'Temporary password' : 'Current password'" :required="true" autocomplete="current-password" />
        <x-field name="password" type="password" label="New password" :required="true" autocomplete="new-password" hint="8+ characters, letters and numbers" />
        <x-field name="password_confirmation" type="password" label="Confirm new password" :required="true" autocomplete="new-password" />
        <button class="btn btn-primary w-full">Save new password</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm font-semibold text-navy-500 underline">Log out</button></form>
</div>
@endsection
