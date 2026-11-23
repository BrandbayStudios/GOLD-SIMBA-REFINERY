// Instant quote estimator: live total and summary. The server re-prices everything on submit.
(function () {
  const form = document.querySelector('[data-quote]');
  if (!form) return;
  const items = [...form.querySelectorAll('[data-item]')];
  const totalEl = form.querySelector('[data-total]');
  const linesEl = form.querySelector('[data-lines]');
  const hint = form.querySelector('[data-package-hint]');
  const packages = JSON.parse(document.getElementById('quote-packages').textContent || '[]');
  const money = (n) => `K${Math.round(n).toLocaleString('en-US')}`;

  function update() {
    let total = 0;
    linesEl.textContent = '';
    for (const el of items) {
      const box = el.querySelector('input[type="checkbox"]');
      const qtyInput = el.querySelector('[data-qty]');
      el.classList.toggle('is-on', box.checked);
      if (!box.checked) continue;
      const qty = qtyInput ? Math.min(50, Math.max(1, parseInt(qtyInput.value, 10) || 1)) : 1;
      const amount = Number(el.dataset.price) * qty;
      total += amount;
      const li = document.createElement('li');
      const name = document.createElement('span');
      name.textContent = el.dataset.name + (qty > 1 ? ` × ${qty}` : '');
      const price = document.createElement('strong');
      price.textContent = money(amount);
      li.append(name, price);
      linesEl.append(li);
    }
    if (!linesEl.children.length) {
      const li = document.createElement('li');
      li.className = 'muted';
      li.textContent = 'Tick the services you need.';
      linesEl.append(li);
    }
    totalEl.textContent = money(total);
    totalEl.classList.remove('bump');
    void totalEl.offsetWidth; // restart the animation
    totalEl.classList.add('bump');
    // Once the selection costs as much as the cheapest package, mention that bundles can work out cheaper.
    const cheapest = packages.reduce((min, p) => (min && min.price <= p.price ? min : p), null);
    if (cheapest && total >= cheapest.price) {
      hint.hidden = false;
      hint.textContent = 'Tip: bundles can work out cheaper. ';
      const a = document.createElement('a');
      a.href = '/packages';
      a.textContent = `Compare packages from ${money(cheapest.price)}`;
      hint.append(a);
    } else {
      hint.hidden = true;
    }
  }

  form.addEventListener('change', update);
  form.addEventListener('input', (e) => { if (e.target.matches('[data-qty]')) update(); });
  // Typing a quantity ticks its item.
  form.addEventListener('focusin', (e) => {
    if (e.target.matches('[data-qty]')) e.target.closest('[data-item]').querySelector('input[type="checkbox"]').checked = true;
  });
  update();
})();
