/* ==========================================================================
   EventHub — shared front-end behaviour
   Static demo only: uses localStorage to fake persistence (favourites,
   checklist, "logged in" state). No backend calls are made.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Mobile nav ---------- */
  var navToggle = document.querySelector('.nav-toggle');
  var navLinks = document.querySelector('.nav-links');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      navLinks.classList.toggle('open');
      var icon = navToggle.querySelector('i');
      if (icon) icon.className = navLinks.classList.contains('open') ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
    });
  }
  document.querySelectorAll('.has-dropdown > a').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (window.innerWidth <= 860) {
        e.preventDefault();
        link.parentElement.classList.toggle('open');
      }
    });
  });

  /* ---------- Back to top ---------- */
  var backTop = document.querySelector('.back-top');
  if (backTop) {
    window.addEventListener('scroll', function () {
      backTop.classList.toggle('show', window.scrollY > 500);
    });
    backTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------- Toast helper ---------- */
  window.EH_toast = function (message, icon) {
    var toast = document.getElementById('eh-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'toast';
      toast.id = 'eh-toast';
      document.body.appendChild(toast);
    }
    toast.innerHTML = '<i class="fa-solid ' + (icon || 'fa-circle-check') + '"></i><span></span>';
    toast.querySelector('span').textContent = message;
    toast.classList.add('show');
    clearTimeout(window._ehToastTimer);
    window._ehToastTimer = setTimeout(function () { toast.classList.remove('show'); }, 3200);
  };

  /* ---------- Favourites (localStorage) ---------- */
  function getFavs() {
    try { return JSON.parse(localStorage.getItem('eh_favourites') || '[]'); }
    catch (e) { return []; }
  }
  function setFavs(list) { localStorage.setItem('eh_favourites', JSON.stringify(list)); }

  function refreshFavButtons() {
    var favs = getFavs();
    document.querySelectorAll('.fav-btn[data-vendor-id]').forEach(function (btn) {
      var id = btn.getAttribute('data-vendor-id');
      btn.classList.toggle('active', favs.indexOf(id) > -1);
    });
    var badge = document.querySelector('[data-fav-count]');
    if (badge) badge.textContent = favs.length;
  }
  window.EH_refreshFavButtons = refreshFavButtons;

  // Delegated (not per-element) so favourite buttons on cards injected later
  // by API-backed pages (e.g. vendors.html live search results) still work.
  document.body.addEventListener('click', function (e) {
    var btn = e.target.closest('.fav-btn[data-vendor-id]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var id = btn.getAttribute('data-vendor-id');
    var name = btn.getAttribute('data-vendor-name') || 'Vendor';
    var favs = getFavs();
    var idx = favs.indexOf(id);
    if (idx > -1) {
      favs.splice(idx, 1);
      EH_toast(name + ' removed from favourites', 'fa-heart-crack');
    } else {
      favs.push(id);
      EH_toast(name + ' saved to favourites', 'fa-heart');
    }
    setFavs(favs);
    refreshFavButtons();

    // Real API ids are numeric (v.id from the backend); the bundled static
    // demo cards use placeholder ids like "v1" which aren't real vendor
    // rows, so only sync to the server when it's an actual id and someone
    // is logged in — otherwise this stays a local-only favourite.
    if (window.EH_API && EH_API.isLoggedIn() && /^\d+$/.test(id)) {
      EH_API.toggleFavourite(id).catch(function () {});
    }
  });
  refreshFavButtons();

  /* ---------- Homepage hero search -> redirects to vendors.html ---------- */
  var heroSearch = document.getElementById('hero-search-form');
  if (heroSearch) {
    heroSearch.addEventListener('submit', function (e) {
      e.preventDefault();
      var q = document.getElementById('hero-search-q').value.trim();
      var loc = document.getElementById('hero-search-loc').value;
      var params = new URLSearchParams();
      if (q) params.set('q', q);
      if (loc) params.set('location', loc);
      window.location.href = 'vendors.html' + (params.toString() ? '?' + params.toString() : '');
    });
  }

  var nearMeBtn = document.getElementById('near-me-btn');
  if (nearMeBtn) {
    nearMeBtn.addEventListener('click', function () {
      var locInput = document.getElementById('hero-search-loc');
      if (!navigator.geolocation) {
        EH_toast('Geolocation not supported on this device', 'fa-triangle-exclamation');
        return;
      }
      EH_toast('Finding your location…', 'fa-location-crosshairs');
      navigator.geolocation.getCurrentPosition(function () {
        if (locInput) locInput.value = 'near-me';
        EH_toast('Location found — showing vendors near you', 'fa-location-dot');
      }, function () {
        EH_toast('Could not access location. Choose a city instead.', 'fa-triangle-exclamation');
      });
    });
  }

  /* Pre-fill vendors.html search box from query params */
  if (window.location.pathname.indexOf('vendors.html') > -1) {
    var params = new URLSearchParams(window.location.search);
    var qInput = document.getElementById('browse-search-q');
    var catFilter = params.get('category');
    if (qInput && params.get('q')) qInput.value = params.get('q');
    if (catFilter) {
      document.querySelectorAll('.filter-option input[data-cat]').forEach(function (cb) {
        if (cb.getAttribute('data-cat').toLowerCase() === catFilter.toLowerCase()) cb.checked = true;
      });
    }
  }

  /* ---------- Auth tabs (login/register) ---------- */
  document.querySelectorAll('.auth-tabs').forEach(function (tabs) {
    tabs.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        tabs.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var target = btn.getAttribute('data-target');
        document.querySelectorAll('.auth-panel').forEach(function (p) { p.style.display = 'none'; });
        var panel = document.querySelector(target);
        if (panel) panel.style.display = 'block';
      });
    });
  });

  /* ---------- Generic tab system: profile tabs, dashboard tabs ---------- */
  document.querySelectorAll('[data-tab-group]').forEach(function (group) {
    var groupName = group.getAttribute('data-tab-group');
    group.querySelectorAll('[data-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        group.querySelectorAll('[data-tab]').forEach(function (b) {
          b.classList.remove('active');
          if (b.parentElement && b.parentElement.tagName === 'LI') b.parentElement.classList.remove('active');
        });
        btn.classList.add('active');
        if (btn.parentElement && btn.parentElement.tagName === 'LI') btn.parentElement.classList.add('active');
        var targetId = btn.getAttribute('data-tab');
        document.querySelectorAll('[data-tab-panel-group="' + groupName + '"]').forEach(function (p) {
          p.classList.toggle('active', p.id === targetId);
        });
      });
    });
  });

  /* ---------- Multi-step vendor registration form ---------- */
  var stepForm = document.getElementById('vendor-register-form');
  if (stepForm) {
    var steps = Array.prototype.slice.call(stepForm.querySelectorAll('.step-panel'));
    var indicators = Array.prototype.slice.call(document.querySelectorAll('.form-steps .fstep'));
    var current = 0;

    function showStep(i) {
      steps.forEach(function (s, idx) { s.classList.toggle('active', idx === i); });
      indicators.forEach(function (ind, idx) {
        ind.classList.toggle('active', idx === i);
        ind.classList.toggle('done', idx < i);
      });
      window.scrollTo({ top: stepForm.offsetTop - 120, behavior: 'smooth' });
    }
    showStep(current);

    stepForm.querySelectorAll('.next-step').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (current < steps.length - 1) { current++; showStep(current); }
      });
    });
    stepForm.querySelectorAll('.prev-step').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (current > 0) { current--; showStep(current); }
      });
    });
    // Actual submission (API call + success modal) is wired per-page, since it
    // needs page-specific field collection — see vendor-register.html.
  }

  /* ---------- Upload box triggers hidden file input & previews name ---------- */
  document.querySelectorAll('.upload-box').forEach(function (box) {
    var input = box.querySelector('input[type=file]');
    if (!input) return;
    box.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
      var label = box.querySelector('.upload-label');
      if (label && input.files.length) {
        label.textContent = input.files.length + ' file(s) selected';
      }
    });
  });

  /* ---------- Modals (generic open/close via data attributes) ---------- */
  document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      var modal = document.querySelector(trigger.getAttribute('data-modal-open'));
      if (modal) modal.classList.add('open');
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(function (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) modal.classList.remove('open');
    });
    modal.querySelectorAll('.modal-close, [data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { modal.classList.remove('open'); });
    });
  });

  /* ---------- Quote request / contact forms: fake submit ---------- */
  document.querySelectorAll('form[data-fake-submit]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = form.getAttribute('data-success-message') || 'Submitted successfully!';
      EH_toast(msg, 'fa-circle-check');
      var modal = form.closest('.modal-overlay');
      if (modal) setTimeout(function () { modal.classList.remove('open'); form.reset(); }, 900);
      else form.reset();
    });
  });

  /* ---------- Newsletter form ---------- */
  document.querySelectorAll('.newsletter-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      EH_toast('Thanks for subscribing to EventHub updates!', 'fa-envelope-circle-check');
      form.reset();
    });
  });

  /* ---------- Star rating range display on filter ---------- */
  var priceRange = document.getElementById('price-range');
  if (priceRange) {
    var priceOut = document.getElementById('price-range-val');
    priceRange.addEventListener('input', function () {
      if (priceOut) priceOut.textContent = 'K' + Number(priceRange.value).toLocaleString();
    });
  }

  /* ---------- Vendor listing filter interaction (client-side demo) ---------- */
  var applyFiltersBtn = document.getElementById('apply-filters');
  if (applyFiltersBtn) {
    applyFiltersBtn.addEventListener('click', function () {
      var checkedCats = Array.prototype.slice.call(document.querySelectorAll('.filter-option input[data-cat]:checked')).map(function (c) { return c.getAttribute('data-cat'); });
      var cards = document.querySelectorAll('.vendor-grid .vendor-card');
      var visible = 0;
      cards.forEach(function (card) {
        var cardCat = card.getAttribute('data-cat');
        var show = checkedCats.length === 0 || checkedCats.indexOf(cardCat) > -1;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      var countEl = document.getElementById('results-count');
      if (countEl) countEl.textContent = visible;
      EH_toast('Showing ' + visible + ' vendors', 'fa-filter');
    });
  }

  /* ---------- Sort dropdown (demo re-order by rating/price/name) ---------- */
  var sortSelect = document.getElementById('sort-select');
  if (sortSelect) {
    sortSelect.addEventListener('change', function () {
      var grid = document.querySelector('.vendor-grid');
      if (!grid) return;
      var cards = Array.prototype.slice.call(grid.children);
      var key = sortSelect.value;
      cards.sort(function (a, b) {
        if (key === 'rating') return parseFloat(b.getAttribute('data-rating')) - parseFloat(a.getAttribute('data-rating'));
        if (key === 'price-low') return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
        if (key === 'price-high') return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
        return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
      });
      cards.forEach(function (c) { grid.appendChild(c); });
    });
  }

  /* ---------- Vendor profile gallery lightbox (simple) ---------- */
  var lightbox = document.getElementById('lightbox');
  if (lightbox) {
    document.querySelectorAll('.gallery-grid img').forEach(function (img) {
      img.addEventListener('click', function () {
        lightbox.querySelector('img').src = img.src;
        lightbox.classList.add('open');
      });
    });
  }

  /* ---------- Event planning checklist ---------- */
  var checklistRoot = document.getElementById('checklist-root');
  if (checklistRoot) {
    var STORAGE_KEY = 'eh_checklist_v1';
    var defaultData = {
      'Venue & Logistics': ['Book event venue', 'Confirm guest count', 'Arrange parking', 'Book tent & chairs'],
      'Food & Drink': ['Hire caterer', 'Order cake', 'Arrange drinks & bar service'],
      'Look & Style': ['Book makeup artist', 'Book hairdresser', 'Order outfits from tailor'],
      'Entertainment': ['Book MC', 'Book DJ / sound & lighting', 'Hire photographer', 'Hire videographer'],
      'Extras': ['Arrange car hire & decoration', 'Book choreographer', 'Confirm wedding planner walkthrough']
    };

    function loadData() {
      try {
        var saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
        if (saved) return saved;
      } catch (e) {}
      var seeded = {};
      Object.keys(defaultData).forEach(function (cat) {
        seeded[cat] = defaultData[cat].map(function (t) { return { text: t, done: false }; });
      });
      return seeded;
    }
    var data = loadData();

    function save() { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); }

    function render() {
      checklistRoot.innerHTML = '';
      var totalItems = 0, doneItems = 0;
      Object.keys(data).forEach(function (cat) {
        totalItems += data[cat].length;
        doneItems += data[cat].filter(function (i) { return i.done; }).length;

        var card = document.createElement('div');
        card.className = 'checklist-card';
        var heading = document.createElement('h4');
        heading.innerHTML = '<i class="fa-solid fa-list-check text-gold"></i> ' + cat;
        card.appendChild(heading);

        data[cat].forEach(function (item, idx) {
          var row = document.createElement('div');
          row.className = 'checklist-item' + (item.done ? ' done' : '');
          var checkboxId = cat.replace(/\s/g, '-') + '-' + idx;
          row.innerHTML =
            '<input type="checkbox" id="' + checkboxId + '" ' + (item.done ? 'checked' : '') + '>' +
            '<label for="' + checkboxId + '">' + item.text + '</label>' +
            '<span class="cat">' + cat + '</span>';
          row.querySelector('input').addEventListener('change', function (e) {
            data[cat][idx].done = e.target.checked;
            save();
            render();
          });
          card.appendChild(row);
        });

        var addRow = document.createElement('div');
        addRow.className = 'add-item-row';
        addRow.innerHTML =
          '<input type="text" class="form-control" placeholder="Add a task…">' +
          '<button class="btn btn-dark btn-sm" type="button"><i class="fa-solid fa-plus"></i></button>';
        addRow.querySelector('button').addEventListener('click', function () {
          var input = addRow.querySelector('input');
          if (input.value.trim()) {
            data[cat].push({ text: input.value.trim(), done: false });
            save();
            render();
          }
        });
        card.appendChild(addRow);
        checklistRoot.appendChild(card);
      });

      var progressBar = document.getElementById('checklist-progress');
      var progressText = document.getElementById('checklist-progress-text');
      var pct = totalItems ? Math.round((doneItems / totalItems) * 100) : 0;
      if (progressBar) progressBar.style.width = pct + '%';
      if (progressText) progressText.textContent = doneItems + ' of ' + totalItems + ' tasks complete (' + pct + '%)';
    }
    render();

    var resetBtn = document.getElementById('checklist-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        localStorage.removeItem(STORAGE_KEY);
        data = loadData();
        render();
        EH_toast('Checklist reset', 'fa-arrow-rotate-left');
      });
    }
  }

  /* ---------- Admin/vendor dashboard: approve/reject demo actions ---------- */
  document.querySelectorAll('[data-row-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('tr');
      if (!row) return;
      var action = btn.getAttribute('data-row-action');
      var pill = row.querySelector('.status-pill');
      if (action === 'approve' && pill) {
        pill.textContent = 'Approved';
        pill.className = 'status-pill approved';
        EH_toast('Vendor approved and now publicly listed', 'fa-circle-check');
      } else if (action === 'reject' && pill) {
        pill.textContent = 'Rejected';
        pill.className = 'status-pill rejected';
        EH_toast('Vendor application rejected', 'fa-ban');
      } else if (action === 'delete') {
        row.style.opacity = '0';
        setTimeout(function () { row.remove(); }, 250);
        EH_toast('Item removed', 'fa-trash');
      }
    });
  });

  /* ---------- Availability calendar toggle (vendor dashboard) ---------- */
  document.querySelectorAll('.avail-day').forEach(function (day) {
    day.addEventListener('click', function () {
      day.classList.toggle('on');
      day.classList.toggle('off');
    });
  });

  /* ---------- Set active nav link based on current page ---------- */
  var path = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links > li[data-page]').forEach(function (li) {
    li.classList.toggle('active', li.getAttribute('data-page') === path);
  });

  /* ---------- Simulated "logged in" state toggle for demo purposes ---------- */
  document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var redirect = form.getAttribute('data-redirect') || 'index.html';
      EH_toast('Welcome to EventHub!', 'fa-champagne-glasses');
      setTimeout(function () { window.location.href = redirect; }, 700);
    });
  });

});
