@extends('layouts.app')

@php
    $img = fn (string $name, int $w = 1920) => asset("images/eventhub/{$name}-{$w}.webp");

    $spotlights = [
        ['proposal-ring', 'Weddings & proposals', 'Venues, décor and every detail of the big day.', 'Venue', 'ri-heart-3-line'],
        ['dj-decks', 'DJs & MCs', 'Sound, lights and someone to keep the room moving.', 'DJ', 'ri-disc-line'],
        ['photographer', 'Photography', 'Photographers and videographers who catch the moment.', 'Photography', 'ri-camera-lens-line'],
        ['car-hire-keys', 'Car hire', 'Wedding cars, chauffeurs and guest transport.', 'Car Hire', 'ri-steering-2-line'],
    ];

    $ribbon = [
        ['Venue', 'Event venues', 'ri-building-4-line'],
        ['Decor', 'Décor & styling', 'ri-flower-line'],
        ['Photography', 'Photography', 'ri-camera-3-line'],
        ['Catering', 'Catering', 'ri-restaurant-2-line'],
        ['DJ', 'MCs & DJs', 'ri-mic-2-line'],
        ['Beauty', 'Hair & make-up', 'ri-brush-3-line'],
        ['Car Hire', 'Car hire', 'ri-roadster-line'],
        ['Fashion & Outfits', 'Fashion & outfits', 'ri-shirt-line'],
    ];

    $verifiedCount = $vendors->where('verified', true)->count();
    $cityCount = $vendors->pluck('location')->filter()->unique()->count();
    $categoryCount = $vendors->pluck('category')->filter()->unique()->count();
@endphp

@section('content')

{{-- ========================= HEADER ========================= --}}
<header class="site-header" id="siteHeader">
    <div class="site-header-inner">
        <a href="#top" class="brand" aria-label="EventHub Zambia home">
            <span class="brand-mark">EH</span>
            <span class="brand-name">EventHub <em>Zambia</em></span>
        </a>

        <nav class="main-nav" id="mainNav" aria-label="Main">
            <span class="nav-indicator" aria-hidden="true"></span>
            <a href="#vendors">Vendors</a>
            <a href="#events">Categories</a>
            <a href="#how-it-works">How it works</a>
            <a href="#locations">Locations</a>
        </nav>

        <div class="header-actions">
            @auth
                <button class="login-btn" onclick="openDashboard()">Dashboard</button>
                <button class="signup-btn" onclick="logoutUser()">Logout</button>
            @else
                <button class="login-btn" onclick="openAuthModal('login')">Log in</button>
                <button class="signup-btn" onclick="openAuthModal('register')">Sign up</button>
            @endauth
        </div>

        <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

{{-- Full-screen menu for phones --}}
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <nav class="mobile-menu-links" aria-label="Mobile">
        <a href="#vendors"><i class="ri-store-3-line"></i> Find vendors</a>
        <a href="#events"><i class="ri-apps-2-line"></i> Categories</a>
        <a href="#how-it-works"><i class="ri-route-line"></i> How it works</a>
        <a href="#locations"><i class="ri-map-pin-2-line"></i> Locations</a>
        <a href="#" data-action="plan"><i class="ri-calendar-event-line"></i> Plan my event</a>
        <a href="#" data-action="vendor"><i class="ri-briefcase-4-line"></i> List my business</a>
    </nav>
    <div class="mobile-menu-auth">
        @auth
            <button class="m-btn m-btn-gold" onclick="openDashboard()">Dashboard</button>
            <button class="m-btn" onclick="logoutUser()">Logout</button>
        @else
            <button class="m-btn" onclick="openAuthModal('login')">Log in</button>
            <button class="m-btn m-btn-gold" onclick="openAuthModal('register')">Create account</button>
        @endauth
    </div>
</div>

