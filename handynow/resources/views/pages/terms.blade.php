@extends('layouts.app', ['title' => 'Terms & conditions'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Legal', 'heading' => 'Terms & conditions', 'sub' => 'Last updated '.now()->format('F Y').'. This MVP text is a starting point and should be reviewed by a Zambian legal advisor before launch.'])
<section class="container-x max-w-3xl space-y-6 py-12 leading-relaxed text-navy-700">
    <div><h2 class="font-display text-xl font-bold text-navy-900">1. The platform</h2><p class="mt-2">HANDY NOW is a marketplace that connects customers with independent service providers. Providers are not employees of HANDY NOW and are responsible for the quality and safety of their work.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">2. Requests and quotations</h2><p class="mt-2">Customers must give accurate information about the problem and location. Quotations are offers by providers in Zambian Kwacha (ZMW). A binding agreement exists between the customer and provider once a quotation is accepted.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">3. Payments and commission</h2><p class="mt-2">Customers pay providers using the agreed method. Providers agree to pay HANDY NOW the platform commission shown in their dashboard on every completed and paid job. Payments recorded on the platform must be truthful.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">4. Provider obligations</h2><p class="mt-2">Providers must submit genuine identity and business documents, hold any licences required for their trade, arrive within the time they quote where reasonably possible, and treat customers with respect.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">5. Cancellations</h2><p class="mt-2">Customers may cancel before work starts. Repeated late cancellations or no-shows by either party may lead to account suspension.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">6. Safety</h2><p class="mt-2">In life-threatening situations contact Zambia Police (991), Fire (993) or emergency medical services first. For gas leaks, leave the building and avoid using switches or open flames.</p></div>
    <div><h2 class="font-display text-xl font-bold text-navy-900">7. Suspension</h2><p class="mt-2">HANDY NOW may suspend accounts that provide false information, engage in fraud, or receive serious complaints.</p></div>
</section>
@endsection
