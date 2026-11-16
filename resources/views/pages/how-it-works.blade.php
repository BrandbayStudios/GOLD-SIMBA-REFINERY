@extends('layouts.app', ['title' => 'How it works'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'How it works', 'heading' => 'Help on the way in minutes', 'sub' => 'HANDY NOW works like a ride-hailing app for emergency repairs: you send one request, nearby verified professionals respond, you choose.'])
<section class="container-x grid gap-12 py-14 lg:grid-cols-2">
    <div>
        <h2 class="font-display text-2xl font-bold">For customers</h2>
        <ol class="mt-6 space-y-5">
            @foreach([
                ['Select the service you need', 'Pick a category such as Roadside Assistance or Plumbing, then the specific problem.'],
                ['Share your location and describe the problem', 'Tap “Use my current location” or drop a pin. Add directions and photos.'],
                ['Receive responses from available technicians', 'We alert approved, online providers nearby. They send quotes in Kwacha with an arrival time.'],
                ['Confirm your preferred provider', 'Compare price, rating and ETA, then accept one. Their contact details are shared with you.'],
                ['Track the job and complete payment', 'Follow their arrival on the map, chat in-app or on WhatsApp, pay by mobile money, bank or cash, and rate them.'],
            ] as $i => [$t, $d])
                <li class="flex gap-4"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 font-bold text-white">{{ $i + 1 }}</span><div><p class="font-bold">{{ $t }}</p><p class="text-navy-500">{{ $d }}</p></div></li>
            @endforeach
        </ol>
        <a href="{{ route('requests.emergency') }}" class="btn btn-primary mt-8">Request help now</a>
    </div>
    <div>
        <h2 class="font-display text-2xl font-bold">For service providers</h2>
        <ol class="mt-6 space-y-5">
            @foreach([
                ['Register and upload your documents', 'Your NRC (and PACRA certificate for businesses) is reviewed by our team.'],
                ['Choose your services and areas', 'Select your categories, base location and how far you will travel.'],
                ['Go online', 'Switch to Online to receive job alerts by SMS and in-app notifications.'],
                ['Send quotations', 'Review the problem, photos and distance, then quote your price and arrival time.'],
                ['Do great work and get paid', 'Update the job status as you go, confirm payment, and build your rating. A small platform commission applies to completed jobs.'],
            ] as $i => [$t, $d])
                <li class="flex gap-4"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-900 font-bold text-white">{{ $i + 1 }}</span><div><p class="font-bold">{{ $t }}</p><p class="text-navy-500">{{ $d }}</p></div></li>
            @endforeach
        </ol>
        <a href="{{ route('provider.register') }}" class="btn btn-navy mt-8">Become a provider</a>
    </div>
</section>
@endsection
