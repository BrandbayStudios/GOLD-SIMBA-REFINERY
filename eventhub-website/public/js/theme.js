/* =========================================================
   EVENTHUB THEME: menu, floating actions, scroll effects.
   Loaded after app.js; uses its global functions
   (openModal, quickCategory, resetFilters, filterVendors...).
========================================================= */
(function eventHubTheme() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canHover = window.matchMedia('(hover: hover)').matches;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const call = (name, ...args) => (typeof window[name] === 'function' ? window[name](...args) : undefined);
    const scrollToId = (id) => document.getElementById(id)?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth' });

    /* ---------- Missing photos fall back to an event photo ---------- */
    const FALLBACK = '/images/eventhub/photographer-960.webp';
    document.addEventListener('error', (e) => {
        const img = e.target;
        if (!(img instanceof HTMLImageElement) || img.dataset.fallback) return;
        img.dataset.fallback = '1';
        img.src = FALLBACK;
    }, true);
    $$('img').forEach((img) => { if (img.complete && img.src && img.naturalWidth === 0) img.dispatchEvent(new Event('error')); });

    /* ---------- Header: solid once you scroll ---------- */
    const header = $('#siteHeader');
    const onScroll = () => {
        header?.classList.toggle('is-scrolled', window.scrollY > 30);
        updateTimeline();
    };

    /* ---------- Desktop menu: gold pill slides to the hovered / current link ---------- */
    const nav = $('#mainNav');
    const indicator = $('.nav-indicator', nav || document);
    const navLinks = nav ? $$('a', nav) : [];
    let currentLink = null;
    const moveIndicator = (link) => {
        if (!indicator) return;
        if (!link) { indicator.style.opacity = '0'; return; }
        indicator.style.opacity = '1';
        indicator.style.width = `${link.offsetWidth}px`;
        indicator.style.transform = `translateX(${link.offsetLeft}px)`;
    };
    navLinks.forEach((link) => {
        link.addEventListener('mouseenter', () => moveIndicator(link));
        link.addEventListener('focus', () => moveIndicator(link));
    });
    nav?.addEventListener('mouseleave', () => moveIndicator(currentLink));

    // Highlight the section you're reading.
    if ('IntersectionObserver' in window && navLinks.length) {
        const sections = navLinks.map((l) => document.querySelector(l.getAttribute('href'))).filter(Boolean);
        const spy = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                currentLink = navLinks.find((l) => l.getAttribute('href') === `#${entry.target.id}`) || null;
                navLinks.forEach((l) => l.classList.toggle('is-current', l === currentLink));
                if (!nav.matches(':hover')) moveIndicator(currentLink);
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        sections.forEach((s) => spy.observe(s));
    }

    /* ---------- Phone menu: full-screen, staggered links ---------- */
    const toggle = $('#menuToggle');
    const menu = $('#mobileMenu');
    const setMenu = (open) => {
        document.body.classList.toggle('menu-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
        toggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        menu?.setAttribute('aria-hidden', String(!open));
    };
    toggle?.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open')));
    $$('a, button', menu || document.createElement('div')).forEach((el) => el.addEventListener('click', () => setMenu(false)));

    /* ---------- Floating quick-action button ---------- */
    const fab = $('#fab');
    const fabMain = $('#fabMain');
    const setFab = (open) => {
        fab?.classList.toggle('is-open', open);
        fabMain?.setAttribute('aria-expanded', String(open));
    };
    fabMain?.addEventListener('click', () => setFab(!fab.classList.contains('is-open')));
    $$('[data-fab-close]').forEach((el) => el.addEventListener('click', () => setFab(false)));
    document.addEventListener('click', (e) => { if (fab && !fab.contains(e.target)) setFab(false); });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        setMenu(false);
        setFab(false);
    });

    /* ---------- Shared actions: plan / list business / claim ---------- */
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-action]');
        if (!trigger) return;
        const action = trigger.dataset.action;
        if (!['plan', 'vendor', 'claim'].includes(action)) return;
        e.preventDefault();
        setFab(false);
        if (action === 'plan') call('openModal', 'plannerModal');
        if (action === 'vendor') call('openModal', 'vendorModal');
        if (action === 'claim') call('openFreeVendorRegistration');
    });

    /* ---------- Footer + city shortcuts filter the marketplace ---------- */
    const showCity = (city) => {
        call('resetFilters');
        const select = $('#locationFilter');
        if (select) select.value = city;
        call('filterVendors');
        scrollToId('vendors');
    };
    $$('[data-footer-category]').forEach((link) => link.addEventListener('click', () => {
        call('resetFilters');
        call('quickCategory', link.dataset.footerCategory);
    }));
    $$('[data-footer-city], .city-chip').forEach((el) => el.addEventListener('click', (e) => {
        e.preventDefault();
        showCity(el.dataset.footerCity || el.dataset.city);
    }));
    $$('[data-plan-type]').forEach((link) => link.addEventListener('click', (e) => {
        e.preventDefault();
        call('openModal', 'plannerModal');
        const select = $('#plannerEventType');
        if (select) select.value = link.dataset.planType;
    }));

    /* ---------- "How it works": gold line fills as you scroll ---------- */
    const timeline = $('#howTimeline');
    function updateTimeline() {
        if (!timeline) return;
        const r = timeline.getBoundingClientRect();
        const start = window.innerHeight * 0.75;
        const progress = Math.min(1, Math.max(0, (start - r.top) / r.height));
        timeline.style.setProperty('--progress', progress.toFixed(3));
        $$('li', timeline).forEach((li, i, all) => li.classList.toggle('is-reached', progress >= (i + 0.5) / all.length - 0.15));
    }

    /* ---------- Hero numbers count up ---------- */
    const countUp = (el) => {
        const target = parseInt(el.dataset.count, 10) || 0;
        if (reduceMotion || target === 0) { el.textContent = String(target); return; }
        const start = performance.now();
        const tick = (now) => {
            const t = Math.min(1, (now - start) / 1300);
            el.textContent = String(Math.round(target * (1 - Math.pow(1 - t, 3))));
            if (t < 1) requestAnimationFrame(tick);
        };
        el.textContent = '0';
        requestAnimationFrame(tick);
    };

    /* ---------- Reveal on scroll (content stays visible if motion is reduced) ---------- */
    const revealSelectors = ['.section-head', '.marketplace-toolbar', '.vendor-card', '.free-discovery-card', '.spot-card', '.how-x-photo', '.timeline li', '.vendor-cta-inner > *', '.city-chips', '.map-box', '.footer-col', '.footer-brand'];
    revealSelectors.forEach((sel) => $$(sel).forEach((el) => el.classList.add('reveal')));
    $$('.reveal').forEach((el) => {
        const group = Array.from(el.parentElement?.children || []).filter((c) => c.classList.contains('reveal'));
        el.style.setProperty('--d', `${(Math.max(0, group.indexOf(el)) % 4) * 90}ms`);
    });
    if (reduceMotion || !('IntersectionObserver' in window)) {
        $$('.reveal').forEach((el) => el.classList.add('is-in'));
        $$('[data-count]').forEach(countUp);
    } else {
        document.documentElement.classList.add('fx');
        const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-in');
            io.unobserve(entry.target);
        }), { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
        $$('.reveal').forEach((el) => io.observe(el));
        const stats = $('.hero-x-stats');
        if (stats) {
            const so = new IntersectionObserver((entries) => {
                if (!entries[0].isIntersecting) return;
                $$('[data-count]', stats).forEach(countUp);
                so.disconnect();
            }, { threshold: 0.4 });
            so.observe(stats);
        }
    }

    /* ---------- Pointer effects (mouse/trackpad only) ---------- */
    if (canHover && !reduceMotion) {
        // Hero arches drift slightly with the pointer.
        const gallery = $('.hero-x-gallery');
        const hero = $('.hero-x');
        hero?.addEventListener('pointermove', (e) => {
            const r = hero.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - 0.5;
            const y = (e.clientY - r.top) / r.height - 0.5;
            gallery?.style.setProperty('--px', x.toFixed(3));
            gallery?.style.setProperty('--py', y.toFixed(3));
        });
        // Category photos tilt toward the pointer.
        $$('.spot-card').forEach((card) => {
            card.addEventListener('pointermove', (e) => {
                const r = card.getBoundingClientRect();
                card.style.setProperty('--rx', `${((e.clientY - r.top) / r.height - 0.5) * -6}deg`);
                card.style.setProperty('--ry', `${((e.clientX - r.left) / r.width - 0.5) * 8}deg`);
            });
            card.addEventListener('pointerleave', () => { card.style.setProperty('--rx', '0deg'); card.style.setProperty('--ry', '0deg'); });
        });
        // Vendor cards: gold glow follows the pointer.
        $$('.vendor-card').forEach((card) => card.addEventListener('pointermove', (e) => {
            const r = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${e.clientX - r.left}px`);
            card.style.setProperty('--my', `${e.clientY - r.top}px`);
        }));
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', () => moveIndicator(currentLink));
    onScroll();
    requestAnimationFrame(() => document.body.classList.add('is-loaded'));
})();
