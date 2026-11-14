<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    @stack('head')
</head>
<body class="flex min-h-screen flex-col">
<header x-data="{ open: false, svc: false }" class="sticky top-0 z-40 border-b border-navy-50/80 bg-white/90 backdrop-blur-lg">
    <div class="container-x flex h-[72px] items-center justify-between gap-4">
        @include('partials.logo')

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
            <div class="relative" @mouseleave="svc = false">
                <button type="button" @click="svc = !svc" @mouseenter="svc = true" class="btn btn-ghost px-3">Services <x-icon name="chevron-down" class="h-4 w-4" /></button>
                <div x-show="svc" x-cloak x-transition class="absolute left-0 top-full w-[560px] pt-2">
                    <div class="card grid grid-cols-2 gap-1 p-3">
                        @foreach($navCategories as $c)
                            <a href="{{ route('services.category', $c) }}" class="flex items-center gap-3 rounded-xl p-2.5 text-sm font-semibold hover:bg-mist">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$c->icon" class="h-4.5 w-4.5" /></span>{{ $c->name }}
                            </a>
                        @endforeach
                        <a href="{{ route('services.index') }}" class="col-span-2 mt-1 rounded-xl bg-mist p-3 text-center text-sm font-bold text-navy-900 hover:bg-navy-50">Browse all services →</a>
                    </div>
                </div>
            </div>
            <a href="{{ route('providers.index') }}" class="btn btn-ghost px-3">Professionals</a>
            <a href="{{ route('how-it-works') }}" class="btn btn-ghost px-3">How it works</a>
            <a href="{{ route('track.lookup') }}" class="btn btn-ghost px-3">Track request</a>
        </nav>

        <div class="flex items-center gap-2">
            @auth
                @include('partials.bell')
                <a href="{{ auth()->user()->dashboardRoute() }}" class="hidden items-center gap-2 rounded-xl border border-navy-50 py-1.5 pl-1.5 pr-3 text-sm font-semibold hover:bg-mist sm:flex">
                    <img src="{{ auth()->user()->avatarUrl() }}" alt="" class="h-7 w-7 rounded-lg object-cover"> Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost hidden sm:inline-flex">Log in</a>
            @endauth
            <a href="{{ route('requests.emergency') }}" class="btn btn-primary hidden sm:inline-flex"><x-icon name="siren" class="h-4 w-4" /> Emergency</a>
            <button type="button" @click="open = !open" class="flex h-10 w-10 items-center justify-center rounded-xl hover:bg-mist lg:hidden" aria-label="Menu" :aria-expanded="open">
                <x-icon name="menu" x-show="!open" /><x-icon name="x" x-show="open" x-cloak />
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-transition class="border-t border-navy-50 bg-white lg:hidden">
        <nav class="container-x grid gap-1 py-4">
            <a href="{{ route('services.index') }}" class="rounded-xl px-3 py-3 font-semibold hover:bg-mist">All services</a>
            <a href="{{ route('providers.index') }}" class="rounded-xl px-3 py-3 font-semibold hover:bg-mist">Professionals</a>
            <a href="{{ route('how-it-works') }}" class="rounded-xl px-3 py-3 font-semibold hover:bg-mist">How it works</a>
            <a href="{{ route('track.lookup') }}" class="rounded-xl px-3 py-3 font-semibold hover:bg-mist">Track a request</a>
            @auth
                <a href="{{ auth()->user()->dashboardRoute() }}" class="rounded-xl px-3 py-3 font-semibold hover:bg-mist">My dashboard</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-xl px-3 py-3 text-left font-semibold hover:bg-mist">Log out</button></form>
            @else
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" class="btn btn-outline">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-navy">Sign up</a>
                </div>
                <a href="{{ route('provider.register') }}" class="mt-1 rounded-xl px-3 py-3 text-sm font-semibold text-brand-600 hover:bg-mist">Become a service provider →</a>
            @endauth
        </nav>
    </div>
</header>

<main class="flex-1">
    @if(session('success') || $errors->any())
        <div class="container-x pt-6">@include('partials.flash')</div>
    @endif
    @yield('content')
</main>

{{-- Always-reachable emergency button on phones --}}
@unless(request()->routeIs('requests.*'))
<a href="{{ route('requests.emergency') }}" class="fixed bottom-4 right-4 z-30 flex items-center gap-2 rounded-full bg-brand-500 px-5 py-3.5 font-bold text-white shadow-2xl shadow-brand-500/40 sm:hidden">
    <span class="pulse-dot text-white"></span> Emergency
</a>
@endunless

@include('partials.footer')
@stack('scripts')
</body>
</html>
