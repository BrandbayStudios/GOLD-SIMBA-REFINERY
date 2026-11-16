<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#101D35">
<title>{{ isset($title) ? $title.' · HANDY NOW' : 'HANDY NOW – Emergency Services. One Call Away.' }}</title>
<meta name="description" content="{{ $description ?? 'HANDY NOW connects you with verified towing, electrical, plumbing, gas, mechanical and handyman professionals across Zambia – fast.' }}">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23FF8A24'/%3E%3Cpath d='M19.6 8.4a1.3 1.3 0 0 0 0 1.9l2.1 2.1a1.3 1.3 0 0 0 1.9 0l5-5a8 8 0 0 1-10.6 10.6l-9.2 9.2a2.8 2.8 0 0 1-4-4l9.2-9.2A8 8 0 0 1 24.6 3.4z' fill='none' stroke='white' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round' transform='scale(.75) translate(5 5)'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
