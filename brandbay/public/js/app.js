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

  // Subtle reveal of content blocks as they scroll into view.
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const targets = document.querySelectorAll('.service-card, .project-card, .pillar, .testimonial, .steps li, .product-card');
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -40px 0px' });
    targets.forEach((el) => { el.classList.add('reveal'); io.observe(el); });
  }
})();
