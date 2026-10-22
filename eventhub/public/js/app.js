/* =========================================================
   EventHub Zambia — front-end behaviour
   (Laravel-backed; replaces the original Firebase layer)
========================================================= */

const EH = window.EventHub || { authenticated: false, user: null, csrf: '', routes: {} };

/* ---------- AJAX helper ---------- */
async function postJson(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': EH.csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(data),
    });

    let payload = {};
    try { payload = await response.json(); } catch (_) {}

    if (!response.ok) {
        // Laravel validation errors (422) arrive under `errors`.
        const firstError = payload.errors ? Object.values(payload.errors)[0]?.[0] : null;
        const message = firstError || payload.message || 'Something went wrong. Please try again.';
        throw new Error(message);
    }
    return payload;
}

/* =========================================================
   TOAST
========================================================= */
let toastTimer;
function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

/* =========================================================
   MODALS
========================================================= */
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
        closeLightbox();
        document.body.style.overflow = '';
    }
});

/* =========================================================
   AUTHENTICATION
========================================================= */
function requireAuth(afterLoginHint) {
    if (EH.authenticated) return true;
    openAuthModal('register');
    if (afterLoginHint) showToast(afterLoginHint);
    return false;
}

function setAuthMode(mode) {
    const register = mode === 'register';
    document.getElementById('authTitle').textContent = register ? 'Create your EventHub account' : 'Welcome back';
    document.getElementById('authSubmit').textContent = register ? 'Create Account' : 'Login';
    document.getElementById('authNameGroup').style.display = register ? 'grid' : 'none';
    document.getElementById('authPhoneGroup').style.display = register ? 'grid' : 'none';
    document.getElementById('authConfirmGroup').style.display = register ? 'grid' : 'none';
    document.getElementById('authSwitch').innerHTML = register
        ? `Already have an account? <button type="button" onclick="setAuthMode('login')">Login</button>`
        : `New to EventHub? <button type="button" onclick="setAuthMode('register')">Create an account</button>`;
    window.eventHubAuthMode = mode;
}

function openAuthModal(mode = 'login') {
    setAuthMode(mode);
    openModal('authModal');
}

async function submitAuthForm(e) {
    e.preventDefault();
    if (window.eventHubAuthMode === 'register') {
        await registerAccount();
    } else {
        await loginAccount();
    }
}

async function registerAccount() {
    const name = document.getElementById('authName').value.trim();
    const phone = document.getElementById('authPhone').value.trim();
    const email = document.getElementById('authEmail').value.trim();
    const password = document.getElementById('authPassword').value;
    const confirm = document.getElementById('authPasswordConfirm').value;

    if (!name || !email || !password) { showToast('Name, email and password are required.'); return; }
    if (password !== confirm) { showToast('Passwords do not match.'); return; }

    try {
        const res = await postJson(EH.routes.register, {
            name, phone, email,
            password,
            password_confirmation: confirm,
        });
        applyLogin(res);
        closeModal('authModal');
        document.getElementById('authForm').reset();
        showToast(res.message || 'Account created.');
        if (window.eventHubPendingClaim) setTimeout(openClaimModal, 150);
    } catch (error) {
        showToast(error.message);
    }
}

async function loginAccount() {
    const email = document.getElementById('authEmail').value.trim();
    const password = document.getElementById('authPassword').value;

    if (!email || !password) { showToast('Enter your email and password.'); return; }

    try {
        const res = await postJson(EH.routes.login, { email, password });
        applyLogin(res);
        closeModal('authModal');
        document.getElementById('authForm').reset();
        showToast(res.message || 'Signed in.');
        if (window.eventHubPendingClaim) setTimeout(openClaimModal, 150);
    } catch (error) {
        showToast(error.message);
    }
}

function applyLogin(res) {
    EH.authenticated = true;
    EH.user = res.user || EH.user;
    if (res.csrf) EH.csrf = res.csrf; // session regeneration rotates the token
    updateAuthUI();
}

