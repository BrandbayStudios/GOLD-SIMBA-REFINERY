@php
    $u = auth()->user();
    $nav = match ($u->role) {
        'admin' => [
            ['admin.dashboard', 'Overview', 'grid', 'admin.dashboard'],
            ['admin.requests.index', 'Requests', 'siren', 'admin.requests.*'],
            ['admin.providers.index', 'Providers', 'shield', 'admin.providers.*'],
            ['admin.users.index', 'Users', 'users', 'admin.users.*'],
            ['admin.payments.index', 'Payments', 'wallet', 'admin.payments.*'],
            ['admin.complaints.index', 'Complaints', 'flag', 'admin.complaints.*'],
            ['admin.categories.index', 'Services & pricing', 'tag', 'admin.categories.*'],
            ['admin.reports', 'Reports', 'chart', 'admin.reports*'],
            ['admin.settings', 'Settings', 'settings', 'admin.settings*'],
            ['admin.sms-log', 'SMS log', 'message', 'admin.sms-log'],
        ],
        'provider' => [
            ['provider.dashboard', 'Dashboard', 'grid', 'provider.dashboard'],
            ['provider.jobs.index', 'Jobs', 'list', 'provider.jobs.*'],
            ['provider.earnings', 'Earnings', 'wallet', 'provider.earnings'],
            ['provider.reviews', 'Reviews', 'star', 'provider.reviews'],
            ['provider.profile', 'Profile & verification', 'user', 'provider.profile'],
        ],
        default => [
            ['customer.dashboard', 'Dashboard', 'grid', 'customer.dashboard'],
            ['customer.requests.index', 'My requests', 'list', 'customer.requests.*'],
            ['customer.payments', 'Payments', 'wallet', 'customer.payments'],
            ['customer.complaints.index', 'Complaints', 'flag', 'customer.complaints.*'],
            ['customer.profile', 'Profile', 'user', 'customer.profile'],
        ],
    };
    $roleLabel = ['admin' => 'Administrator', 'provider' => 'Service provider', 'customer' => 'Customer'][$u->role];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    @stack('head')
</head>
<body class="bg-mist" x-data="{ side: false }">
<div class="flex min-h-screen">
    <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-navy-900 transition-transform lg:translate-x-0" :class="side && 'translate-x-0'">
        <div class="flex h-[72px] items-center justify-between px-5">
            @include('partials.logo', ['light' => true])
            <button @click="side = false" class="text-navy-200 lg:hidden" aria-label="Close menu"><x-icon name="x" /></button>
        </div>
        <div class="mx-4 mb-4 flex items-center gap-3 rounded-2xl bg-white/5 p-3">
            <img src="{{ $u->avatarUrl() }}" alt="" class="h-10 w-10 rounded-xl object-cover">
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-white">{{ $u->name }}</p>
                <p class="text-xs text-navy-300">{{ $roleLabel }} @if($u->is_demo)<span class="ml-1 rounded bg-violet-500/30 px-1 text-[10px] text-violet-100">DEMO</span>@endif</p>
            </div>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3" aria-label="Dashboard">
            @foreach($nav as [$route, $label, $icon, $pattern])
                <a href="{{ route($route) }}" class="side-link {{ request()->routeIs($pattern) ? 'active' : '' }}"><x-icon :name="$icon" class="h-[18px] w-[18px]" /> {{ $label }}</a>
            @endforeach
        </nav>
        <div class="space-y-1 border-t border-white/10 p-3">
            @if($u->isCustomer())
                <a href="{{ route('requests.emergency') }}" class="btn btn-primary mb-2 w-full"><x-icon name="siren" class="h-4 w-4" /> New emergency request</a>
            @endif
            <a href="{{ route('home') }}" class="side-link"><x-icon name="home" class="h-[18px] w-[18px]" /> Public website</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="side-link w-full"><x-icon name="logout" class="h-[18px] w-[18px]" /> Log out</button></form>
        </div>
    </aside>
    <div x-show="side" x-cloak @click="side = false" class="fixed inset-0 z-40 bg-navy-950/50 lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-30 flex h-[72px] items-center gap-3 border-b border-navy-50 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button @click="side = true" class="flex h-10 w-10 items-center justify-center rounded-xl hover:bg-mist lg:hidden" aria-label="Open menu"><x-icon name="menu" /></button>
            <div class="min-w-0 flex-1">
                <h1 class="truncate font-display text-lg font-bold sm:text-xl">@yield('heading', $title ?? 'Dashboard')</h1>
            </div>
            @yield('header-actions')
            @include('partials.bell')
        </header>
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
