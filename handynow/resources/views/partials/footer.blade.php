<footer class="bg-navy-900 text-navy-100">
    <div class="container-x grid gap-10 py-14 md:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            @include('partials.logo', ['light' => true])
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-navy-200">Emergency Services. One Call Away. HANDY NOW connects households, businesses and motorists across Zambia with verified professionals – day and night.</p>
            <div class="mt-5 flex gap-2">
                @foreach(['facebook' => 'https://facebook.com', 'instagram' => 'https://instagram.com', 'x-social' => 'https://x.com', 'linkedin' => 'https://linkedin.com'] as $icon => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/5 text-navy-100 hover:bg-brand-500 hover:text-white" aria-label="{{ ucfirst($icon) }}"><x-icon :name="$icon" class="h-4 w-4" /></a>
                @endforeach
            </div>
        </div>
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Services</h3>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach($navCategories->take(6) as $c)
                    <li><a href="{{ route('services.category', $c) }}" class="hover:text-brand-400">{{ $c->name }}</a></li>
                @endforeach
                <li><a href="{{ route('services.index') }}" class="font-semibold text-brand-400 hover:text-brand-200">All services →</a></li>
            </ul>
        </div>
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Company</h3>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a href="{{ route('how-it-works') }}" class="hover:text-brand-400">How it works</a></li>
                <li><a href="{{ route('provider.register') }}" class="hover:text-brand-400">Become a provider</a></li>
                <li><a href="{{ route('faqs') }}" class="hover:text-brand-400">FAQs</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-brand-400">Terms & conditions</a></li>
                <li><a href="{{ route('privacy') }}" class="hover:text-brand-400">Privacy policy</a></li>
                <li><a href="{{ route('contact') }}" class="hover:text-brand-400">Contact us</a></li>
            </ul>
        </div>
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">24/7 Support</h3>
            <ul class="mt-4 space-y-3 text-sm">
                <li><a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="flex items-center gap-2 hover:text-brand-400"><x-icon name="phone" class="h-4 w-4" /> {{ $supportPhone }}</a></li>
                <li><a href="{{ whatsapp_link($supportWhatsapp, 'Hello HANDY NOW, I need help.') }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-brand-400"><x-icon name="whatsapp" class="h-4 w-4" /> WhatsApp us</a></li>
                <li><a href="mailto:{{ $supportEmail }}" class="flex items-center gap-2 hover:text-brand-400"><x-icon name="mail" class="h-4 w-4" /> {{ $supportEmail }}</a></li>
                <li class="flex items-center gap-2"><x-icon name="map-pin" class="h-4 w-4" /> Lusaka, Zambia</li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-x flex flex-col gap-2 py-6 text-xs text-navy-300 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} HANDY NOW. All rights reserved. Prices shown in Zambian Kwacha (ZMW).</p>
            <p>Payments: MTN MoMo · Airtel Money · Zamtel Kwacha · Bank · Cash</p>
        </div>
    </div>
</footer>