function updateAuthUI() {
    const loginBtn = document.querySelector('.login-btn');
    const signupBtn = document.querySelector('.signup-btn');
    if (!loginBtn || !signupBtn) return;

    if (EH.authenticated) {
        loginBtn.textContent = 'Dashboard';
        loginBtn.onclick = openDashboard;
        signupBtn.textContent = 'Logout';
        signupBtn.onclick = logoutUser;
    } else {
        loginBtn.textContent = 'Login';
        loginBtn.onclick = () => openAuthModal('login');
        signupBtn.textContent = 'Sign Up';
        signupBtn.onclick = () => openAuthModal('register');
    }
}

function openDashboard() {
    window.location.href = EH.routes.dashboard;
}

async function logoutUser() {
    try {
        const res = await postJson(EH.routes.logout, {});
        EH.authenticated = false;
        EH.user = null;
        if (res.csrf) EH.csrf = res.csrf;
        updateAuthUI();
        showToast('You have been logged out.');
    } catch (error) {
        showToast('Unable to log out right now.');
    }
}

/* =========================================================
   HERO SLIDER
========================================================= */
let currentSlide = 0;
const slides = document.querySelectorAll('.hero-slide');
const dots = document.querySelectorAll('.slider-dot');

function showSlide(index) {
    if (index >= slides.length) currentSlide = 0;
    if (index < 0) currentSlide = slides.length - 1;
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    slides[currentSlide].classList.add('active');
    if (dots[currentSlide]) dots[currentSlide].classList.add('active');
}

function changeSlide(direction) {
    currentSlide += direction;
    if (currentSlide >= slides.length) currentSlide = 0;
    if (currentSlide < 0) currentSlide = slides.length - 1;
    showSlide(currentSlide);
}

function goToSlide(index) {
    currentSlide = index;
    showSlide(currentSlide);
}

if (slides.length) setInterval(() => changeSlide(1), 6000);

/* =========================================================
   HERO SEARCH
========================================================= */
function heroSearch() {
    const value = document.getElementById('heroSearch').value.trim();
    if (!value) {
        document.getElementById('vendorSearch').focus();
        document.getElementById('vendors').scrollIntoView({ behavior: 'smooth' });
        return;
    }
    document.getElementById('vendorSearch').value = value;
    document.getElementById('categoryFilter').value = '';
    document.getElementById('locationFilter').value = '';
    filterVendors();
    document.getElementById('vendors').scrollIntoView({ behavior: 'smooth' });
}

/* Natural-language search — resolved client-side against the loaded catalogue. */
function aiVendorSearch() {
    const input = document.getElementById('heroSearch');
    const query = input ? input.value.trim() : '';
    if (!query) {
        showToast('Tell EventHub what you are looking for first.');
        if (input) input.focus();
        return;
    }

    const q = query.toLowerCase();
    const categoryMap = {
        venue: 'Venue', hall: 'Venue', wedding: 'Venue',
        decor: 'Decor', decoration: 'Decor', floral: 'Decor',
        beauty: 'Beauty', makeup: 'Beauty', hair: 'Beauty',
        car: 'Car Hire', transport: 'Car Hire', ride: 'Car Hire',
        catering: 'Catering', food: 'Catering', buffet: 'Catering',
        photo: 'Photography', photography: 'Photography', video: 'Photography',
        dj: 'DJ', music: 'DJ', sound: 'DJ', entertainment: 'DJ', mc: 'DJ',
        fashion: 'Fashion & Outfits', outfit: 'Fashion & Outfits', dress: 'Fashion & Outfits', suit: 'Fashion & Outfits',
    };
    const locations = ['Lusaka', 'Ndola', 'Kitwe', 'Livingstone'];

    let category = '';
    for (const key in categoryMap) {
        if (q.includes(key)) { category = categoryMap[key]; break; }
    }
    const location = locations.find(l => q.includes(l.toLowerCase())) || '';

    document.getElementById('vendorSearch').value = category || location ? '' : query;
    document.getElementById('categoryFilter').value = category;
    document.getElementById('locationFilter').value = location;
    filterVendors();

    const visible = Array.from(document.querySelectorAll('.vendor-card'))
        .filter(c => c.style.display !== 'none').length;

    document.getElementById('vendors').scrollIntoView({ behavior: 'smooth', block: 'start' });
    showToast(visible ? `${visible} EventHub match${visible === 1 ? '' : 'es'} found.` : 'No matching vendors found. Try another search.');
}

