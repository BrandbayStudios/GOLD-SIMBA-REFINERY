@extends('layouts.app', ['title' => 'Track a request'])
@section('content')
<section class="container-x flex justify-center py-16">
    <div class="card card-pad w-full max-w-md">
        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="navigation" /></span>
        <h1 class="mt-4 font-display text-2xl font-bold">Track your request</h1>
        <p class="mt-1 text-sm text-navy-500">Enter the reference from your SMS (e.g. HN-AB12CD) and the phone number you used.</p>
        <form method="POST" action="{{ route('track.find') }}" class="mt-6 space-y-4">
            @csrf
            <x-field name="reference" label="Request reference" :required="true" placeholder="HN-XXXXXX" class="uppercase" />
            <x-field name="phone" type="tel" label="Phone number" :required="true" placeholder="097 123 4567" />
            <button class="btn btn-primary w-full">Track request</button>
        </form>
    </div>
</section>
@endsection
