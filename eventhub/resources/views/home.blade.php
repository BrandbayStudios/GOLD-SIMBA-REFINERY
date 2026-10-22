@extends('layouts.app')

@section('content')

{{-- ========================= HEADER ========================= --}}
<header class="header">
    <div class="header-inner">
        <a href="#" class="logo">
            <span class="logo-mark">EH</span>
            <span>EventHub Zambia</span>
        </a>

        <nav class="nav">
            <a href="#events">Events</a>
            <a href="#vendors">Vendors</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#locations">Locations</a>
        </nav>

        <div class="header-actions">
            @auth
                <button class="login-btn" onclick="openDashboard()">Dashboard</button>
                <button class="signup-btn" onclick="logoutUser()">Logout</button>
            @else
                <button class="login-btn" onclick="openAuthModal('login')">Login</button>
                <button class="signup-btn" onclick="openAuthModal('register')">Sign Up</button>
            @endauth
        </div>

        <button class="mobile-toggle"><i class="ri-menu-3-line"></i></button>
    </div>
</header>

{{-- ========================= HERO ========================= --}}
<section class="hero">
    @foreach ([
        'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=2000&q=85',
        'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=2000&q=85',
        'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=2000&q=85',
        'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=2000&q=85',
        'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=2000&q=85',
    ] as $i => $slide)
        <div class="hero-slide {{ $i === 0 ? 'active' : '' }}" style="background-image:url('{{ $slide }}')"></div>
    @endforeach

    <div class="hero-overlay"></div>

    <div class="hero-content">
        <div class="hero-copy">
            <div class="eyebrow">Zambia's Event Marketplace</div>
            <h1>Make Every <span>Moment</span> Remarkable.</h1>
            <p class="hero-description">
                Discover trusted venues, decorators, photographers, caterers,
                DJs, beauty professionals, transport providers and more —
                all in one place.
            </p>

            <div class="hero-search-row">
                <div class="search-box">
                    <i class="ri-search-line"></i>
                    <input type="text" id="heroSearch" placeholder="What are you looking for?">
                    <button onclick="heroSearch()">Search</button>
                </div>

                <button class="ai-search-btn" type="button" onclick="aiVendorSearch()">
                    <i class="ri-sparkling-2-line"></i> Ask EventHub
                </button>

                <button class="plan-event-btn" onclick="openModal('plannerModal')">
                    <i class="ri-calendar-event-line"></i> Plan My Event
                </button>

                <button class="vendor-btn" onclick="openModal('vendorModal')">
                    <i class="ri-store-2-line"></i> Become a Vendor
                </button>
            </div>

            <div class="popular">
                Popular:
                <a href="#vendors" onclick="quickCategory('Venue')">Wedding Venues</a>
                <a href="#vendors" onclick="quickCategory('Photography')">Photographers</a>
                <a href="#vendors" onclick="quickCategory('Catering')">Catering</a>
                <a href="#vendors" onclick="quickCategory('DJ')">DJs</a>
            </div>
        </div>
    </div>

    <div class="hero-controls">
        <button class="slider-arrow" onclick="changeSlide(-1)"><i class="ri-arrow-left-line"></i></button>
        <div class="slider-dots">
            @for ($i = 0; $i < 5; $i++)
                <button class="slider-dot {{ $i === 0 ? 'active' : '' }}" onclick="goToSlide({{ $i }})"></button>
            @endfor
        </div>
        <button class="slider-arrow" onclick="changeSlide(1)"><i class="ri-arrow-right-line"></i></button>
    </div>
</section>

{{-- ========================= SERVICES ========================= --}}
<section class="services">
    <div class="services-inner">
        <a class="service-item" href="#vendors" onclick="quickCategory('Venue')"><i class="ri-building-4-line"></i><span>Event Venues</span></a>
        <a class="service-item" href="#vendors" onclick="quickCategory('DJ')"><i class="ri-mic-line"></i><span>MCs & DJs</span></a>
        <a class="service-item" href="#vendors" onclick="quickCategory('Photography')"><i class="ri-camera-3-line"></i><span>Photography</span></a>
        <a class="service-item" href="#vendors" onclick="quickCategory('Car Hire')"><i class="ri-roadster-line"></i><span>Car Hire</span></a>
        <a class="service-item" href="#vendors" onclick="quickCategory('Beauty')"><i class="ri-sparkling-2-line"></i><span>Beauty</span></a>
        <a class="service-item" href="#vendors" onclick="quickCategory('Catering')"><i class="ri-restaurant-line"></i><span>Catering</span></a>
        <a class="service-item" href="#vendors" onclick="quickCategory('Fashion & Outfits')"><i class="ri-shirt-line"></i><span>Fashion & Outfits</span></a>
    </div>
</section>