{{-- ========================= HERO ========================= --}}
<section class="hero-x" id="top">
    <div class="hero-x-pattern" aria-hidden="true"></div>

    <div class="hero-x-inner">
        <div class="hero-x-copy">
            <p class="hero-x-kicker"><i class="ri-sparkling-2-fill"></i> Zambia's event marketplace</p>
            <h1>Every great celebration starts with the right people.</h1>
            <p class="hero-x-lead">
                Find venues, décor, photographers, caterers, DJs, beauty artists and car hire across Zambia.
                Compare packages, then talk to vendors directly on WhatsApp.
            </p>

            <div class="hero-search">
                <i class="ri-search-line" aria-hidden="true"></i>
                <input type="text" id="heroSearch" placeholder="Try “wedding venue in Lusaka”" aria-label="Search vendors">
                <button type="button" onclick="heroSearch()">Search</button>
            </div>

            <div class="hero-x-actions">
                <button type="button" class="btn btn-gold" onclick="openModal('plannerModal')">
                    <i class="ri-calendar-event-line"></i> Plan my event
                </button>
                <button type="button" class="btn btn-glass" onclick="aiVendorSearch()">
                    <i class="ri-sparkling-2-line"></i> Ask EventHub
                </button>
                <button type="button" class="btn btn-line" onclick="openModal('vendorModal')">
                    <i class="ri-store-2-line"></i> List my business
                </button>
            </div>

            <div class="hero-x-popular">
                <span>Popular:</span>
                <a href="#vendors" onclick=\"resetFilters(); quickCategory('Venue')\">Wedding venues</a>
                <a href="#vendors" onclick=\"resetFilters(); quickCategory('Photography')\">Photographers</a>
                <a href="#vendors" onclick=\"resetFilters(); quickCategory('Catering')\">Catering</a>
                <a href="#vendors" onclick=\"resetFilters(); quickCategory('DJ')\">DJs</a>
            </div>
        </div>

        <div class="hero-x-gallery" aria-hidden="true">
            <figure class="arch arch-tall"><img src="{{ $img('proposal-ring') }}" alt=""></figure>
            <figure class="arch arch-mid"><img src="{{ $img('dj-decks', 960) }}" alt=""></figure>
            <figure class="arch arch-small"><img src="{{ $img('photographer', 960) }}" alt=""></figure>

            <span class="float-badge fb-1"><i class="ri-heart-3-fill"></i> Weddings</span>
            <span class="float-badge fb-2"><i class="ri-music-2-fill"></i> DJs &amp; MCs</span>
            <span class="float-badge fb-3"><i class="ri-camera-fill"></i> Photography</span>
            <span class="float-icon fi-1"><i class="ri-cake-3-line"></i></span>
            <span class="float-icon fi-2"><i class="ri-restaurant-2-line"></i></span>
            <span class="float-icon fi-3"><i class="ri-roadster-line"></i></span>
        </div>
    </div>

    <div class="hero-x-stats">
        <div><strong data-count="{{ $vendors->count() }}">{{ $vendors->count() }}</strong><span>vendors listed</span></div>
        <div><strong data-count="{{ $verifiedCount }}">{{ $verifiedCount }}</strong><span>verified by our team</span></div>
        <div><strong data-count="{{ $categoryCount }}">{{ $categoryCount }}</strong><span>service categories</span></div>
        <div><strong data-count="{{ $cityCount }}">{{ $cityCount }}</strong><span>cities and growing</span></div>
    </div>
</section>

{{-- ========================= CATEGORY RIBBON ========================= --}}
<section class="ribbon" aria-label="Browse by category">
    <div class="ribbon-track">
        @foreach (array_merge($ribbon, $ribbon) as $i => [$value, $label, $icon])
            <a href="#vendors" class="ribbon-item" onclick="resetFilters(); quickCategory('{{ $value }}')" @if ($i >= count($ribbon)) aria-hidden="true" tabindex="-1" @endif>
                <i class="{{ $icon }}"></i> {{ $label }}
            </a>
        @endforeach
    </div>
</section>

