/* ==========================================================================
   EventHub Zambia — shared UI: header, footer, vendor cards, favourites,
   toasts, modals, tabs and form validation.
   ========================================================================== */
(function () {
  var D = window.EH_DATA;
  var page = (location.pathname.split('/').pop() || 'index.html').replace(/\?.*$/, '');

  var LOGO = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 7h7l-5.6 4.3L18.5 21 12 16.6 5.5 21l2.1-7.7L2 9h7z" fill="#D4AF5A"/></svg>';

  var NAV = [
    ['index.html', 'Home'],
    ['vendors.html', 'Find vendors'],
    ['events.html', 'Events'],
    ['planner.html', 'Planner'],
    ['vendor-register.html', 'List your business'],
    ['contact.html', 'Contact']
  ];

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function kwacha(n) { return 'K' + Number(n).toLocaleString('en-ZM'); }

  function header() {
    var user = window.EH_API && EH_API.getUser && EH_API.getUser();
    var links = NAV.map(function (n) { return '<a href="' + n[0] + '"' + (page === n[0] ? ' aria-current="page"' : '') + '>' + n[1] + '</a>'; }).join('');
    var account = user
      ? '<a class="btn btn-ghost btn-sm" href="' + (user.role === 'vendor' ? 'vendor-dashboard.html' : user.role === 'admin' ? 'admin-dashboard.html' : 'planner.html') + '"><i class="fa-regular fa-user"></i> ' + esc((user.name || 'Account').split(' ')[0]) + '</a>'
      : '<a class="btn btn-ghost btn-sm" href="login.html">Log in</a><a class="btn btn-primary btn-sm" href="register.html">Sign up</a>';
    return '<header class="site-header"><div class="wrap">' +
      '<a class="brand" href="index.html" aria-label="EventHub Zambia home"><span class="brand-mark">' + LOGO + '</span><span>EventHub<small>Zambia’s event marketplace</small></span></a>' +
      '<nav class="nav" aria-label="Main">' + links + '</nav>' +
      '<div class="header-actions"><a class="fav-link" href="favourites.html" aria-label="Saved vendors"><i class="fa-regular fa-heart"></i><span class="fav-count" data-fav-count data-zero>0</span></a>' + account +
      '<button class="menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav"><i class="fa-solid fa-bars"></i></button></div>' +
      '</div><nav class="mobile-nav" id="mobile-nav" aria-label="Mobile">' + links + (user ? '' : '<a href="login.html">Log in</a><a href="register.html">Sign up</a>') + '</nav></header>';
  }

  function footer() {
    var cats = D.CATEGORIES.slice(0, 6).map(function (c) { return '<li><a href="vendors.html?category=' + c.slug + '">' + c.name + '</a></li>'; }).join('');
    return '<div class="band thin"></div><footer class="site-footer"><div class="wrap"><div class="footer-grid">' +
      '<div><a class="brand" href="index.html"><span class="brand-mark">' + LOGO + '</span>EventHub</a><p style="color:#9FC1B3;margin-top:16px;max-width:34ch">Find, compare and book trusted event vendors across Zambia — from Lusaka to Livingstone.</p>' +
      '<div class="socials"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><a href="#" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a><a href="https://wa.me/260971234567" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a></div></div>' +
      '<div><h4>Popular categories</h4><ul>' + cats + '</ul></div>' +
      '<div><h4>Plan</h4><ul><li><a href="vendors.html">Find vendors</a></li><li><a href="events.html">Upcoming events</a></li><li><a href="planner.html">Checklist & budget</a></li><li><a href="favourites.html">Saved vendors</a></li></ul></div>' +
      '<div><h4>For businesses</h4><ul><li><a href="vendor-register.html">List your business</a></li><li><a href="vendor-dashboard.html">Vendor dashboard</a></li><li><a href="contact.html">Advertise with us</a></li><li><a href="contact.html">Help & support</a></li></ul></div>' +
      '</div><div class="footer-base"><span>© ' + new Date().getFullYear() + ' EventHub Zambia. Plot 14, Rhodes Park, Lusaka.</span><span><a href="mailto:hello@eventhub.zm">hello@eventhub.zm</a> · +260 97 123 4567</span></div></div></footer>' +
      '<div class="toast" role="status" aria-live="polite"><i class="fa-solid fa-circle-check"></i><span></span></div>' +
      '<div class="lightbox" role="dialog" aria-label="Photo viewer"><img alt=""></div>';
  }

  /* ---------- Favourites ---------- */
  function getFavs() { try { return JSON.parse(localStorage.getItem('eh_favourites') || '[]'); } catch (e) { return []; } }
  function setFavs(l) { try { localStorage.setItem('eh_favourites', JSON.stringify(l)); } catch (e) {} }
  function refreshFavs() {
    var favs = getFavs();
    document.querySelectorAll('.fav-btn[data-id]').forEach(function (b) {
      var on = favs.indexOf(b.dataset.id) > -1;
      b.setAttribute('aria-pressed', on);
      b.setAttribute('aria-label', (on ? 'Remove ' : 'Save ') + b.dataset.name + (on ? ' from saved' : ''));
      b.querySelector('i').className = (on ? 'fa-solid' : 'fa-regular') + ' fa-heart';
    });
    var c = document.querySelector('[data-fav-count]');
    if (c) { c.textContent = favs.length; favs.length ? c.removeAttribute('data-zero') : c.setAttribute('data-zero', ''); }
  }

  /* ---------- Vendor card ---------- */
  function card(v) {
    var cat = D.category(v.cat) || { name: v.cat };
    var tel = (v.phone || '').replace(/[^\d+]/g, '');
    return '<article class="v-card">' +
      '<div class="v-media"><a href="vendor-profile.html?v=' + v.id + '" tabindex="-1"><img loading="lazy" src="' + v.img + '" alt="' + esc(v.name) + '"></a>' +
      (v.featured ? '<span class="v-badge gold"><i class="fa-solid fa-star"></i> Featured</span>' : v.verified ? '<span class="v-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>' : '') +
      '<button class="fav-btn" data-id="' + v.id + '" data-name="' + esc(v.name) + '" aria-pressed="false"><i class="fa-regular fa-heart"></i></button></div>' +
      '<div class="v-body"><span class="v-cat">' + esc(cat.name) + '</span>' +
      '<h3><a href="vendor-profile.html?v=' + v.id + '">' + esc(v.name) + '</a></h3>' +
      '<div class="v-loc"><i class="fa-solid fa-location-dot"></i> ' + esc(v.area) + ', ' + esc(v.city) + (v.km ? ' · ' + v.km + ' km' : '') + '</div>' +
      '<div class="v-meta"><span class="rating"><i class="fa-solid fa-star"></i>' + v.rating.toFixed(1) + ' <small>(' + v.count + ')</small></span><span class="price"><small>from </small>' + kwacha(v.price) + '<small>/' + v.unit + '</small></span></div>' +
      '<div class="v-actions"><a class="btn btn-ghost btn-sm" href="vendor-profile.html?v=' + v.id + '">View profile</a>' +
      '<a class="btn btn-ghost btn-sm btn-icon" href="tel:' + tel + '" aria-label="Call ' + esc(v.name) + '"><i class="fa-solid fa-phone"></i></a>' +
      '<a class="btn btn-wa btn-sm btn-icon" href="https://wa.me/' + tel.replace('+', '') + '?text=' + encodeURIComponent('Hi ' + v.name + ', I found you on EventHub and would like a quote.') + '" target="_blank" rel="noopener" aria-label="WhatsApp ' + esc(v.name) + '"><i class="fa-brands fa-whatsapp"></i></a></div>' +
      '</div></article>';
  }

  /* ---------- Toast ---------- */
  var toastTimer;
  function toast(msg, icon) {
    var t = document.querySelector('.toast'); if (!t) return;
    t.querySelector('span').textContent = msg;
    t.querySelector('i').className = 'fa-solid ' + (icon || 'fa-circle-check');
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, 3200);
  }

  /* ---------- Form validation ---------- */
  function validate(form) {
    var ok = true, first;
    form.querySelectorAll('[required]').forEach(function (el) {
      var f = el.closest('.field'); if (!f) return;
      var bad = el.type === 'checkbox' ? !el.checked : !el.value.trim() || (el.type === 'email' && !/^\S+@\S+\.\S+$/.test(el.value)) || (el.minLength > 0 && el.value.length < el.minLength);
      f.classList.toggle('invalid', bad);
      if (bad) { ok = false; first = first || el; }
    });
    if (first) first.focus();
    return ok;
  }

  window.EH = { esc: esc, kwacha: kwacha, card: card, toast: toast, validate: validate, getFavs: getFavs, setFavs: setFavs, refreshFavs: refreshFavs, page: page };

  document.addEventListener('DOMContentLoaded', function () {
    var h = document.getElementById('site-header'); if (h) h.outerHTML = header();
    var f = document.getElementById('site-footer'); if (f) f.outerHTML = footer();

    var mb = document.querySelector('.menu-btn');
    if (mb) mb.addEventListener('click', function () {
      var n = document.getElementById('mobile-nav'); var open = n.classList.toggle('open');
      mb.setAttribute('aria-expanded', open);
      mb.innerHTML = '<i class="fa-solid ' + (open ? 'fa-xmark' : 'fa-bars') + '"></i>';
    });

    document.querySelectorAll('[data-vendor-grid]').forEach(function (g) {
      var key = g.getAttribute('data-vendor-grid');
      var list = D.VENDORS.filter(function (v) { return key === 'featured' ? v.featured : key === 'all' ? true : v.city === key; });
      var lim = +g.getAttribute('data-limit') || list.length;
      g.innerHTML = list.slice(0, lim).map(card).join('');
    });

    document.body.addEventListener('click', function (e) {
      var b = e.target.closest('.fav-btn[data-id]');
      if (b) {
        e.preventDefault();
        var favs = getFavs(), id = b.dataset.id, i = favs.indexOf(id);
        if (i > -1) { favs.splice(i, 1); toast(b.dataset.name + ' removed from saved', 'fa-heart-crack'); }
        else { favs.push(id); toast(b.dataset.name + ' saved', 'fa-heart'); }
        setFavs(favs); refreshFavs();
        if (window.EH_API && EH_API.isLoggedIn()) EH_API.toggleFavourite(id).catch(function () {});
        document.dispatchEvent(new CustomEvent('eh:favs'));
        return;
      }
      var open = e.target.closest('[data-modal]');
      if (open) { e.preventDefault(); var m = document.querySelector(open.getAttribute('data-modal')); if (m) { m.classList.add('open'); var fi = m.querySelector('input,select,textarea,button:not(.modal-x)'); if (fi) fi.focus(); } return; }
      if (e.target.classList.contains('modal') || e.target.closest('.modal-x,[data-close]')) { var mm = e.target.closest('.modal'); if (mm) mm.classList.remove('open'); return; }
      var zoom = e.target.closest('[data-zoom]');
      if (zoom) { var lb = document.querySelector('.lightbox'); lb.querySelector('img').src = zoom.src.replace(/w=\d+/, 'w=1600'); lb.querySelector('img').alt = zoom.alt; lb.classList.add('open'); return; }
      if (e.target.closest('.lightbox')) { document.querySelector('.lightbox').classList.remove('open'); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') document.querySelectorAll('.modal.open,.lightbox.open').forEach(function (m) { m.classList.remove('open'); });
    });

    /* Tabs: <div role="tablist" data-tabs="name"><button data-tab="panelId"> + panels [data-panel-of="name"] */
    document.querySelectorAll('[data-tabs]').forEach(function (list) {
      var name = list.getAttribute('data-tabs');
      list.querySelectorAll('[data-tab]').forEach(function (btn) {
        btn.setAttribute('role', 'tab');
        btn.addEventListener('click', function () {
          list.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b === btn); });
          document.querySelectorAll('[data-panel-of="' + name + '"]').forEach(function (p) { p.classList.toggle('on', p.id === btn.dataset.tab); p.hidden = p.id !== btn.dataset.tab; });
          if (history.replaceState && list.hasAttribute('data-hash')) history.replaceState(null, '', '#' + btn.dataset.tab);
        });
      });
      var initial = list.hasAttribute('data-hash') && location.hash && list.querySelector('[data-tab="' + location.hash.slice(1) + '"]');
      (initial || list.querySelector('[data-tab]')).click();
    });

    /* Simple forms that only confirm (contact, demo actions) */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.setAttribute('novalidate', '');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validate(form)) return;
        toast(form.getAttribute('data-confirm'));
        var m = form.closest('.modal'); if (m) m.classList.remove('open');
        form.reset();
      });
    });

    refreshFavs();
  });
})();
