<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Dashboard') | EventHub Zambia</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>

<div class="dash-bar">
    <div class="dash-bar-inner">
        <a href="{{ route('home') }}" class="dash-logo">
            <span class="dash-logo-mark">EH</span>
            <span>EventHub Zambia</span>
        </a>
        <div class="dash-bar-actions">
            <a href="{{ route('home') }}"><i class="ri-arrow-left-line"></i> Marketplace</a>
            <span class="dash-user">{{ $user->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dash-logout">Logout</button>
            </form>
        </div>
    </div>
</div>

<div class="dash-wrap">
    @yield('content')
</div>

</body>
</html>