{{-- ========================= VENDORS ========================= --}}
<section class="section marketplace" id="vendors">
    <div class="container">
        <div class="section-head">
            <h2>Find your event team</h2>
            <p>Every vendor here has a profile with packages, prices and reviews. Filter by what you need and where your event is.</p>
        </div>

        <div class="marketplace-toolbar">
            <div class="toolbar-top">
                <div class="search-control">
                    <i class="ri-search-line"></i>
                    <input type="text" id="vendorSearch" placeholder="Search by name, service or city" oninput="filterVendors()">
                </div>

                <select id="categoryFilter" class="filter-control" onchange="filterVendors()" aria-label="Category">
                    <option value="">All categories</option>
                    <option value="Venue">Venues</option>
                    <option value="Decor">Decor</option>
                    <option value="Beauty">Beauty</option>
                    <option value="Car Hire">Car Hire</option>
                    <option value="Catering">Catering</option>
                    <option value="Photography">Photography</option>
                    <option value="DJ">DJ & Entertainment</option>
                    <option value="Fashion & Outfits">Fashion & Outfits</option>
                </select>

                <select id="locationFilter" class="filter-control" onchange="filterVendors()" aria-label="City">
                    <option value="">All cities</option>
                    <option value="Lusaka">Lusaka</option>
                    <option value="Ndola">Ndola</option>
                    <option value="Kitwe">Kitwe</option>
                    <option value="Livingstone">Livingstone</option>
                </select>

                <select id="sortFilter" class="filter-control" onchange="sortVendors()" aria-label="Sort">
                    <option value="featured">Featured first</option>
                    <option value="rating">Highest rated</option>
                    <option value="priceLow">Price: low to high</option>
                    <option value="priceHigh">Price: high to low</option>
                </select>
            </div>

            {{-- Pill labels must match quickCategory(): "All" or the category value itself. --}}
            <div class="quick-categories">
                <button class="category-pill active" onclick="quickCategory('')">All</button>
                <button class="category-pill" onclick="quickCategory('Venue')">Venue</button>
                <button class="category-pill" onclick="quickCategory('Decor')">Decor</button>
                <button class="category-pill" onclick="quickCategory('Photography')">Photography</button>
                <button class="category-pill" onclick="quickCategory('Catering')">Catering</button>
                <button class="category-pill" onclick="quickCategory('DJ')">DJ</button>
                <button class="category-pill" onclick="quickCategory('Beauty')">Beauty</button>
                <button class="category-pill" onclick="quickCategory('Car Hire')">Car Hire</button>
                <button class="category-pill" onclick="quickCategory('Fashion & Outfits')">Fashion & Outfits</button>
            </div>
        </div>

        <div class="marketplace-meta">
            <div class="vendor-count">Showing <strong id="vendorCount">{{ $vendors->count() }}</strong> vendors</div>
            <button class="clear-filters-btn" type="button" onclick="resetFilters()">
                <i class="ri-refresh-line"></i> Reset filters
            </button>
        </div>

        <div class="vendor-grid" id="vendorGrid">
            @foreach ($vendors as $vendor)
                <article class="vendor-card"
                    data-name="{{ $vendor->name }}"
                    data-category="{{ $vendor->category }}"
                    data-location="{{ $vendor->location }}"
                    data-rating="{{ $vendor->rating ?? 0 }}"
                    data-price="{{ $vendor->price_from ?? 0 }}"
                    data-featured="{{ $vendor->featured ? '1' : '0' }}"
                    data-vendor="{{ $vendor->slug }}">

                    <div class="vendor-image">
                        <img src="{{ $vendor->cover }}" alt="{{ $vendor->name }}" loading="lazy">
                        @if ($vendor->featured)
                            <span class="featured-badge"><i class="ri-vip-crown-2-fill"></i> Featured</span>
                        @endif
                        @if ($vendor->verified)
                            <span class="verified-badge"><i class="ri-verified-badge-fill"></i> Verified</span>
                        @endif
                        <button class="favorite-btn" type="button" onclick="toggleFavorite(this)" aria-label="Save {{ $vendor->name }}">
                            <i class="ri-heart-line"></i>
                        </button>
                    </div>

                    <div class="vendor-body">
                        <div class="vendor-category">{{ $vendor->category }}</div>
                        <h3 class="vendor-name">{{ $vendor->name }}</h3>
                        <div class="vendor-meta">
                            <span><i class="ri-map-pin-line"></i> {{ $vendor->location }}</span>
                            <span class="vendor-rating"><i class="ri-star-fill"></i> {{ $vendor->ratingLabel() }}</span>
                        </div>
                        <div class="vendor-price">
                            @if ($vendor->price_from)
                                From <strong>K{{ number_format($vendor->price_from) }}</strong>
                            @else
                                <strong>Ask for a quote</strong>
                            @endif
                        </div>
                        <div class="vendor-actions">
                            <button class="profile-btn" onclick="openVendorProfile('{{ $vendor->slug }}')">View profile</button>
                            @if ($vendor->phone)
                                <button class="whatsapp-btn" onclick="contactWhatsApp('{{ $vendor->name }}')">
                                    <i class="ri-whatsapp-line"></i> WhatsApp
                                </button>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="no-results" id="noResults">
            <i class="ri-search-eye-line"></i>
            <strong>No vendors match those filters.</strong>
            <p>Reset the filters, or search Facebook and Google below to find more businesses.</p>
        </div>

        {{-- FREE VENDOR DISCOVERY --}}
        <div class="free-discovery-card">
            <div class="free-discovery-copy">
                <h3>Can't find the vendor you need?</h3>
                <p>Search Facebook, Google or Google Maps for event businesses near you, then invite them to claim a free EventHub profile.</p>
            </div>
            <div class="free-discovery-controls">
                <div class="free-search-row">
                    <input id="facebookVendorQuery" type="text" placeholder="e.g. wedding decorators" autocomplete="off" aria-label="What are you looking for">
                    <select id="facebookVendorLocation" aria-label="City">
                        @foreach (['Lusaka','Kitwe','Ndola','Livingstone','Kabwe','Chipata','Solwezi','Mansa','Kasama','Chingola'] as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="free-discovery-actions">
                    <button type="button" class="free-facebook-btn" onclick="searchFacebookVendors()"><i class="ri-facebook-fill"></i> Facebook</button>
                    <button type="button" class="free-google-btn" onclick="searchGoogleVendors()"><i class="ri-google-fill"></i> Google</button>
                    <button type="button" class="free-maps-btn" onclick="searchGoogleMapsVendors()"><i class="ri-map-pin-2-fill"></i> Google Maps</button>
                    <button type="button" class="free-register-btn" onclick="openFreeVendorRegistration()"><i class="ri-store-2-line"></i> Add / claim vendor</button>
                </div>
                <div class="free-discovery-note">Results open in a new tab. EventHub doesn't copy Facebook or Google results.</div>
            </div>
        </div>
    </div>
</section>

{{-- ========================= CATEGORY SPOTLIGHT ========================= --}}
<section class="section spotlight" id="events">
    <div class="container">
        <div class="section-head section-head-split">
            <h2>Start with the moment you're creating</h2>
            <p>Pick what you need first. We'll show you the vendors who do it best.</p>
        </div>

        <div class="spotlight-grid">
            @foreach ($spotlights as $i => [$photo, $title, $copy, $category, $icon])
                <a href="#vendors" class="spot-card {{ $i === 0 ? 'spot-card-lg' : '' }}" onclick="resetFilters(); quickCategory('{{ $category }}')">
                    <img src="{{ $img($photo, $i === 0 ? 1920 : 960) }}" alt="{{ $title }}" loading="lazy">
                    <span class="spot-icon"><i class="{{ $icon }}"></i></span>
                    <span class="spot-copy">
                        <strong>{{ $title }}</strong>
                        <span>{{ $copy }}</span>
                        <span class="spot-cta">See vendors <i class="ri-arrow-right-up-line"></i></span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ========================= HOW IT WORKS ========================= --}}