/* =========================================================
   MARKETPLACE FILTERING
========================================================= */
function filterVendors() {
    const search = document.getElementById('vendorSearch').value.toLowerCase().trim();
    const category = document.getElementById('categoryFilter').value;
    const location = document.getElementById('locationFilter').value;
    const cards = document.querySelectorAll('.vendor-card');
    let visible = 0;

    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const cardCategory = card.dataset.category;
        const cardLocation = card.dataset.location;

        const matchesSearch = !search ||
            name.includes(search) ||
            cardCategory.toLowerCase().includes(search) ||
            cardLocation.toLowerCase().includes(search) ||
            (card.dataset.vendor || '').toLowerCase().includes(search);
        const matchesCategory = !category || cardCategory === category;
        const matchesLocation = !location || cardLocation === location;

        const show = matchesSearch && matchesCategory && matchesLocation;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('vendorCount').textContent = visible;
    document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';
}

function resetFilters() {
    quickCategory('');
    document.getElementById('vendorSearch').value = '';
    document.getElementById('locationFilter').value = '';
    document.getElementById('sortFilter').value = 'featured';
    filterVendors();
    sortVendors();
}

/* =========================================================
   QUICK CATEGORIES
========================================================= */
function quickCategory(category) {
    document.getElementById('categoryFilter').value = category;
    document.querySelectorAll('.category-pill').forEach(pill => {
        pill.classList.remove('active');
        if ((category === '' && pill.textContent.trim() === 'All') ||
            pill.textContent.trim().toLowerCase() === category.toLowerCase()) {
            pill.classList.add('active');
        }
    });
    filterVendors();
}

/* =========================================================
   SORTING
========================================================= */
function sortVendors() {
    const sort = document.getElementById('sortFilter').value;
    const grid = document.getElementById('vendorGrid');
    const cards = Array.from(grid.querySelectorAll('.vendor-card'));

    cards.sort((a, b) => {
        if (sort === 'featured') return Number(b.dataset.featured) - Number(a.dataset.featured);
        if (sort === 'rating') return (Number(b.dataset.rating) || 0) - (Number(a.dataset.rating) || 0);
        if (sort === 'priceLow') return Number(a.dataset.price) - Number(b.dataset.price);
        if (sort === 'priceHigh') return Number(b.dataset.price) - Number(a.dataset.price);
        return 0;
    });

    cards.forEach(card => grid.appendChild(card));
}

/* =========================================================
   FAVOURITES
========================================================= */
function toggleFavorite(button) {
    button.classList.toggle('active');
    const icon = button.querySelector('i');
    if (button.classList.contains('active')) {
        icon.className = 'ri-heart-fill';
        showToast('Vendor added to favourites.');
    } else {
        icon.className = 'ri-heart-line';
        showToast('Vendor removed from favourites.');
    }
}

/* =========================================================
   VENDOR PROFILE
========================================================= */
let activeVendor = null;

