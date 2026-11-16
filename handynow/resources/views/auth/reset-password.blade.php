@extends('layouts.auth', ['title' => 'Reset password'])
@section('content')
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">Choose a new password</h1>
    <p class="mt-1 text-sm text-navy-500">Type the 6-digit code we texted you, then your new password.</p>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <x-field name="identifier" label="Phone number or email" :value="$identifier" :required="true" autocomplete="username" />
        <x-field name="code" label="6-digit code" :required="true" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="123456" class="text-center font-mono text-xl tracking-[0.4em]" />
        <x-field name="password" type="password" label="New password" :required="true" autocomplete="new-password" hint="8+ characters, letters and numbers" />
        <x-field name="password_confirmation" type="password" label="Confirm new password" :required="true" autocomplete="new-password" />
        <button class="btn btn-primary w-full">Change password</button>
    </form>
    <p class="mt-6 text-center text-sm text-navy-500">No code? Codes last 15 minutes. <a href="{{ route('password.request') }}" class="font-semibold text-brand-600">Request a new one</a></p>
</div>
@endsection
