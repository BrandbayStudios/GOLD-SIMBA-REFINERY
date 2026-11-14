@extends('layouts.app', ['title' => 'Privacy policy'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Legal', 'heading' => 'Privacy policy', 'sub' => 'How HANDY NOW handles your personal information, in line with the Zambian Data Protection Act No. 3 of 2021.'])
<section class="container-x max-w-3xl space-y-6 py-12 leading-relaxed text-navy-700">
    <div><h2 class="font-display text-xl font-bold text-navy-900">What we collect</h2><p class="mt-2">Name, phone number, email, request details, photos you upload, and the location you share. For providers: NRC and business registration documents, service areas and live location while on an active job.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">How we use it</h2><p class="mt-2">To match requests with nearby providers, send SMS and in-app updates, process payments and commissions, verify providers, and resolve disputes.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">Sharing</h2><p class="mt-2">Your phone number and location are shared with a provider only after you accept their quotation. Identity documents are stored privately and are only visible to verification staff.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">Your rights</h2><p class="mt-2">You may request access to, correction of, or deletion of your data by contacting {{ $supportEmail }}.</p></div>
</section>
@endsection
