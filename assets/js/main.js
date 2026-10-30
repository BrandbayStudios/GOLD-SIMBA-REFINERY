(function () {
  'use strict';

  var COMPANY_EMAIL = 'brinnahmunsanje2@gmail.com';
  var COMPANY_WHATSAPP = '260963816296';

  var header = document.querySelector('.site-header');
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('nav');
  var toTop = document.querySelector('.to-top');

  // Sticky header + back-to-top
  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    header.classList.toggle('scrolled', y > 40);
    toTop.classList.toggle('show', y > 600);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // Mobile menu
  function setMenu(open) {
    nav.classList.toggle('open', open);
    header.classList.toggle('menu-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }
  toggle.addEventListener('click', function () {
    setMenu(!nav.classList.contains('open'));
  });
  nav.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () { setMenu(false); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setMenu(false);
  });

  // Reveal on scroll
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(function (el, i) {
      el.style.transitionDelay = (i % 4) * 70 + 'ms';
      io.observe(el);
    });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  // Hero parallax
  var heroBg = document.querySelector('.hero-bg');
  if (heroBg && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    window.addEventListener('scroll', function () {
      var y = window.scrollY;
      if (y < window.innerHeight) heroBg.style.transform = 'scale(1.08) translateY(' + y * 0.25 + 'px)';
    }, { passive: true });
  }

  // Supplies filter
  var tabs = document.querySelectorAll('.tab');
  var chips = document.querySelectorAll('.chip-grid li');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var f = tab.dataset.filter;
      tabs.forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('active', on);
        t.setAttribute('aria-selected', String(on));
      });
      chips.forEach(function (c) {
        var show = f === 'all' || c.dataset.cat === f;
        c.classList.toggle('hide', !show);
        c.classList.remove('pop');
        if (show) { void c.offsetWidth; c.classList.add('pop'); }
      });
    });
  });

  // Quote form -> email or WhatsApp
  var form = document.getElementById('quoteForm');
  var msg = form.querySelector('.form-msg');
  var via = 'email';
  form.querySelectorAll('button[type="submit"]').forEach(function (b) {
    b.addEventListener('click', function () { via = b.dataset.via; });
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var ok = true;
    ['name', 'phone', 'message'].forEach(function (id) {
      var el = form.elements[id];
      var bad = !el.value.trim();
      el.classList.toggle('invalid', bad);
      if (bad) ok = false;
    });
    if (!ok) {
      msg.textContent = 'Please fill in your name, phone and requirements.';
      return;
    }
    msg.textContent = '';
    var d = form.elements;
    var body =
      'Quotation request\n\n' +
      'Name: ' + d.name.value.trim() + '\n' +
      (d.company.value.trim() ? 'Company: ' + d.company.value.trim() + '\n' : '') +
      'Phone: ' + d.phone.value.trim() + '\n' +
      'Interested in: ' + d.interest.value + '\n\n' +
      d.message.value.trim();
    if (via === 'whatsapp') {
      window.open('https://wa.me/' + COMPANY_WHATSAPP + '?text=' + encodeURIComponent(body), '_blank', 'noopener');
    } else {
      window.location.href = 'mailto:' + COMPANY_EMAIL +
        '?subject=' + encodeURIComponent('Quotation request - ' + d.interest.value) +
        '&body=' + encodeURIComponent(body);
    }
  });

  // Product quote links preselect the product
  document.querySelectorAll('.product-link').forEach(function (a) {
    a.addEventListener('click', function () {
      var sel = document.getElementById('interest');
      if (sel) sel.value = a.dataset.interest;
    });
  });

  document.getElementById('year').textContent = new Date().getFullYear();
})();