{{-- ========================= VENDORS ========================= --}}
<section class="section marketplace" id="vendors">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Discover</div>
            <h2 class="section-title">Find your event team.<br>All in one place.</h2>
            <p class="section-description">
                Discover venues and trusted event professionals across Zambia. Compare services, packages, ratings and pricing before you connect.
            </p>
        </div>

        <div class="marketplace-toolbar">
            <div class="toolbar-top">
                <div class="search-control">
                    <i class="ri-search-line"></i>
                    <input type="text" id="vendorSearch" placeholder="Search vendors..." oninput="filterVendors()">
                </div>

                <select id="categoryFilter" class="filter-control" onchange="filterVendors()">
                    <option value="">All Categories</option>
                    <option value="Venue">Venues</option>
                    <option value="Decor">Decor</option>
                    <option value="Beauty">Beauty</option>
                    <option value="Car Hire">Car Hire</option>
                    <option value="Catering">Catering</option>
                    <option value="Photography">Photography</option>
                    <option value="DJ">DJ & Entertainment</option>
                    <option value="Fashion & Outfits">Fashion & Outfits</option>
                </select>

                <select id="locationFilter" class="filter-control" onchange="filterVendors()">
                    <option value="">All Locations</option>
                    <option value="Lusaka">Lusaka</option>
                    <option value="Ndola">Ndola</option>
                    <option value="Kitwe">Kitwe</option>
                    <option value="Livingstone">Livingstone</option>
                </select>

                <select id="sortFilter" class="filter-control" onchange="sortVendors()">
                    <option value="featured">Featured</option>
                    <option value="rating">Highest Rated</option>
                    <option value="priceLow">Price: Low to High</option>
                    <option value="priceHigh">Price: High to Low</option>
                </select>
            </div>

            <div class="quick-categories">
                <button class="category-pill active" onclick="quickCategory('')">All</button>
                <button class="category-pill" onclick="quickCategory('Venue')">Venues</button>
                <button class="category-pill" onclick="quickCategory('Decor')">Decor</button>
                <button class="category-pill" onclick="quickCategory('Photography')">Photography</button>
                <button class="category-pill" onclick="quickCategory('Catering')">Catering</button>
                <button class="category-pill" onclick="quickCategory('DJ')">Entertainment</button>
                <button class="category-pill" onclick="quickCategory('Beauty')">Beauty</button>
                <button class="category-pill" onclick="quickCategory('Car Hire')">Car Hire</button>
                <button class="category-pill" onclick="quickCategory('Fashion & Outfits')">Fashion & Outfits</button>
            </div>
        </div>

        <div class="marketplace-toolbar-foot">
            <div class="marketplace-result-label">
                <span class="result-dot"></span>
                <span>Verified businesses and event professionals</span>
            </div>
            <button class="clear-filters-btn" type="button" onclick="resetFilters()">
                <i class="ri-refresh-line"></i> Reset filters
            </button>
        </div>

        {{-- FREE VENDOR DISCOVERY --}}
        <div class="free-discovery-card">
            <div class="free-discovery-copy">
                <div class="free-discovery-eyebrow">FREE DISCOVERY</div>
                <h3>Can't find the vendor you're looking for?</h3>
                <p>Search Facebook directly for event businesses, then invite the business owner to claim or create their EventHub profile.</p>
            </div>
            <div class="free-discovery-controls">
                <div class="free-search-row">
                    <input id="facebookVendorQuery" type="text" placeholder="e.g. wedding decorators" autocomplete="off">
                    <select id="facebookVendorLocation">
                        @foreach (['Lusaka','Kitwe','Ndola','Livingstone','Kabwe','Chipata','Solwezi','Mansa','Kasama','Chingola'] as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="free-discovery-actions">
                    <button type="button" class="free-facebook-btn" onclick="searchFacebookVendors()">
                        <i class="ri-facebook-fill"></i> Search Facebook
                    </button>
                    <button type="button" class="free-register-btn" onclick="openFreeVendorRegistration()">
                        <i class="ri-store-2-line"></i> Add / Claim Vendor
                    </button>
                </div>
                <div class="free-discovery-note">
                    Facebook opens in a new tab. EventHub does not scrape or import Facebook search results automatically.
                </div>
            </div>
        </div>

        <div class="marketplace-meta">
            <div class="vendor-count">
                Showing <strong id="vendorCount">{{ $vendors->count() }}</strong> vendors
            </div>
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
                            <span class="featured-badge">Featured</span>
                        @endif
                        @if ($vendor->verified)
                            <span class="verified-badge"><i class="ri-verified-badge-fill"></i> Verified</span>
                        @endif
                        <button class="favorite-btn" type="button" onclick="toggleFavorite(this)">
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
                                Starting from <strong>K{{ number_format($vendor->price_from) }}</strong>
                            @else
                                <strong>Contact vendor for pricing</strong>
                            @endif
                        </div>
                        <div class="vendor-actions">
                            <button class="profile-btn" onclick="openVendorProfile('{{ $vendor->slug }}')">View Profile</button>
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
            <strong>No vendors found.</strong>
            <p>Try another category, location or search term.</p>
        </div>

        <div class="load-more-wrap">
            <button class="load-more" onclick="showToast('More vendors will be loaded from the marketplace database.')">
                Load More Vendors
                <i class="ri-arrow-down-line"></i>
            </button>
        </div>
    </div>
</section>

{{-- ========================= EVENT TYPES ========================= --}}
<section class="section event-types" id="events">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Whatever the Occasion</div>
            <h2 class="section-title">Start with the<br>moment you're creating.</h2>
        </div>

        <div class="event-grid">
            @foreach ([
                ['https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=1000&q=85', 'Weddings', 'Venues, décor, photography, beauty and more.'],
                ['https://images.unsplash.com/photo-1530103862676-de8c9debad1d?auto=format&fit=crop&w=1000&q=85', 'Birthdays', 'Make your next celebration unforgettable.'],
                ['https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=1000&q=85', 'Corporate', 'Professional events without the stress.'],
                ['https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1000&q=85', 'Celebrations', 'From graduations to private celebrations.'],
            ] as [$img, $heading, $copy])
                <div class="event-card">
                    <img src="{{ $img }}" alt="{{ $heading }}">
                    <div class="event-card-content">
                        <h3>{{ $heading }}</h3>
                        <p>{{ $copy }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ========================= HOW IT WORKS ========================= --}}
