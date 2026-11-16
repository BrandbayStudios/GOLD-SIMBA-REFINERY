@extends('layouts.auth', ['title' => 'Forgot password'])
@section('content')
<div class="card card-pad sm:p-8">
    <h1 class="font-display text-2xl font-bold">Forgot your password?</h1>
    @if($smsAvailable)
        <p class="mt-1 text-sm text-navy-500">Enter the phone number (or email) of your account. We will text a 6-digit code to the phone number on the account.</p>
        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <x-field name="identifier" label="Phone number or email" :required="true" autocomplete="username" placeholder="097 123 4567" autofocus />
            <button class="btn btn-primary w-full">Send me a code</button>
        </form>
        <p class="mt-4 text-center text-sm text-navy-500">Already have a code? <a href="{{ route('password.reset') }}" class="font-semibold text-brand-600">Enter it here</a></p>
    @else
        <p class="mt-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Resetting by text message is not switched on yet. Please contact our support team and we will reset your password for you.</p>
        <div class="mt-4 grid gap-2">
            <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="btn btn-navy"><x-icon name="phone" class="h-4 w-4" /> Call {{ $supportPhone }}</a>
            <a href="{{ whatsapp_link($supportWhatsapp, 'Hello HANDY NOW, I forgot my password. My phone number is ') }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-icon name="whatsapp" class="h-4 w-4" /> WhatsApp support</a>
        </div>
    @endif
    <p class="mt-6 text-center text-sm text-navy-500"><a href="{{ route('login') }}" class="font-semibold text-brand-600">← Back to log in</a></p>
</div>
@endsection
