<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="Find trusted venues, décor, photographers, caterers, DJs, beauty artists and car hire across Zambia. Compare packages and contact vendors directly.">
<meta name="theme-color" content="#05392D">

<title>@yield('title', 'EventHub Zambia | Plan. Connect. Celebrate.')</title>

<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Marcellus&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}?v=2">
</head>

<body>

@yield('content')

@include('partials.modals')

{{-- LIGHTBOX --}}
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" onclick="closeLightbox()"><i class="ri-close-line"></i></button>
    <img id="lightboxImage" src="" alt="Portfolio">
</div>

{{-- TOAST --}}
<div class="toast" id="toast"></div>

<script>
    window.EventHub = {
        authenticated: @json(auth()->check()),
        user: @json(auth()->check() ? ['name' => auth()->user()->name, 'email' => auth()->user()->email, 'role' => auth()->user()->role] : null),
        csrf: '{{ csrf_token() }}',
        routes: {
            register: '{{ route('register') }}',
            login: '{{ route('login') }}',
            logout: '{{ route('logout') }}',
            dashboard: '{{ route('dashboard') }}',
            quotes: '{{ route('quotes.store') }}',
            planner: '{{ route('planner.store') }}',
            vendorApply: '{{ route('vendor.apply') }}',
            vendorsBase: '{{ url('/vendors') }}',
        },
    };
    window.vendors = @json($vendorScript ?? (object)[]);
</script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/theme.js') }}?v=2"></script>
@stack('scripts')

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>

</body>
</html>
