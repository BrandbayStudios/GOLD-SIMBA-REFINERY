@extends('layouts.auth', ['title' => 'Log in'])
@section('content')
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">Welcome back</h1>
    <p class="mt-1 text-sm text-navy-500">Log in with your email or phone number.</p>
    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <x-field name="login" label="Email or phone" :required="true" autocomplete="username" placeholder="you@example.com or 097…" autofocus />
        <x-field name="password" type="password" label="Password" :required="true" autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" class="accent-brand-500"> Keep me logged in</label>
        <button class="btn btn-primary w-full">Log in</button>
    </form>
    <p class="mt-6 text-center text-sm text-navy-500">New here? <a href="{{ route('register') }}" class="font-semibold text-brand-600">Create a customer account</a> · <a href="{{ route('provider.register') }}" class="font-semibold text-brand-600">Join as a provider</a></p>
</div>
@if(app()->environment('local') || \App\Models\User::where('is_demo', true)->exists())
<div class="mt-4 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-xs text-violet-900">
    <p class="font-bold">Demo accounts (password: Password123)</p>
    <p class="mt-1">Customer: customer@demo.handynow.co.zm · Provider: provider1@demo.handynow.co.zm</p>
    <p class="mt-1 text-violet-700">Demo data is clearly labelled and can be removed by an admin under Settings.</p>
</div>
@endif
@endsection
