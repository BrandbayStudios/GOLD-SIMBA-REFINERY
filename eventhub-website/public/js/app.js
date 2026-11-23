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
   SMART SEARCH
   Understands everyday phrases such as "bridal makeup artist in Lusaka"
   or "catering for 200 guests": picks out the service, the town and any
   other useful words, and never leaves the customer at a dead end.
========================================================= */
const SEARCH_CATEGORIES = [
    // Most specific services first, so "wedding car hire" is car hire, not a venue.
    { value: 'Car Hire', label: 'car hire', words: ['car', 'cars', 'carhire', 'transport', 'chauffeur', 'limo', 'limousine', 'vehicle', 'ride', 'bus', 'shuttle'] },
    { value: 'Beauty', label: 'beauty and make-up', words: ['makeup', 'make-up', 'mua', 'hair', 'hairdresser', 'braids', 'nails', 'beauty', 'salon', 'lashes', 'barber', 'spa'] },
    { value: 'Photography', label: 'photographers', words: ['photo', 'photos', 'photographer', 'photography', 'video', 'videographer', 'videography', 'camera', 'drone', 'shoot', 'photobooth'] },
    { value: 'DJ', label: 'DJs and MCs', words: ['dj', 'djs', 'mc', 'mcs', 'emcee', 'music', 'sound', 'pa', 'entertainment', 'entertainer', 'band', 'musician', 'host'] },
    { value: 'Catering', label: 'caterers', words: ['catering', 'caterer', 'caterers', 'food', 'buffet', 'chef', 'cook', 'meals', 'cake', 'cakes', 'baker', 'drinks', 'bar'] },
    { value: 'Decor', label: 'décor', words: ['decor', 'decoration', 'decorations', 'decorator', 'decorators', 'flowers', 'floral', 'florist', 'balloons', 'styling', 'stylist', 'tent', 'tents', 'chairs', 'draping'] },
    { value: 'Fashion & Outfits', label: 'fashion and outfits', words: ['fashion', 'outfit', 'outfits', 'dress', 'dresses', 'gown', 'gowns', 'suit', 'suits', 'tailor', 'designer', 'chitenge', 'attire', 'clothes', 'tuxedo'] },
    { value: 'Venue', label: 'venues', words: ['venue', 'venues', 'hall', 'halls', 'lodge', 'garden', 'gardens', 'hotel', 'space', 'conference', 'reception', 'grounds'] },
];
const SEARCH_TOWNS = ['Lusaka', 'Ndola', 'Kitwe', 'Livingstone', 'Kabwe', 'Chipata', 'Solwezi', 'Mansa', 'Kasama', 'Chingola',
    'Mufulira', 'Luanshya', 'Kafue', 'Mazabuka', 'Choma', 'Chilanga', 'Mongu', 'Monze', 'Siavonga', 'Kalulushi'];
// Words that describe the event or are just filler: they never narrow the results.
const SEARCH_IGNORE = new Set(('a an and or the for in at on near me my our i we need needed looking look want find get some any with to of from ' +
    'best good cheap affordable top nice please around zambia guests guest people pax person ' +
    'wedding weddings bridal bride groom kitchen party parties birthday graduation corporate funeral memorial traditional ' +
    'ceremony ceremonies event events celebration anniversary baby shower engagement church artist artists service services company').split(' '));

function normaliseSearchText(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/make\s*-?\s*up/g, 'makeup').replace(/car\s+hire/g, 'carhire').replace(/[^a-z0-9\s-]/g, ' ');
}

/* Turns a phrase into { category, town, servedTown, words }. */
function parseVendorQuery(text) {
    const tokens = normaliseSearchText(text).split(/\s+/).filter(Boolean);
    const singular = t => (t.length > 3 && t.endsWith('s') ? t.slice(0, -1) : t);
    let category = null;
    for (const cat of SEARCH_CATEGORIES) {
        if (tokens.some(t => cat.words.includes(t) || cat.words.includes(singular(t)))) { category = cat; break; }
    }
    const town = SEARCH_TOWNS.find(name => tokens.includes(name.toLowerCase())) || '';
    const served = town && document.querySelector(`#locationFilter option[value="${town}"]`) ? town : '';
    const categoryWords = new Set(SEARCH_CATEGORIES.flatMap(c => c.words));
    const words = tokens.filter(t => !SEARCH_IGNORE.has(t) && !/^\d+$/.test(t) && t.length > 1 &&
        !categoryWords.has(t) && !categoryWords.has(singular(t)) && t !== town.toLowerCase());
    return { category, town, servedTown: served, words };
}