<section class="section how" id="how-it-works">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Simple By Design</div>
            <h2 class="section-title">From idea to<br>unforgettable.</h2>
        </div>

        <div class="steps">
            <div class="step">
                <div class="step-number">01 — DISCOVER</div>
                <i class="ri-search-eye-line"></i>
                <h3>Find what you need.</h3>
                <p>Search venues and event professionals by service, location, rating and price.</p>
            </div>
            <div class="step">
                <div class="step-number">02 — CONNECT</div>
                <i class="ri-chat-3-line"></i>
                <h3>Connect directly.</h3>
                <p>View vendor profiles, compare packages and contact providers directly.</p>
            </div>
            <div class="step">
                <div class="step-number">03 — CELEBRATE</div>
                <i class="ri-gift-line"></i>
                <h3>Make it happen.</h3>
                <p>Book the right people and focus on enjoying the event you've created.</p>
            </div>
        </div>
    </div>
</section>

{{-- ========================= LOCATIONS ========================= --}}
<section class="section map-section" id="locations">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Across Zambia</div>
            <h2 class="section-title">Events happen<br>everywhere.</h2>
            <p class="section-description">
                Discover event professionals in Lusaka, Copperbelt, Livingstone and growing markets across Zambia.
            </p>
        </div>

        <div class="map-box">
            <iframe class="google-map" title="EventHub Zambia locations"
                src="https://www.google.com/maps?q=Mpongwe%2C%20Zambia&output=embed"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>

            <div class="map-overlay-card">
                <span class="map-overlay-eyebrow">EVENTHUB ZAMBIA</span>
                <strong>Find event professionals across Zambia.</strong>
                <span>Explore locations on the live map.</span>
            </div>
        </div>
    </div>
</section>

{{-- ========================= CTA ========================= --}}
<section class="cta">
    <h2>Your event deserves the right people.</h2>
    <p>Whether you're planning a wedding, corporate function, birthday or celebration, EventHub brings everything together.</p>
    <div class="cta-buttons">
        <button class="cta-primary" onclick="openModal('plannerModal')">Plan My Event</button>
        <button class="cta-secondary" onclick="openModal('vendorModal')">Become a Vendor</button>
    </div>
</section>

{{-- ========================= FOOTER ========================= --}}
<footer class="footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="#" class="logo">
                <span class="logo-mark">EH</span>
                <span>EventHub Zambia</span>
            </a>
            <p>Zambia's event marketplace connecting people with trusted event professionals and businesses.</p>
        </div>

        <div>
            <h4>Marketplace</h4>
            <div class="footer-links">
                <a href="#vendors">Find Vendors</a>
                <a href="#events">Events</a>
                <a href="#vendors">Venues</a>
                <a href="#vendors">Services</a>
            </div>
        </div>

        <div>
            <h4>For Vendors</h4>
            <div class="footer-links">
                <a href="#" onclick="openModal('vendorModal');return false;">Become a Vendor</a>
                <a href="#">Vendor Dashboard</a>
                <a href="#">Pricing</a>
                <a href="#">Vendor Resources</a>
            </div>
        </div>

        <div>
            <h4>Company</h4>
            <div class="footer-links">
                <a href="#">About</a>
                <a href="#">Contact</a>
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <span>© {{ date('Y') }} EventHub Zambia. All rights reserved. <span class="footer-credit">Designed & Developed by <strong>BrandBay Innovation Studios</strong></span></span>
        <span>Plan. Connect. Celebrate.</span>
    </div>
</footer>

@endsection
