// BrandBay front-end behaviour: mobile nav, project filters, lightbox, confirm dialogs, reveal-on-scroll.
(function () {
  document.documentElement.classList.add('js');

  // Mobile navigation (public header and admin sidebar share the same hooks).
  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && nav.classList.contains('open')) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  // Project category filter.
  const filterGroup = document.querySelector('[data-filter-group]');
  const filterTarget = document.querySelector('[data-filter-target]');
  if (filterGroup && filterTarget) {
    filterGroup.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-filter]');
      if (!btn) return;
      const value = btn.dataset.filter;
      filterGroup.querySelectorAll('[data-filter]').forEach((b) => b.classList.toggle('active', b === btn));
      filterTarget.querySelectorAll('[data-category]').forEach((card) => {
        card.hidden = Boolean(value) && card.dataset.category !== value;
      });
    });
  }

  // Lightbox for gallery, certificates and project images.
  const dialog = document.querySelector('[data-lightbox-dialog]');
  if (dialog && typeof dialog.showModal === 'function') {
    const img = dialog.querySelector('[data-lightbox-img]');
    const caption = dialog.querySelector('[data-lightbox-caption]');
    let items = [];
    let index = 0;
    const show = (i) => {
      index = (i + items.length) % items.length;
      const item = items[index];
      img.src = item.dataset.lightbox;
      img.alt = item.dataset.caption || '';
      caption.textContent = item.dataset.caption || '';
    };
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-lightbox]');
      if (!trigger) return;
      const group = trigger.closest('[data-lightbox-group]');
      items = group ? Array.from(group.querySelectorAll('[data-lightbox]')) : [trigger];
      show(items.indexOf(trigger));
      dialog.showModal();
    });
    dialog.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-lightbox-prev]').addEventListener('click', () => show(index - 1));
    dialog.querySelector('[data-lightbox-next]').addEventListener('click', () => show(index + 1));
    dialog.addEventListener('click', (e) => { if (e.target === dialog || e.target.tagName === 'FIGURE') dialog.close(); });
    dialog.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') show(index - 1);
      if (e.key === 'ArrowRight') show(index + 1);
    });
  }

  // Read-only link fields select their whole value for easy copying.
  document.querySelectorAll('[data-select-on-focus]').forEach((el) => el.addEventListener('focus', () => el.select()));

  // Confirm destructive actions.
  document.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) e.preventDefault();
  });

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Headline word-by-word reveal: wraps each word (keeping inner markup such as gold spans).
  document.querySelectorAll('[data-split]').forEach((el) => {
    let i = 0;
    const wrap = (node) => {
      [...node.childNodes].forEach((child) => {
        if (child.nodeType === 3) {
          const frag = document.createDocumentFragment();
          child.textContent.split(/(\s+)/).forEach((part) => {
            if (!part) return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
            const outer = document.createElement('span');
            outer.className = 'word';
            const inner = document.createElement('span');
            inner.style.setProperty('--i', i++);
            inner.textContent = part;
            outer.appendChild(inner);
            frag.appendChild(outer);
          });
          child.replaceWith(frag);
        } else if (child.nodeType === 1) {
          wrap(child);
        }
      });
    };
    wrap(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('split-in')));
  });

  // Staggered scroll reveals.
  document.querySelectorAll('[data-reveal-group]').forEach((group) => {
    group.querySelectorAll('[data-reveal]').forEach((el, i) => el.style.setProperty('--delay', `${Math.min(i, 12) * 80}ms`));
  });
  const revealTargets = document.querySelectorAll('[data-reveal], .section-title');
  if ('IntersectionObserver' in window && !reduceMotion) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -60px 0px', threshold: 0.08 });
    revealTargets.forEach((el) => io.observe(el));
  } else {
    revealTargets.forEach((el) => el.classList.add('is-visible'));
  }

  // Header state, scroll progress, parallax backgrounds and back-to-top, on one rAF-throttled listener.
  const header = document.querySelector('[data-header]');
  const progress = document.querySelector('[data-scroll-progress]');
  const toTop = document.querySelector('[data-to-top]');
  const parallax = reduceMotion ? [] : [...document.querySelectorAll('[data-parallax]')];
  let lastY = window.scrollY;
  let ticking = false;
  const onScroll = () => {
    const y = window.scrollY;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    if (header) {
      header.classList.toggle('scrolled', y > 40);
      header.classList.toggle('hide', y > 400 && y > lastY + 4 && !(nav && nav.classList.contains('open')));
      if (y < lastY - 4) header.classList.remove('hide');
    }
    if (progress) progress.style.transform = `scaleX(${max > 0 ? y / max : 0})`;
    if (toTop) toTop.classList.toggle('show', y > 700);
    parallax.forEach((img) => {
      const box = img.parentElement.getBoundingClientRect();
      if (box.bottom < 0 || box.top > window.innerHeight) return;
      const shift = (box.top + box.height / 2 - window.innerHeight / 2) * -0.08;
      img.style.transform = `translate3d(0, ${shift.toFixed(1)}px, 0)`;
    });
    lastY = y;
    ticking = false;
  };
  window.addEventListener('scroll', () => {
    if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' }));

  // Gold glow follows the pointer across the hero.
  const hero = document.querySelector('.hero-video');
  const glow = hero && hero.querySelector('.hero-glow');
  if (glow && !reduceMotion) {
    hero.addEventListener('pointermove', (e) => {
      const r = hero.getBoundingClientRect();
      glow.style.setProperty('--mx', `${e.clientX - r.left}px`);
      glow.style.setProperty('--my', `${e.clientY - r.top}px`);
    });
  }

  // 3D tilt on cards (pointer devices only).
  if (!reduceMotion && window.matchMedia('(hover: hover)').matches) {
    document.querySelectorAll('.tilt').forEach((card) => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        card.style.setProperty('--ry', `${(x * 8).toFixed(2)}deg`);
        card.style.setProperty('--rx', `${(-y * 8).toFixed(2)}deg`);
      });
      card.addEventListener('pointerleave', () => {
        card.style.setProperty('--ry', '0deg');
        card.style.setProperty('--rx', '0deg');
      });
    });
  }

  // Background videos only play while on screen (saves battery and data); paused for reduced motion.
  const videos = document.querySelectorAll('.bg-video');
  if (reduceMotion) {
    videos.forEach((v) => { v.removeAttribute('autoplay'); v.pause(); });
  } else if ('IntersectionObserver' in window) {
    const vio = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const v = entry.target;
        if (entry.isIntersecting) v.play().catch(() => {});
        else v.pause();
      });
    });
    videos.forEach((v) => vio.observe(v));
  }

  // Fade out before following an internal link, for a smooth page transition.
  if (!reduceMotion) {
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[href]');
      if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (a.target === '_blank' || a.hasAttribute('download')) return;
      const url = new URL(a.href, location.href);
      if (url.origin !== location.origin || url.hash && url.pathname === location.pathname) return;
      if (/^(mailto|tel):/.test(a.getAttribute('href'))) return;
      e.preventDefault();
      document.body.classList.add('leaving');
      setTimeout(() => { location.href = url.href; }, 220);
    });
    // Coming back through the browser cache must not leave the page faded out.
    window.addEventListener('pageshow', (e) => { if (e.persisted) document.body.classList.remove('leaving'); });
  }
})();