/* Everything a card can be found by: name, category, town, services and description. */
function vendorSearchText(card) {
    const v = (window.vendors || {})[card.dataset.vendor] || {};
    return normaliseSearchText([card.dataset.name, card.dataset.category, card.dataset.location, card.dataset.vendor,
        (v.services || []).join(' '), (v.eventTypes || []).join(' '), v.about || ''].join(' '));
}

/* The marketplace search box: every meaningful word must match the card,
   by its own text or by the category a word stands for ("makeup" finds Beauty). */
function cardMatchesSearch(card, search) {
    if (!search) return true;
    const tokens = normaliseSearchText(search).split(/\s+/).filter(t => t && !SEARCH_IGNORE.has(t) && !/^\d+$/.test(t));
    if (!tokens.length) return true;
    const haystack = vendorSearchText(card);
    return tokens.every(t => {
        if (haystack.includes(t)) return true;
        const stem = t.length > 3 && t.endsWith('s') ? t.slice(0, -1) : t;
        if (haystack.includes(stem)) return true;
        const cat = SEARCH_CATEGORIES.find(c => c.words.includes(t) || c.words.includes(stem));
        return Boolean(cat && card.dataset.category === cat.value);
    });
}

/* A short, persistent note above the results explaining what was searched. */
function setSearchNote(message) {
    let note = document.getElementById('searchNote');
    if (!note) {
        const grid = document.getElementById('vendorGrid');
        if (!grid) return;
        note = document.createElement('div');
        note.id = 'searchNote';
        note.setAttribute('role', 'status');
        note.style.cssText = 'margin:0 0 18px;padding:12px 16px;border-radius:12px;border-left:4px solid #D4A73C;background:rgba(212,167,60,.12);font-size:14px;line-height:1.5;color:inherit;';
        grid.parentNode.insertBefore(note, grid);
    }
    note.textContent = message || '';
    note.style.display = message ? 'block' : 'none';
}

function visibleVendorCount() {
    return Array.from(document.querySelectorAll('#vendorGrid .vendor-card')).filter(c => c.style.display !== 'none').length;
}

/* Fill the "search Facebook / Google" panel so customers in uncovered towns have a next step. */
function prefillDiscovery(category, town) {
    const query = document.getElementById('facebookVendorQuery');
    const city = document.getElementById('facebookVendorLocation');
    if (query && category) query.value = category.label;
    if (city && town && city.querySelector(`option[value="${town}"]`)) city.value = town;
}

function smartSearch(text) {
    const query = String(text || '').trim();
    const { category, town, servedTown, words } = parseVendorQuery(query);
    const setFilters = (cat, loc, search) => {
        document.getElementById('vendorSearch').value = search;
        if (typeof quickCategory === 'function') quickCategory(cat); else document.getElementById('categoryFilter').value = cat;
        document.getElementById('locationFilter').value = loc;
        filterVendors();
    };
    const what = category ? category.label : 'vendors';

    const wanted = normaliseSearchText(query).trim();
    const byName = Array.from(document.querySelectorAll('#vendorGrid .vendor-card'))
        .filter(card => wanted.length > 2 && normaliseSearchText(card.dataset.name).includes(wanted));
    if (byName.length) {
        setFilters('', '', query);
        return setSearchNote(`Showing results for "${query}".`);
    }

    if (!category && !town) {
        // No service or town recognised: search by the remaining words (names, services, descriptions).
        setFilters('', '', words.join(' ') || query);
        if (visibleVendorCount()) return setSearchNote(`Showing results for "${query}".`);
        setFilters('', '', '');
        return setSearchNote(`Nothing matched "${query}". Here are all EventHub vendors. Try a service such as "photographer" or "catering", or search Facebook and Google below.`);
    }

    if (town) prefillDiscovery(category, town);
    setFilters(category ? category.value : '', servedTown, '');
    if (visibleVendorCount()) {
        if (town && !servedTown) {
            return setSearchNote(`EventHub doesn't list vendors in ${town} yet. Showing ${what} in other towns. Search Facebook and Google for ${what} in ${town} below.`);
        }
        return setSearchNote(`Showing ${what}${servedTown ? ` in ${servedTown}` : ''}.`);
    }

    // Nothing in that town: show the service everywhere, and say so plainly.
    prefillDiscovery(category, town);
    setFilters(category ? category.value : '', '', '');
    const elsewhere = visibleVendorCount();
    if (town && elsewhere) {
        return setSearchNote(`No ${what} listed in ${town} yet. Showing ${what} in other towns. You can also search Facebook and Google for ${what} in ${town} below.`);
    }
    if (elsewhere) return setSearchNote(`Showing ${what}.`);
    setFilters('', '', '');
    return setSearchNote(`No ${what} listed yet. Here are all EventHub vendors, or search Facebook and Google below.`);
}

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
    smartSearch(value);
    document.getElementById('vendors').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* "Ask EventHub": the same understanding as the main search, with a toast summary. */