function openVendorProfile(vendorId) {
    const vendor = window.vendors[vendorId];
    if (!vendor) { showToast('Vendor profile unavailable.'); return; }
    activeVendor = vendorId;

    document.getElementById('profileCoverImage').src = vendor.cover;
    document.getElementById('profileLogo').src = vendor.logo;
    document.getElementById('profileName').textContent = vendor.name;
    document.getElementById('profileLocation').innerHTML = `<i class="ri-map-pin-line"></i> ${vendor.location}, Zambia`;
    document.getElementById('profileAbout').textContent = vendor.about;
    document.getElementById('profileExperience').textContent = vendor.experience;
    document.getElementById('profileEvents').textContent = vendor.events;
    document.getElementById('profileRating').textContent = vendor.rating;
    document.getElementById('reviewAverage').textContent = vendor.rating;
    document.getElementById('reviewCount').textContent = `${vendor.reviews} reviews`;

    const services = document.getElementById('profileServices');
    services.innerHTML = '';
    (vendor.services || []).forEach(service => {
        const tag = document.createElement('span');
        tag.className = 'service-tag';
        tag.textContent = service;
        services.appendChild(tag);
    });

    const eventTypes = document.getElementById('profileEventTypes');
    eventTypes.innerHTML = '';
    (vendor.eventTypes || []).forEach(type => {
        const tag = document.createElement('span');
        tag.className = 'service-tag';
        tag.textContent = type;
        eventTypes.appendChild(tag);
    });

    const packageContainer = document.getElementById('profilePackages');
    packageContainer.innerHTML = '';
    (vendor.packages || []).forEach(pkg => {
        const el = document.createElement('div');
        el.className = 'package';
        el.innerHTML = `
            <div class="package-top">
                <h4>${pkg.name}</h4>
                <div class="package-price">From ${pkg.price}</div>
            </div>
            <ul>${(pkg.items || []).map(item => `<li>${item}</li>`).join('')}</ul>
            <button onclick="requestPackageQuote('${escapeQuotes(vendor.name)}','${escapeQuotes(pkg.name)}')">Request This Package</button>
        `;
        packageContainer.appendChild(el);
    });

    const portfolio = document.getElementById('profilePortfolio');
    portfolio.innerHTML = '';
    (vendor.portfolio || []).forEach(image => {
        const item = document.createElement('div');
        item.className = 'portfolio-item';
        item.innerHTML = `<img src="${image}" alt="${vendor.name} portfolio" onclick="openLightbox('${image}')">`;
        portfolio.appendChild(item);
    });

    const reviews = document.getElementById('profileReviews');
    reviews.innerHTML = '';
    (vendor.customerReviews || []).forEach(review => {
        const el = document.createElement('div');
        el.className = 'review';
        el.innerHTML = `
            <div class="review-head">
                <span class="review-name">${review[0]}</span>
                <span class="stars">${review[1]}</span>
            </div>
            <p>${review[2]}</p>
        `;
        reviews.appendChild(el);
    });

    const openWhatsApp = () => contactWhatsApp(vendor.name);
    document.getElementById('profileWhatsApp').onclick = openWhatsApp;
    document.getElementById('sidebarWhatsApp').onclick = openWhatsApp;

    resetReviewForm();

    document.getElementById('profileModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function escapeQuotes(str) {
    return String(str).replace(/'/g, "\\'");
}

/* =========================================================
   RATINGS / REVIEWS
========================================================= */
let selectedReviewRating = 0;

function setReviewRating(value) {
    selectedReviewRating = value;
    document.querySelectorAll('#reviewStars .review-star').forEach(btn => {
        const filled = Number(btn.dataset.value) <= value;
        btn.querySelector('i').className = filled ? 'ri-star-fill' : 'ri-star-line';
    });
}

function resetReviewForm() {
    selectedReviewRating = 0;
    const body = document.getElementById('reviewBody');
    if (body) body.value = '';
    document.querySelectorAll('#reviewStars .review-star i').forEach(i => { i.className = 'ri-star-line'; });
}

async function submitReview() {
    if (!activeVendor) return;
    if (!requireAuth('Create an account to leave a review.')) { closeModal('profileModal'); return; }

    if (!selectedReviewRating) { showToast('Please select a star rating.'); return; }
    const body = document.getElementById('reviewBody').value.trim();
    if (!body) { showToast('Please write a short review.'); return; }

    try {
        const url = `${EH.routes.vendorsBase}/${activeVendor}/reviews`;
        const res = await postJson(url, { rating: selectedReviewRating, body });

        // Prepend the new review in the modal.
        const reviews = document.getElementById('profileReviews');
        const el = document.createElement('div');
        el.className = 'review';
        el.innerHTML = `
            <div class="review-head">
                <span class="review-name">${res.review.author}</span>
                <span class="stars">${res.review.stars}</span>
            </div>
            <p>${res.review.body}</p>`;
        reviews.prepend(el);

        // Update the aggregate figures in the modal...
        document.getElementById('reviewAverage').textContent = res.vendor.rating;
        document.getElementById('reviewCount').textContent = `${res.vendor.reviews} reviews`;
        document.getElementById('profileRating').textContent = res.vendor.rating;

        // ...the cached vendor payload...
        if (window.vendors[activeVendor]) {
            window.vendors[activeVendor].rating = res.vendor.rating;
            window.vendors[activeVendor].reviews = res.vendor.reviews;
        }

        // ...and the marketplace card.
        const card = document.querySelector(`.vendor-card[data-vendor="${CSS.escape(activeVendor)}"]`);
        if (card) {
            card.dataset.rating = res.vendor.rating;
            const ratingEl = card.querySelector('.vendor-rating');
            if (ratingEl) ratingEl.innerHTML = `<i class="ri-star-fill"></i> ${res.vendor.rating}`;
        }

        resetReviewForm();
        showToast(res.message || 'Review posted.');
    } catch (error) {
        showToast(error.message);
    }
}

/* =========================================================
   QUOTE FROM PROFILE / PACKAGE
========================================================= */
function openQuoteFromProfile() {
    if (!activeVendor) return;
    const vendor = window.vendors[activeVendor];
    closeModal('profileModal');
    document.getElementById('quoteVendorName').textContent = vendor.name;
    document.getElementById('quoteVendorName').dataset.vendorSlug = vendor.id;
    openModal('quoteModal');
}

function requestPackageQuote(vendorName, packageName) {
    closeModal('profileModal');
    const label = document.getElementById('quoteVendorName');
    label.textContent = `${vendorName} — ${packageName}`;
    if (activeVendor) label.dataset.vendorSlug = activeVendor;
    document.getElementById('quoteRequirements').value = `I am interested in the ${packageName} package.`;
    openModal('quoteModal');
}

/* =========================================================
   WHATSAPP
========================================================= */
function contactWhatsApp(vendorName) {
    const vendor = Object.values(window.vendors).find(v => v.name === vendorName);
    if (!vendor || !vendor.phone) { showToast('Vendor contact unavailable.'); return; }
    const url = `https://wa.me/${vendor.phone}?text=` +
        encodeURIComponent(`Hello ${vendor.name}, I found you on EventHub Zambia and would like to enquire about your services.`);
    window.open(url, '_blank', 'noopener,noreferrer');
}

/* =========================================================
   FORM SUBMISSIONS (Laravel-backed)
========================================================= */
async function submitQuote() {
    if (!requireAuth('Create an account to send a quote request.')) { closeModal('quoteModal'); return; }

    const label = document.getElementById('quoteVendorName');
    try {
        const res = await postJson(EH.routes.quotes, {
            vendor_slug: label.dataset.vendorSlug || null,
            vendor_name: label.textContent.trim(),
            name: document.getElementById('quoteName').value.trim(),
            phone: document.getElementById('quotePhone').value.trim(),
            event_type: document.getElementById('quoteEvent').value,
            event_date: document.getElementById('quoteDate').value || null,
            location: document.getElementById('quoteLocation').value.trim(),
            guest_count: Number(document.getElementById('quoteGuests').value || 0),
            requirements: document.getElementById('quoteRequirements').value.trim(),
        });
        closeModal('quoteModal');
        showToast(res.message || 'Quote request sent.');
    } catch (error) {
        showToast(error.message);
    }
}

async function submitPlanner() {
    if (!requireAuth('Create an account to save your event plan.')) { closeModal('plannerModal'); return; }

    try {
        const res = await postJson(EH.routes.planner, {
            name: document.getElementById('plannerName').value.trim(),
            phone: document.getElementById('plannerPhone').value.trim(),
            event_type: document.getElementById('plannerEventType').value,
            event_date: document.getElementById('plannerDate').value || null,
            location: document.getElementById('plannerLocation').value.trim(),
            guest_count: Number(document.getElementById('plannerGuests').value || 0),
            notes: document.getElementById('plannerNeeds').value.trim(),
        });
        closeModal('plannerModal');
        showToast(res.message || 'Your event plan has been saved.');
    } catch (error) {
        showToast(error.message);
    }
}

async function saveVendorApplication() {
    if (!requireAuth('Create an account first to become a vendor.')) { closeModal('vendorModal'); return; }

    try {
        const res = await postJson(EH.routes.vendorApply, {
            business_name: document.getElementById('vendorBusinessName').value.trim(),
            contact_person: document.getElementById('vendorContactPerson').value.trim(),
            phone: document.getElementById('vendorPhone').value.trim(),
            email: document.getElementById('vendorEmail').value.trim(),
            category: document.getElementById('vendorCategory').value,
            location: document.getElementById('vendorLocation').value,
            description: document.getElementById('vendorDescription').value.trim(),
            facebook_url: document.getElementById('vendorFacebookUrl').value.trim() || null,
        });
        if (EH.user) EH.user.role = 'vendor';
        closeModal('vendorModal');
        showToast(res.message || 'Vendor application submitted.');
        document.getElementById('vendorBusinessName').value = '';
        document.getElementById('vendorContactPerson').value = '';
        document.getElementById('vendorPhone').value = '';
        document.getElementById('vendorEmail').value = '';
        document.getElementById('vendorDescription').value = '';
        document.getElementById('vendorFacebookUrl').value = '';
        window.eventHubPendingClaim = null;
    } catch (error) {
        showToast(error.message);
    }
}

/* =========================================================
   FREE VENDOR DISCOVERY (Facebook)
========================================================= */
function safeUrl(value, fallback = '') {
    const raw = (value || '').trim();
    if (!raw) return fallback;
    try {
        const url = new URL(raw);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : fallback;
    } catch (_) { return fallback; }
}

function searchFacebookVendors() {
    const query = document.getElementById('facebookVendorQuery')?.value.trim() || 'event vendors';
    const location = document.getElementById('facebookVendorLocation')?.value || 'Lusaka';
    const fullQuery = `${query} ${location} Zambia`;
    window.open(`https://www.facebook.com/search/pages/?q=${encodeURIComponent(fullQuery)}`, '_blank', 'noopener,noreferrer');
    showToast(`Opening Facebook search for ${fullQuery}.`);
}

function openFreeVendorRegistration() {
    window.eventHubPendingClaim = null;
    openModal('vendorModal');
}

function openClaimModal() {
    const claim = window.eventHubPendingClaim || {};
    openModal('vendorModal');
    const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
    set('vendorBusinessName', claim.businessName);
    set('vendorCategory', claim.category);
    set('vendorLocation', claim.location);
    set('vendorFacebookUrl', claim.facebookUrl);
    if (claim.facebookUrl) {
        document.getElementById('vendorDescription').value =
            `Claim request for the Facebook business/page: ${claim.businessName}. Facebook URL: ${claim.facebookUrl}`;
    }
    showToast('Vendor claim form pre-filled. Complete your details and submit.');
}

/* =========================================================
   LIGHTBOX
========================================================= */
function openLightbox(image) {
    document.getElementById('lightboxImage').src = image;
    document.getElementById('lightbox').classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}

/* =========================================================
   MOBILE MENU
========================================================= */
const mobileToggle = document.querySelector('.mobile-toggle');
if (mobileToggle) {
    mobileToggle.addEventListener('click', function () {
        const nav = document.querySelector('.nav');
        if (nav.style.display === 'flex') {
            nav.style.display = '';
        } else {
            Object.assign(nav.style, {
                position: 'absolute', top: '75px', left: '5%', right: '5%',
                background: '#050505', padding: '20px', display: 'flex',
                flexDirection: 'column', alignItems: 'flex-start',
                border: '1px solid rgba(255,255,255,.1)', borderRadius: '10px',
            });
        }
    });
}

/* =========================================================
   KEYBOARD: ENTER TO SEARCH
========================================================= */
const fbQuery = document.getElementById('facebookVendorQuery');
if (fbQuery) fbQuery.addEventListener('keydown', e => { if (e.key === 'Enter') searchFacebookVendors(); });

const heroSearchInput = document.getElementById('heroSearch');
if (heroSearchInput) heroSearchInput.addEventListener('keydown', e => { if (e.key === 'Enter') heroSearch(); });

/* =========================================================
   INIT
========================================================= */
document.addEventListener('DOMContentLoaded', function () {
    setAuthMode('login');
    updateAuthUI();
    sortVendors();
});