<section class="section how-x" id="how-it-works">
    <div class="container how-x-inner">
        <div class="how-x-photo">
            <figure class="arch arch-feature"><img src="{{ $img('car-hire-keys', 960) }}" alt="Car keys handed over to a customer" loading="lazy"></figure>
            <span class="float-badge fb-how"><i class="ri-whatsapp-fill"></i> Vendors reply on WhatsApp</span>
        </div>

        <div class="how-x-steps">
            <h2>From first idea to the big day</h2>
            <ol class="timeline" id="howTimeline">
                <span class="timeline-progress" aria-hidden="true"></span>
                <li>
                    <span class="timeline-dot"><i class="ri-search-eye-line"></i></span>
                    <h3>Find what you need</h3>
                    <p>Search by service, city, rating and price. Every listing shows packages and starting prices.</p>
                </li>
                <li>
                    <span class="timeline-dot"><i class="ri-chat-smile-3-line"></i></span>
                    <h3>Talk to vendors directly</h3>
                    <p>Request a quote or message on WhatsApp. No middleman, no booking fees.</p>
                </li>
                <li>
                    <span class="timeline-dot"><i class="ri-goblet-line"></i></span>
                    <h3>Celebrate</h3>
                    <p>Confirm the details with your vendors and enjoy the day you planned.</p>
                </li>
            </ol>
            <button type="button" class="btn btn-gold" onclick="openModal('plannerModal')"><i class="ri-calendar-event-line"></i> Start planning</button>
        </div>
    </div>