function aiVendorSearch() {
    const input = document.getElementById('heroSearch');
    const query = input ? input.value.trim() : '';
    if (!query) {
        showToast('Tell EventHub what you are looking for first.');
        if (input) input.focus();
        return;
    }
    smartSearch(query);
    const visible = visibleVendorCount();
    document.getElementById('vendors').scrollIntoView({ behavior: 'smooth', block: 'start' });
    showToast(`${visible} EventHub match${visible === 1 ? '' : 'es'} found.`);
}

/* =========================================================
   MARKETPLACE FILTERING
========================================================= */
function filterVendors() {
    const search = document.getElementById('vendorSearch').value.trim();
    const category = document.getElementById('categoryFilter').value;
    const location = document.getElementById('locationFilter').value;
    const cards = document.querySelectorAll('.vendor-card');
    let visible = 0;

    cards.forEach(card => {
        const matchesCategory = !category || card.dataset.category === category;
        const matchesLocation = !location || card.dataset.location === location;
        const show = matchesCategory && matchesLocation && cardMatchesSearch(card, search);
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('vendorCount').textContent = visible;
    const empty = document.getElementById('noResults');
    empty.style.display = visible === 0 ? 'block' : 'none';
    if (visible === 0) showEmptyStateAction(empty, category, location);
}

/* When filters leave nothing, offer the obvious next step instead of a dead end. */
function showEmptyStateAction(empty, category, location) {
    let action = document.getElementById('noResultsAction');
    if (!action) {
        action = document.createElement('button');
        action.id = 'noResultsAction';
        action.type = 'button';
        action.style.cssText = 'margin-top:14px;padding:10px 18px;border-radius:999px;border:0;background:#0B5D45;color:#fff;font-weight:700;cursor:pointer;';
        empty.appendChild(action);
    }
    const cat = SEARCH_CATEGORIES.find(c => c.value === category);
    if (category && location) {
        action.textContent = `Show ${cat ? cat.label : category} in all towns`;
        action.onclick = () => {
            document.getElementById('locationFilter').value = '';
            prefillDiscovery(cat, location);
            filterVendors();
            setSearchNote(`No ${cat ? cat.label : category} listed in ${location} yet. Showing other towns. You can also search Facebook and Google for ${location} below.`);
        };
    } else {
        action.textContent = 'Show all vendors';
        action.onclick = () => resetFilters();
    }
}

function resetFilters() {
    setSearchNote('');
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
   FREE VENDOR DISCOVERY (Facebook / Google)
========================================================= */
function safeUrl(value, fallback = '') {
    const raw = (value || '').trim();
    if (!raw) return fallback;
    try {
        const url = new URL(raw);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : fallback;
    } catch (_) { return fallback; }
}

function externalVendorQuery() {
    const query = document.getElementById('facebookVendorQuery')?.value.trim() || 'event vendors';
    const location = document.getElementById('facebookVendorLocation')?.value || 'Lusaka';
    return `${query} ${location} Zambia`;
}

function searchGoogleVendors() {
    const fullQuery = externalVendorQuery();
    window.open(`https://www.google.com/search?q=${encodeURIComponent(fullQuery)}`, '_blank', 'noopener,noreferrer');
    showToast(`Opening Google search for ${fullQuery}.`);
}

function searchGoogleMapsVendors() {
    const fullQuery = externalVendorQuery();
    window.open(`https://www.google.com/maps/search/${encodeURIComponent(fullQuery)}`, '_blank', 'noopener,noreferrer');
    showToast(`Opening Google Maps for ${fullQuery}.`);
}

function searchFacebookVendors() {
    const fullQuery = externalVendorQuery();
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

/* =========================================================
   SEARCH NOTE: clear it once the customer changes filters by hand
========================================================= */
['vendorSearch', 'categoryFilter', 'locationFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', () => setSearchNote(''));
});
document.querySelectorAll('.category-pill').forEach(pill => pill.addEventListener('click', () => setSearchNote('')));
