<section class="bg-navy-900">
    <div class="container-x py-12 sm:py-16">
        @isset($eyebrow)<p class="text-sm font-bold uppercase tracking-wider text-brand-400">{{ $eyebrow }}</p>@endisset
        <h1 class="mt-2 font-display text-3xl font-extrabold text-white sm:text-5xl">{{ $heading }}</h1>
        @isset($sub)<p class="mt-4 max-w-2xl text-lg text-navy-100">{{ $sub }}</p>@endisset
    </div>
</section>
