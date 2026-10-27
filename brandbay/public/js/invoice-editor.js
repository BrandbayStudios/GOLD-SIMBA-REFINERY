// Admin invoice editor: add/remove/reorder lines, pick services and clients, and keep the summary totals live.
// The server recalculates everything on save; these totals are only a preview.
(function () {
  const form = document.querySelector('[data-invoice-editor]');
  if (!form) return;
  const data = JSON.parse(document.getElementById('invoice-data').textContent);
  const lines = form.querySelector('[data-lines]');
  const template = form.querySelector('[data-line-template]');
  const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
  const num = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
  const pct = (v) => Math.min(100, Math.max(0, num(v)));
  const taxRate = (id) => { const t = data.taxes.find((x) => String(x.id) === String(id)); return t ? num(t.rate) : 0; };

  function symbol() {
    const c = data.currencies.find((x) => x.code === form.elements.currency.value);
    return c ? c.symbol : '';
  }
  function money(n) {
    const v = round2(n);
    return `${v < 0 ? '-' : ''}${symbol()}${Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }
  const field = (line, f) => line.querySelector(`[data-f="${f}"]`);

  function recalc() {
    const docDisc = pct(form.elements.discount_percent.value);
    let subtotal = 0, discount = 0, tax = 0;
    lines.querySelectorAll('[data-line]').forEach((line) => {
      const gross = round2(num(field(line, 'qty').value) * num(field(line, 'price').value));
      const after = round2(gross - round2(gross * pct(field(line, 'discount').value) / 100));
      const dd = round2(after * docDisc / 100);
      subtotal += after;
      discount += dd;
      tax += round2((after - dd) * taxRate(field(line, 'tax').value) / 100);
      line.querySelector('[data-line-total]').textContent = money(after);
    });
    const taxable = round2(subtotal - discount);
    const total = round2(taxable + tax);
    const whtSel = form.elements.withholding_id;
    const wht = whtSel && whtSel.value ? round2(taxable * taxRate(whtSel.value) / 100) : 0;
    const payable = round2(total - wht);
    // Receipts have no withholding or deposit fields.
    const showDeposit = Boolean(form.elements.show_deposit && form.elements.show_deposit.checked);
    const out = {
      subtotal, discount: -discount, tax, total, withholding: -wht, payable,
      deposit: showDeposit ? round2(payable * pct(form.elements.deposit_percent.value) / 100) : 0,
    };
    for (const [k, v] of Object.entries(out)) {
      const cell = form.querySelector(`[data-t="${k}"]`);
      if (cell) cell.textContent = money(v);
    }
    if (form.elements.deposit_percent) form.elements.deposit_percent.disabled = !showDeposit;
  }

  function addLine(values) {
    const node = template.content.firstElementChild.cloneNode(true);
    if (values) {
      field(node, 'category').value = values.category || '';
      node.querySelector('[data-cat-label]').textContent = values.category || 'Custom item';
      field(node, 'name').value = values.name || '';
      field(node, 'unit').value = values.unit || '';
      field(node, 'price').value = values.price ?? 0;
    }
    const taxSelect = field(node, 'tax');
    const taxId = values && values.taxId ? values.taxId : data.defaultTaxId;
    if (taxId) taxSelect.value = String(taxId);
    lines.appendChild(node);
    recalc();
    field(node, 'name').focus();
  }

  form.querySelector('[data-service-picker]').addEventListener('change', (e) => {
    const item = data.catalog.find((c) => String(c.id) === e.target.value);
    if (item) {
      // Replace the single untouched starter line rather than leaving it empty.
      const all = lines.querySelectorAll('[data-line]');
      if (all.length === 1 && !field(all[0], 'name').value && !num(field(all[0], 'price').value)) all[0].remove();
      addLine(item);
    }
    e.target.value = '';
  });

  form.querySelector('[data-add-line]').addEventListener('click', () => addLine());

  lines.addEventListener('click', (e) => {
    const line = e.target.closest('[data-line]');
    if (!line) return;
    if (e.target.closest('[data-remove]')) {
      line.remove();
    } else if (e.target.closest('[data-move]')) {
      const dir = Number(e.target.closest('[data-move]').dataset.move);
      const sibling = dir < 0 ? line.previousElementSibling : line.nextElementSibling;
      if (sibling) dir < 0 ? sibling.before(line) : sibling.after(line);
    }
    recalc();
  });

  const picker = form.querySelector('[data-client-picker]');
  picker.addEventListener('change', () => {
    const c = data.clients.find((x) => String(x.id) === picker.value);
    if (!c) return;
    form.elements.client_name.value = c.name || '';
    form.elements.client_company.value = c.company || '';
    form.elements.client_email.value = c.email || '';
    form.elements.client_phone.value = c.phone || '';
  });

  const typeSelect = form.querySelector('[data-doc-type]');
  if (typeSelect) {
    typeSelect.addEventListener('change', () => {
      const label = form.querySelector('[data-due-label]');
      if (label) label.textContent = typeSelect.value === 'quotation' ? 'Valid until' : 'Due date';
      // The suggested number belongs to the old type; the server assigns the right one when it is left blank.
      if (/^(INV|QUO|PRO)-\d{4}-\d{4,}$/.test(form.elements.number.value)) form.elements.number.value = '';
      form.elements.number.required = false;
      form.elements.number.placeholder = 'Assigned automatically';
      const status = form.elements.status;
      const opts = typeSelect.value === 'quotation' ? ['draft', 'sent', 'accepted', 'declined', 'cancelled'] : ['draft', 'sent', 'cancelled'];
      const labels = { draft: 'Draft', sent: 'Sent', accepted: 'Accepted', declined: 'Declined', cancelled: 'Cancelled' };
      status.innerHTML = opts.map((o) => `<option value="${o}">${labels[o]}</option>`).join('');
    });
  }

  form.addEventListener('input', recalc);
  form.addEventListener('change', recalc);
  form.addEventListener('submit', (e) => {
    if (!lines.querySelector('[data-line]')) {
      e.preventDefault();
      alert('Add at least one service or item.');
    }
  });
  recalc();
})();