</section>

{{-- ========================= VENDOR CTA ========================= --}}
<section class="vendor-cta">
    <div class="container vendor-cta-inner">
        <div>
            <h2>Run an event business? Get found by people planning their next celebration.</h2>
            <ul>
                <li><i class="ri-checkbox-circle-fill"></i> A free profile with photos, packages and prices</li>
                <li><i class="ri-checkbox-circle-fill"></i> Quote requests and WhatsApp enquiries sent straight to you</li>
                <li><i class="ri-checkbox-circle-fill"></i> A verified badge once our team checks your business</li>
            </ul>
        </div>
        <div class="vendor-cta-actions">
            <button type="button" class="btn btn-gold btn-lg" onclick="openModal('vendorModal')"><i class="ri-store-2-line"></i> List my business free</button>
            <button type="button" class="btn btn-line" onclick="openFreeVendorRegistration()">Claim an existing listing</button>
        </div>
    </div>
</section>

{{-- ========================= LOCATIONS ========================= --}}
<section class="section locations-x" id="locations">
    <div class="container">
        <div class="section-head section-head-split">
            <h2>Vendors across Zambia</h2>
            <p>Starting in Lusaka, the Copperbelt and Livingstone, with more towns joining every month.</p>
        </div>

        <div class="city-chips">
            @foreach (['Lusaka', 'Ndola', 'Kitwe', 'Livingstone'] as $city)
                <button type="button" class="city-chip" data-city="{{ $city }}"><i class="ri-map-pin-2-fill"></i> {{ $city }}</button>
            @endforeach
        </div>

        <div class="map-box">
            <iframe class="google-map" title="EventHub Zambia locations"
                src="https://www.google.com/maps?q=Zambia&output=embed"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
    </div>
</section>

{{-- ========================= CLOSING CTA ========================= --}}
<section class="closing-cta">
    <div class="closing-cta-photo" style="background-image:url('{{ $img('proposal-ring') }}')" aria-hidden="true"></div>
    <div class="container closing-cta-inner">
        <h2>Your event deserves the right people.</h2>
        <p>Tell us what you're planning and we'll help you find the vendors to make it happen.</p>
        <div class="closing-cta-actions">
            <button type="button" class="btn btn-gold btn-lg" onclick="openModal('plannerModal')"><i class="ri-calendar-event-line"></i> Plan my event</button>
            <a href="#vendors" class="btn btn-glass btn-lg"><i class="ri-search-line"></i> Browse vendors</a>
        </div>
    </div>
</section>

