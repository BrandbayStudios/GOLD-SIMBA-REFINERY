<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="min-h-screen bg-mist">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-navy-900 lg:block">
        <img src="{{ $image ?? '/images/mechanical.webp' }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-t from-navy-900 via-navy-900/40"></div>
        <div class="relative flex h-full flex-col justify-between p-12">
            @include('partials.logo', ['light' => true])
            <div>
                <p class="font-display text-4xl font-extrabold leading-tight text-white">Emergency Services.<br><span class="text-brand-500">One Call Away.</span></p>
                <p class="mt-4 max-w-md text-navy-100">Verified towing, electrical, plumbing, gas and mechanical professionals across Zambia.</p>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-center p-4 sm:p-8">
        <div class="w-full {{ $wide ?? false ? 'max-w-3xl' : 'max-w-md' }}">
            <div class="mb-6 lg:hidden">@include('partials.logo')</div>
            @include('partials.flash')
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
