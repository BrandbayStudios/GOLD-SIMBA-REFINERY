<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#101D35">
<title>{{ isset($title) ? $title.' · HANDY NOW' : 'HANDY NOW – Emergency Services. One Call Away.' }}</title>
<meta name="description" content="{{ $description ?? 'HANDY NOW connects you with verified towing, electrical, plumbing, gas, mechanical and handyman professionals across Zambia – fast.' }}">
<link rel="icon" type="image/svg+xml" href="/brand/handynow-icon.svg">
<link rel="apple-touch-icon" href="/brand/handynow-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