{{-- ========================= FOOTER ========================= --}}
<footer class="site-footer">
    <div class="footer-ribbon" aria-hidden="true">
        <div class="footer-ribbon-track">
            @for ($r = 0; $r < 2; $r++)
                @foreach (['Weddings', 'Kitchen parties', 'Birthdays', 'Corporate events', 'Graduations', 'Traditional ceremonies', 'Baby showers', 'Anniversaries'] as $type)
                    <span>{{ $type }}</span><i class="ri-sparkling-2-fill"></i>
                @endforeach
            @endfor
        </div>
    </div>

    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="#top" class="brand"><span class="brand-mark">EH</span><span class="brand-name">EventHub <em>Zambia</em></span></a>
                <p>Zambia's event marketplace. Find trusted venues, décor, photographers, caterers, DJs and more, compare packages, and connect with vendors directly.</p>
                <div class="footer-cta-row">
                    <button type="button" class="btn btn-gold" onclick="openModal('plannerModal')"><i class="ri-calendar-event-line"></i> Plan my event</button>
                    <button type="button" class="btn btn-line" onclick="openModal('vendorModal')"><i class="ri-store-2-line"></i> List my business</button>
                </div>
                <div class="trust-badges">
                    <span><i class="ri-verified-badge-fill"></i> Verified vendors</span>
                    <span><i class="ri-money-dollar-circle-line"></i> Free quotes</span>
                    <span><i class="ri-whatsapp-line"></i> WhatsApp ready</span>
                    <span><i class="ri-android-line"></i> Android app soon</span>
                </div>
            </div>

            <div class="footer-col">
                <h4>Find vendors</h4>
                @foreach ($ribbon as [$value, $label, $icon])
                    <a href="#vendors" data-footer-category="{{ $value }}"><i class="{{ $icon }}"></i> {{ $label }}</a>
                @endforeach
            </div>

            <div class="footer-col">
                <h4>Plan an event</h4>
                @foreach (['Wedding', 'Birthday', 'Corporate Event', 'Graduation', 'Traditional Ceremony', 'Other'] as $type)
                    <a href="#" data-plan-type="{{ $type }}"><i class="ri-calendar-check-line"></i> {{ $type === 'Other' ? 'Something else' : $type }}</a>
                @endforeach
            </div>

            <div class="footer-col">
                <h4>Cities</h4>
                @foreach (['Lusaka', 'Ndola', 'Kitwe', 'Livingstone'] as $city)
                    <a href="#vendors" data-footer-city="{{ $city }}"><i class="ri-map-pin-line"></i> {{ $city }}</a>
                @endforeach
                <a href="#locations"><i class="ri-map-2-line"></i> All of Zambia</a>
            </div>

            <div class="footer-col">
                <h4>For vendors</h4>
                <a href="#" data-action="vendor"><i class="ri-store-2-line"></i> Become a vendor</a>
                <a href="#" data-action="claim"><i class="ri-hand-heart-line"></i> Claim your listing</a>
                <a href="{{ route('dashboard') }}"><i class="ri-dashboard-line"></i> Vendor dashboard</a>
                <h4 class="footer-sub">EventHub</h4>
                <a href="#how-it-works"><i class="ri-route-line"></i> How it works</a>
                <a href="/privacy.html"><i class="ri-shield-user-line"></i> Privacy policy</a>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© {{ date('Y') }} EventHub Zambia. All rights reserved.</span>
            <span class="footer-credit">Designed &amp; developed by <strong>BrandBay Innovation Studios</strong></span>
            <a href="#top" class="footer-top-link"><i class="ri-arrow-up-line"></i> Back to top</a>
        </div>
    </div>
</footer>

{{-- ========================= FLOATING QUICK ACTIONS ========================= --}}
<div class="fab" id="fab">
    <div class="fab-menu" id="fabMenu">
        <button type="button" data-action="plan"><span>Plan my event</span><i class="ri-calendar-event-line"></i></button>
        <a href="#vendors" data-fab-close><span>Find vendors</span><i class="ri-search-line"></i></a>
        <button type="button" data-action="vendor"><span>List my business</span><i class="ri-store-2-line"></i></button>
        <a href="#top" data-fab-close><span>Back to top</span><i class="ri-arrow-up-line"></i></a>
    </div>
    <button type="button" class="fab-main" id="fabMain" aria-label="Quick actions" aria-expanded="false" aria-controls="fabMenu">
        <i class="ri-add-line"></i>
    </button>
</div>

@endsection
