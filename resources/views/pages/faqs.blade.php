@extends('layouts.app', ['title' => 'FAQs'])
@php $faqs = [
    ['Do I need an account to request help?', 'No. Anyone can submit an emergency request with a name and phone number. We send a secure tracking link by SMS. Creating an account lets you see all your requests, payments and ratings in one place.'],
    ['How are providers verified?', 'Every provider uploads their National Registration Card (NRC); registered businesses also provide their PACRA certificate. Our team reviews these before the provider can go online and receive jobs. Customers rate every completed job.'],
    ['How much will it cost?', 'Each service shows an indicative price range in Kwacha. Providers send you a firm quotation (including any call-out fee) before work begins, and you only pay the amount you accepted.'],
    ['How do I pay?', 'You can pay your provider via MTN Mobile Money, Airtel Money, Zamtel Kwacha, bank transfer or cash. Record the payment in HANDY NOW and the provider confirms receipt.'],
    ['Can I track my provider?', 'Yes. Once a provider is on the way, their location is shared on your tracking map with distance and estimated arrival time.'],
    ['What if something goes wrong?', 'Report a complaint from your dashboard or contact support on WhatsApp. Our team reviews disputes and can suspend providers who break our standards.'],
    ['Which areas do you cover?', 'We are launching in Lusaka and expanding across Zambia. Requests outside a provider’s coverage are flagged to our support team, who will try to assign someone.'],
    ['How do providers get paid and what is the commission?', 'Customers pay providers directly. HANDY NOW charges a platform commission on completed, paid jobs, which is shown transparently on the provider’s earnings page.'],
]; @endphp
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Help centre', 'heading' => 'Frequently asked questions'])
<section class="container-x max-w-3xl py-12">
    <div class="space-y-3">
        @foreach($faqs as [$q, $a])
            <details class="card card-pad group"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold">{{ $q }}<x-icon name="chevron-down" class="h-5 w-5 shrink-0 transition group-open:rotate-180" /></summary><p class="mt-3 leading-relaxed text-navy-600">{{ $a }}</p></details>
        @endforeach
    </div>
    <p class="mt-8 text-center text-navy-500">Still need help? <a href="{{ route('contact') }}" class="font-semibold text-brand-600">Contact us</a></p>
</section>
@endsection
