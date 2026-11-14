@extends('layouts.app', ['title' => 'Contact us'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Contact', 'heading' => "We're here 24/7", 'sub' => 'For an active emergency, submit a request – it is the fastest way to reach providers.'])
<section class="container-x grid gap-8 py-12 lg:grid-cols-[1fr_380px]">
    <form method="POST" action="{{ route('contact.send') }}" class="card card-pad space-y-4">
        @csrf
        <h2 class="font-display text-xl font-bold">Send us a message</h2>
        <div class="grid gap-4 sm:grid-cols-2"><x-field name="name" label="Name" :required="true" /><x-field name="phone" type="tel" label="Phone" :required="true" /></div>
        <x-field name="message" type="textarea" label="Message" :required="true" rows="5" />
        <button class="btn btn-primary">Send message</button>
    </form>
    <aside class="space-y-3">
        <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="card card-pad flex items-center gap-3 hover:shadow-lg"><x-icon name="phone" class="text-brand-500" /> <span><b class="block">Call</b>{{ $supportPhone }}</span></a>
        <a href="{{ whatsapp_link($supportWhatsapp, 'Hello HANDY NOW') }}" target="_blank" rel="noopener" class="card card-pad flex items-center gap-3 hover:shadow-lg"><x-icon name="whatsapp" class="text-[#25D366]" /> <span><b class="block">WhatsApp</b>Chat with support</span></a>
        <a href="mailto:{{ $supportEmail }}" class="card card-pad flex items-center gap-3 hover:shadow-lg"><x-icon name="mail" class="text-brand-500" /> <span><b class="block">Email</b>{{ $supportEmail }}</span></a>
    </aside>
</section>
@endsection
