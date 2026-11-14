@extends('layouts.auth', ['title' => 'Create account', 'image' => '/images/plumbing.webp'])
@section('content')
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">Create your account</h1>
    <p class="mt-1 text-sm text-navy-500">Track requests, compare quotes and rate providers.</p>
    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <x-field name="name" label="Full name" :required="true" autocomplete="name" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="phone" type="tel" label="Phone" :required="true" placeholder="097 123 4567" autocomplete="tel" />
            <x-field name="city" label="Town / city" placeholder="Lusaka" />
        </div>
        <x-field name="email" type="email" label="Email" :required="true" autocomplete="email" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="password" type="password" label="Password" :required="true" autocomplete="new-password" hint="8+ characters, letters and numbers" />
            <x-field name="password_confirmation" type="password" label="Confirm password" :required="true" autocomplete="new-password" />
        </div>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="mt-1 accent-brand-500" @checked(old('terms'))> <span>I agree to the <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-brand-600">terms</a> and <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-brand-600">privacy policy</a>.</span></label>
        @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn btn-primary w-full">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-navy-500">Already registered? <a href="{{ route('login') }}" class="font-semibold text-brand-600">Log in</a></p>
</div>
@endsection
