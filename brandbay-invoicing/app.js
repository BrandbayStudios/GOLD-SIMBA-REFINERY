/* BrandBay Invoicing — a self-contained invoicing app (HTML/CSS/JS, data stored in localStorage). */
(function () {
  'use strict';

  const STORE_KEY = 'brandbay-invoicing-v1';

  // ---------------------------------------------------------------- defaults

  const SERVICES = [
    ['Graphic Design & Brand Identity', ['Logo Design', 'Brand Strategy', 'Corporate Branding', 'Product Branding', 'Visual Identity Systems', 'Brand Naming']],
    ['Digital Advertising & Marketing', ['Search Engine Optimization (SEO)', 'Social Media Marketing', 'Email Marketing', 'Content Strategy', 'Paid Campaign Management']],
    ['Technological Solutions', ['Website & App Design', 'E-commerce Platforms', 'EPOS Setup', 'IT Hardware & System Support', 'Digital Infrastructure Consulting']],
    ['Service & Event Branding', ['Company Profile Design', 'Corporate Documents', 'Event Posters & Flyers', 'Pull-Up Banners', 'Motion Graphics']],
    ['Compliance & Business Registration', ['Business Registration (PACRA)', 'ZPPA Registration', 'ZRA Tax Registration', 'Business Plans & Proposals']],
    ['Printing & Artistry Solutions', ['Business Cards', 'Flyers & Posters', 'Pull-Up Banners', 'Brochures & Company Profiles', 'Stickers & Labels', 'Event Branding Materials', 'Branded Documents & Stationery']],
  ];

  const DOC_TYPES = {
    invoice: { label: 'Invoice', prefix: 'INV' },
    quotation: { label: 'Quotation', prefix: 'QUO' },
    proforma: { label: 'Proforma Invoice', prefix: 'PRO' },
    receipt: { label: 'Receipt', prefix: 'REC' },
  };

  const STATUSES = { draft: 'Draft', sent: 'Sent', partial: 'Partially paid', paid: 'Paid', overdue: 'Overdue', accepted: 'Accepted', declined: 'Declined', cancelled: 'Cancelled' };
  const PAYMENT_METHODS = ['Mobile Money', 'Bank transfer', 'Cash', 'Cheque', 'Card'];

  function defaultData() {
    let n = 0;
    const catalog = [];
    for (const [category, items] of SERVICES) {
      for (const name of items) catalog.push({ id: uid(), category, name, price: 0, unit: category.startsWith('Printing') ? 'per unit' : 'per project', taxId: 'vat', sort: n++ });
    }
    return {
      version: 1,
      settings: {
        company: {
          name: 'BrandBay Innovation Studios',
          tagline: 'Brand | Build | Boost',
          address1: 'Cairo Road, Lusaka, Zambia',
          address2: 'Mpongwe, Copperbelt Zambia',
          phone: '+260972015689',
          email: 'info@brandbaystudio.online',
          website: 'brandbaystudios.online',
          tpin: '',
          logo: '',
        },
        defaultCurrency: 'ZMW',
        currencies: [
          { code: 'ZMW', symbol: 'K', name: 'Zambian Kwacha', rate: 1 },
          { code: 'USD', symbol: '$', name: 'US Dollar', rate: null },
          { code: 'ZAR', symbol: 'R', name: 'South African Rand', rate: null },
          { code: 'GBP', symbol: '£', name: 'British Pound', rate: null },
          { code: 'EUR', symbol: '€', name: 'Euro', rate: null },
        ],
        // kind "add" is charged on top of the line; kind "withhold" is deducted from the amount payable.
        taxes: [
          { id: 'vat', name: 'VAT (standard rate)', rate: 16, kind: 'add' },
          { id: 'zero', name: 'VAT zero-rated', rate: 0, kind: 'add' },
          { id: 'exempt', name: 'VAT exempt', rate: 0, kind: 'add' },
          { id: 'tot', name: 'Turnover Tax', rate: 5, kind: 'add' },
          { id: 'none', name: 'No tax', rate: 0, kind: 'add' },
          { id: 'wht', name: 'Withholding Tax', rate: 15, kind: 'withhold' },
        ],
        defaultTaxId: 'vat',
        banks: [
          { id: uid(), type: 'bank', label: 'Bank account', bankName: '', accountName: 'BrandBay Innovation Studios', accountNumber: '', branch: '', branchCode: '', swift: '', currency: 'ZMW', active: true },
          { id: uid(), type: 'mobile', label: 'MTN Mobile Money', provider: 'MTN MoMo', accountName: 'BrandBay Innovation Studios', accountNumber: '', active: true },
          { id: uid(), type: 'mobile', label: 'Airtel Money', provider: 'Airtel Money', accountName: 'BrandBay Innovation Studios', accountNumber: '', active: false },
        ],
        numbering: { invoice: 1, quotation: 1, proforma: 1, receipt: 1 },
        dueDays: 14,
        validDays: 30,
        depositPercent: 70,
        terms: 'Payment structure: 70% upfront deposit and 30% upon completion. Printing jobs may require full upfront payment.\nEditable/source files are provided on request after full payment.\nPost-delivery changes are treated as a new request.',
        footer: 'Thank you for choosing BrandBay Innovation Studios.',
      },
      catalog,
      clients: [],
      docs: [],
    };
  }

  // ---------------------------------------------------------------- state

  let data = load();

  function load() {
    try {
      const raw = localStorage.getItem(STORE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && parsed.settings && Array.isArray(parsed.docs)) return normalize(parsed);
      }
    } catch (e) { /* fall through to defaults */ }
    return defaultData();
  }

  // Older saves have payments without ids; receipts need them to link to a payment.
  function normalize(d) {
    d.docs.forEach((x) => { (x.payments || []).forEach((p) => { if (!p.id) p.id = uid(); }); });
    return d;
  }

  function save() {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify(data));
      return true;
    } catch (e) {
      toast('Could not save — browser storage is full or blocked. Export a backup from Settings.', true);
      return false;
    }
  }

  // ---------------------------------------------------------------- helpers

  function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }
  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }
  function nl2br(v) { return esc(v).replace(/\n/g, '<br>'); }
  function round2(n) { return Math.round((Number(n) + Number.EPSILON) * 100) / 100; }
  function num(v) { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; }
  function today() { const d = new Date(); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); }
  function addDays(iso, days) { const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + Number(days || 0)); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); }
  function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso + 'T00:00:00');
    return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function currency(code) { return data.settings.currencies.find((c) => c.code === code) || data.settings.currencies[0]; }
  function money(amount, code) {
    const c = currency(code);
    const n = round2(amount);
    const s = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (n < 0 ? '-' : '') + (c ? c.symbol : '') + s;
  }
  function tax(id) { return data.settings.taxes.find((t) => t.id === id); }
  function client(id) { return data.clients.find((c) => c.id === id); }
  function doc(id) { return data.docs.find((d) => d.id === id); }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  let toastTimer;
  function toast(msg, isError) {
    const el = $('#toast');
    el.textContent = msg;
    el.className = 'toast show' + (isError ? ' error' : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.className = 'toast'; }, 3200);
  }

  function nextNumber(type) {
    const year = new Date().getFullYear();
    const n = data.settings.numbering[type] || 1;
    return `${DOC_TYPES[type].prefix}-${year}-${String(n).padStart(4, '0')}`;
  }

  // ---------------------------------------------------------------- calculations

  function totals(d) {
    const lines = d.items.map((it) => {
      const gross = round2(num(it.qty) * num(it.price));
      const lineDiscount = round2(gross * Math.min(100, Math.max(0, num(it.discount))) / 100);
      const afterLine = gross - lineDiscount;
      // The invoice-level discount is spread across lines so tax is charged on the discounted amount.
      const docDiscount = round2(afterLine * Math.min(100, Math.max(0, num(d.discount))) / 100);
      const net = round2(afterLine - docDiscount);
      const t = tax(it.taxId);
      const taxAmt = t && t.kind === 'add' ? round2(net * num(t.rate) / 100) : 0;
      return { gross, lineDiscount, docDiscount, net, taxAmt, tax: t };
    });
    const subtotal = round2(lines.reduce((s, l) => s + l.gross - l.lineDiscount, 0));
    const discount = round2(lines.reduce((s, l) => s + l.docDiscount, 0));
    const taxable = round2(subtotal - discount);
    const taxGroups = {};
    lines.forEach((l) => {
      if (!l.tax || l.tax.kind !== 'add') return;
      const key = l.tax.id;
      taxGroups[key] = taxGroups[key] || { name: l.tax.name, rate: l.tax.rate, base: 0, amount: 0 };
      taxGroups[key].base = round2(taxGroups[key].base + l.net);
      taxGroups[key].amount = round2(taxGroups[key].amount + l.taxAmt);
    });
    const taxTotal = round2(Object.values(taxGroups).reduce((s, g) => s + g.amount, 0));
    const total = round2(taxable + taxTotal);
    const wht = d.withholdingId ? tax(d.withholdingId) : null;
    const withholding = wht ? round2(taxable * num(wht.rate) / 100) : 0;
    const payable = round2(total - withholding);
    const paid = round2((d.payments || []).reduce((s, p) => s + num(p.amount), 0));
    const balance = round2(payable - paid);
    const deposit = round2(payable * num(d.depositPercent) / 100);
    return { lines, subtotal, discount, taxable, taxGroups: Object.values(taxGroups), taxTotal, total, wht, withholding, payable, paid, balance, deposit };
  }

  // Status shown in lists: payments and due dates override the stored status for invoices.
  function effectiveStatus(d) {
    if (d.status === 'cancelled' || d.status === 'draft') return d.status;
    if (d.type === 'invoice' || d.type === 'proforma') {
      const t = totals(d);
      if (t.paid > 0 && t.balance <= 0.004) return 'paid';
      if (t.paid > 0) return d.dueDate && d.dueDate < today() ? 'overdue' : 'partial';
      if (d.type === 'invoice' && d.dueDate && d.dueDate < today()) return 'overdue';
    }
    if (d.type === 'receipt') return 'paid';
    return d.status;
  }

  // ---------------------------------------------------------------- receipts

  // A receipt issued from an invoice payment mirrors that payment and is changed through the invoice.
  function isLinkedReceipt(d) { return d.type === 'receipt' && Boolean(d.sourceId); }

  // What a receipt acknowledges: the amount received and, when linked, where the invoice stands after that payment.
  function receiptDetails(r) {
    const out = { amount: totals(r).paid, invoice: null };
    const src = r.sourceId ? doc(r.sourceId) : null;
    if (!src || (src.type !== 'invoice' && src.type !== 'proforma')) return out;
    const pays = (src.payments || []).filter((p) => num(p.amount) > 0);
    const ordered = pays.map((p, i) => ({ p, i })).sort((a, b) => (a.p.date || '').localeCompare(b.p.date || '') || a.i - b.i).map((x) => x.p);
    const at = ordered.findIndex((p) => p.receiptId === r.id);
    // Paid to date counts every payment up to and including the one this receipt is for.
    const upTo = at >= 0 ? ordered.slice(0, at + 1) : ordered;
    const paidToDate = round2(upTo.reduce((sum, p) => sum + num(p.amount), 0));
    const total = totals(src).payable;
    out.invoice = { id: src.id, number: src.number, label: DOC_TYPES[src.type].label, total, paidToDate, balance: round2(Math.max(0, total - paidToDate)) };
    return out;
  }

  function issueReceipt(src, payment) {
    const r = newDoc('receipt', src);
    Object.assign(r, { sourceId: src.id, reference: src.number, issueDate: payment.date || today(), notes: '' });
    r.payments = [{ id: uid(), date: payment.date, amount: num(payment.amount), method: payment.method, ref: payment.ref || '' }];
    commitNumber(r);
    data.docs.push(r);
    payment.receiptId = r.id;
    return r;
  }

  // Keeps an invoice's receipts in step with its payments: edited payments update their receipt, removed ones void it.
  function syncReceipts(src, previousPayments) {
    const current = new Map((src.payments || []).map((p) => [p.id, p]));
    for (const old of previousPayments || []) {
      if (!old.receiptId) continue;
      const r = doc(old.receiptId);
      if (!r) continue;
      const p = current.get(old.id);
      if (!p) { r.status = 'cancelled'; continue; }
      p.receiptId = r.id;
      r.payments = [{ id: (r.payments[0] && r.payments[0].id) || uid(), date: p.date, amount: num(p.amount), method: p.method, ref: p.ref || '' }];
      r.issueDate = p.date || r.issueDate;
      r.reference = src.number;
      r.currency = src.currency;
    }
  }

  // ---------------------------------------------------------------- document factory

  function newDoc(type, fromDoc) {
    const s = data.settings;
    const d = {
      id: uid(),
      type,
      number: nextNumber(type),
      status: 'draft',
      clientId: fromDoc ? fromDoc.clientId : '',
      issueDate: today(),
      dueDate: type === 'receipt' ? '' : addDays(today(), type === 'quotation' ? s.validDays : s.dueDays),
      currency: fromDoc ? fromDoc.currency : s.defaultCurrency,
      reference: fromDoc ? fromDoc.number : '',
      items: fromDoc ? JSON.parse(JSON.stringify(fromDoc.items)) : [blankItem()],
      discount: fromDoc ? fromDoc.discount : 0,
      withholdingId: fromDoc ? fromDoc.withholdingId : '',
      depositPercent: type === 'receipt' ? 0 : s.depositPercent,
      showDeposit: type !== 'receipt',
      bankIds: type === 'receipt' ? [] : s.banks.filter((b) => b.active).map((b) => b.id),
      notes: fromDoc ? fromDoc.notes : '',
      terms: type === 'receipt' ? '' : s.terms,
      payments: [],
      created: new Date().toISOString(),
    };
    if (type === 'receipt') {
      d.status = 'paid';
      d.withholdingId = '';
      d.payments = [{ id: uid(), date: today(), amount: 0, method: PAYMENT_METHODS[0], ref: '' }];
    }
    return d;
  }

  function blankItem(catalogItem) {
    return catalogItem
      ? { id: uid(), category: catalogItem.category, name: catalogItem.name, description: '', qty: 1, unit: catalogItem.unit, price: catalogItem.price, discount: 0, taxId: catalogItem.taxId || data.settings.defaultTaxId }
      : { id: uid(), category: '', name: '', description: '', qty: 1, unit: '', price: 0, discount: 0, taxId: data.settings.defaultTaxId };
  }

  function commitNumber(d) {
    // Advance the counter only when a new document is first saved, so abandoned drafts do not skip numbers.
    if (!data.docs.some((x) => x.id === d.id)) {
      data.settings.numbering[d.type] = (data.settings.numbering[d.type] || 1) + 1;
    }
  }

  // ---------------------------------------------------------------- router

  const routes = {
    dashboard: renderDashboard,
    documents: renderDocuments,
    new: (type) => renderEditor(null, type),
    edit: (id) => renderEditor(id),
    view: renderView,
    clients: renderClients,
    catalog: renderCatalog,
    settings: renderSettings,
  };

  function route() {
    const [name, arg] = (location.hash.replace(/^#\/?/, '').split('?')[0] || 'dashboard').split('/');
    const fn = routes[name] || renderDashboard;
    $$('[data-nav]').forEach((a) => a.classList.toggle('active', a.dataset.nav === name || (name === 'view' || name === 'edit' || name === 'new') && a.dataset.nav === 'documents'));
    $('#topbarActions').innerHTML = '';
    // Each screen installs its own click handlers.
    $('#app').onclick = null;
    $('#topbarActions').onclick = null;
    closeMenu();
    fn(arg ? decodeURIComponent(arg) : undefined);
    window.scrollTo(0, 0);
  }

  function setTitle(t) { $('#pageTitle').textContent = t; document.title = `${t} · BrandBay Invoicing`; }
  function actions(html) { $('#topbarActions').innerHTML = html; }

  // ---------------------------------------------------------------- dashboard

  function renderDashboard() {
    setTitle('Dashboard');
    const cur = data.settings.defaultCurrency;
    const invoices = data.docs.filter((d) => d.type === 'invoice' && d.status !== 'cancelled' && d.status !== 'draft');
    const sameCur = invoices.filter((d) => d.currency === cur);
    let billed = 0, received = 0, outstanding = 0, overdue = 0;
    sameCur.forEach((d) => {
      const t = totals(d);
      billed += t.payable; received += t.paid; outstanding += Math.max(0, t.balance);
      if (effectiveStatus(d) === 'overdue') overdue += Math.max(0, t.balance);
    });
    const quotes = data.docs.filter((d) => d.type === 'quotation');
    const openQuotes = quotes.filter((d) => ['draft', 'sent'].includes(d.status));
    const recent = [...data.docs].sort((a, b) => (b.created || '').localeCompare(a.created || '')).slice(0, 8);
    const otherCur = invoices.length - sameCur.length;
    const missingBank = !data.settings.banks.some((b) => b.active && b.accountNumber);
    const unpriced = data.catalog.filter((c) => !num(c.price)).length;

    $('#app').innerHTML = `
      ${missingBank || unpriced ? `<div class="notice">
        <strong>Finish setting up:</strong>
        ${missingBank ? '<a href="#/settings">add your bank / Mobile Money details</a>' : ''}
        ${missingBank && unpriced ? ' · ' : ''}
        ${unpriced ? `<a href="#/catalog">set prices for ${unpriced} service${unpriced === 1 ? '' : 's'}</a>` : ''}
      </div>` : ''}
      <div class="kpis">
        <div class="kpi"><span>Total invoiced</span><strong>${money(billed, cur)}</strong></div>
        <div class="kpi"><span>Received</span><strong>${money(received, cur)}</strong></div>
        <div class="kpi"><span>Outstanding</span><strong>${money(outstanding, cur)}</strong></div>
        <div class="kpi ${overdue ? 'kpi-alert' : ''}"><span>Overdue</span><strong>${money(overdue, cur)}</strong></div>
        <div class="kpi"><span>Open quotations</span><strong>${openQuotes.length}</strong></div>
        <div class="kpi"><span>Clients</span><strong>${data.clients.length}</strong></div>
      </div>
      ${otherCur ? `<p class="muted small">Totals above are in ${esc(cur)}. ${otherCur} invoice${otherCur === 1 ? '' : 's'} in other currencies ${otherCur === 1 ? 'is' : 'are'} not included.</p>` : ''}
      <section class="panel">
        <div class="panel-head"><h2>Recent documents</h2><a href="#/documents">View all</a></div>
        ${recent.length ? docTable(recent) : `<div class="empty"><p>No invoices yet.</p><a class="btn btn-gold" href="#/new/invoice">Create your first invoice</a></div>`}
      </section>`;
  }

  function statusBadge(s) { return `<span class="status status-${s}">${esc(STATUSES[s] || s)}</span>`; }

  function docTable(docs) {
    return `<div class="table-wrap"><table class="table">
      <thead><tr><th>Number</th><th>Type</th><th>Client</th><th>Date</th><th>Due / valid</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th></tr></thead>
      <tbody>${docs.map((d) => {
        const t = totals(d);
        const c = client(d.clientId);
        return `<tr data-href="#/view/${d.id}">
          <td><a href="#/view/${d.id}"><strong>${esc(d.number)}</strong></a></td>
          <td>${esc(DOC_TYPES[d.type].label)}</td>
          <td>${esc(c ? c.company || c.name : '—')}</td>
          <td>${fmtDate(d.issueDate)}</td>
          <td>${d.type === 'receipt' ? '—' : fmtDate(d.dueDate)}</td>
          <td>${statusBadge(effectiveStatus(d))}</td>
          <td class="num">${money(d.type === 'receipt' ? t.paid : t.payable, d.currency)}</td>
          <td class="num">${d.type === 'quotation' || d.type === 'receipt' ? '—' : money(Math.max(0, t.balance), d.currency)}</td>
        </tr>`;
      }).join('')}</tbody></table></div>`;
  }

  // ---------------------------------------------------------------- documents list

  function renderDocuments() {
    setTitle('Invoices & quotes');
    actions(`<a class="btn btn-gold btn-sm" href="#/new/invoice">+ New invoice</a>`);
    $('#app').innerHTML = `
      <div class="toolbar">
        <input type="search" id="docSearch" placeholder="Search number, client, item…" aria-label="Search documents">
        <select id="docType" aria-label="Type"><option value="">All types</option>${Object.entries(DOC_TYPES).map(([k, v]) => `<option value="${k}">${v.label}</option>`).join('')}</select>
        <select id="docStatus" aria-label="Status"><option value="">All statuses</option>${Object.entries(STATUSES).map(([k, v]) => `<option value="${k}">${v}</option>`).join('')}</select>
        <button class="btn btn-outline btn-sm" id="exportCsv" type="button">Export CSV</button>
      </div>
      <section class="panel" id="docList"></section>`;
    const draw = () => {
      const q = $('#docSearch').value.trim().toLowerCase();
      const type = $('#docType').value;
      const status = $('#docStatus').value;
      const list = [...data.docs]
        .filter((d) => !type || d.type === type)
        .filter((d) => !status || effectiveStatus(d) === status)
        .filter((d) => {
          if (!q) return true;
          const c = client(d.clientId);
          const hay = [d.number, d.reference, c && c.name, c && c.company, ...d.items.map((i) => i.name)].join(' ').toLowerCase();
          return hay.includes(q);
        })
        .sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || '') || (b.created || '').localeCompare(a.created || ''));
      $('#docList').innerHTML = list.length ? docTable(list) : '<p class="empty">No documents match.</p>';
    };
    ['#docSearch', '#docType', '#docStatus'].forEach((s) => $(s).addEventListener('input', draw));
    $('#exportCsv').addEventListener('click', exportCsv);
    draw();
  }

  function exportCsv() {
    const rows = [['Number', 'Type', 'Status', 'Client', 'Issue date', 'Due date', 'Currency', 'Subtotal', 'Discount', 'Tax', 'Total', 'Withholding', 'Payable', 'Paid', 'Balance']];
    data.docs.forEach((d) => {
      const t = totals(d);
      const c = client(d.clientId);
      rows.push([d.number, DOC_TYPES[d.type].label, STATUSES[effectiveStatus(d)], c ? c.company || c.name : '', d.issueDate, d.dueDate, d.currency, t.subtotal, t.discount, t.taxTotal, t.total, t.withholding, t.payable, t.paid, t.balance]);
    });
    const csv = rows.map((r) => r.map((v) => {
      let s = String(v ?? '');
      if (/^[=+\-@]/.test(s) && Number.isNaN(Number(s))) s = "'" + s;
      return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    }).join(',')).join('\n');
    download(`brandbay-documents-${today()}.csv`, csv, 'text/csv');
  }

  function download(name, content, type) {
    const blob = new Blob([content], { type });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
  }

  // ---------------------------------------------------------------- editor

  let draft = null;

  function renderEditor(id, type) {
    if (id) {
      const existing = doc(id);
      if (!existing) return renderMissing();
      if (isLinkedReceipt(existing)) {
        toast('This receipt mirrors a payment on an invoice. Change that payment, or void this receipt.', true);
        location.hash = `#/view/${existing.id}`;
        return;
      }
      draft = JSON.parse(JSON.stringify(existing));
    } else {
      const fromId = new URLSearchParams(location.hash.split('?')[1] || '').get('from');
      const t = DOC_TYPES[type] ? type : 'invoice';
      draft = newDoc(t, fromId ? doc(fromId) : null);
    }
    const isNew = !doc(draft.id);
    setTitle(`${isNew ? 'New' : 'Edit'} ${DOC_TYPES[draft.type].label.toLowerCase()}`);
    actions(`<button class="btn btn-outline btn-sm" type="button" data-act="cancel">Cancel</button><button class="btn btn-gold btn-sm" type="button" data-act="save">Save</button>`);
    const s = data.settings;

    $('#app').innerHTML = `
      <div class="editor">
        <form class="editor-form" id="docForm" autocomplete="off">
          <section class="panel">
            <h2>Details</h2>
            <div class="grid-3">
              <label>Document type
                <select name="type">${Object.entries(DOC_TYPES).map(([k, v]) => `<option value="${k}" ${draft.type === k ? 'selected' : ''}>${v.label}</option>`).join('')}</select>
              </label>
              <label>Number<input name="number" value="${esc(draft.number)}" required></label>
              <label>Status
                <select name="status">${statusOptions(draft.type, draft.status)}</select>
              </label>
              <label>Issue date<input type="date" name="issueDate" value="${esc(draft.issueDate)}" required></label>
              <label data-not-receipt><span id="dueLabel">${draft.type === 'quotation' ? 'Valid until' : 'Due date'}</span><input type="date" name="dueDate" value="${esc(draft.dueDate)}"></label>
              <label>Reference / PO<input name="reference" value="${esc(draft.reference)}" placeholder="e.g. BB-2026-0001"></label>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head"><h2 id="clientHeading">${draft.type === 'receipt' ? 'Received from' : 'Bill to'}</h2><button class="link" type="button" data-act="new-client">+ New client</button></div>
            <select name="clientId" aria-label="Client">
              <option value="">Choose a client…</option>
              ${[...data.clients].sort((a, b) => (a.company || a.name).localeCompare(b.company || b.name)).map((c) => `<option value="${c.id}" ${draft.clientId === c.id ? 'selected' : ''}>${esc(c.company ? `${c.company} — ${c.name}` : c.name)}</option>`).join('')}
            </select>
            <div id="quickClient" class="quick-client" hidden>
              <div class="grid-2">
                <label>Contact name*<input data-qc="name"></label>
                <label>Company<input data-qc="company"></label>
                <label>Email<input type="email" data-qc="email"></label>
                <label>Phone<input data-qc="phone"></label>
                <label>TPIN<input data-qc="tpin"></label>
                <label>Address<input data-qc="address"></label>
              </div>
              <button class="btn btn-gold btn-sm" type="button" data-act="add-client">Add client</button>
            </div>
          </section>

          <section class="panel" data-receipt-only>
            <h2>Payment received</h2>
            <div class="grid-3">
              <label>Date received<input type="date" data-rp="date" value="${esc(receiptPayment().date)}"></label>
              <label>Method<select data-rp="method">${PAYMENT_METHODS.map((m) => `<option ${receiptPayment().method === m ? 'selected' : ''}>${m}</option>`).join('')}</select></label>
              <label>Transaction reference<input data-rp="ref" value="${esc(receiptPayment().ref)}" placeholder="Transaction ID"></label>
            </div>
            <p class="muted small">The amount received is the total of the items below. To receipt a payment on an invoice, record the payment on that invoice instead.</p>
          </section>

          <section class="panel">
            <div class="panel-head"><h2 id="itemsHeading">${draft.type === 'receipt' ? 'What the payment was for' : 'Services &amp; items'}</h2></div>
            <div class="add-service">
              <label class="grow">Add a service
                <select id="servicePicker">
                  <option value="">Choose from the BrandBay catalogue…</option>
                  ${catalogOptions()}
                </select>
              </label>
              <button class="btn btn-outline btn-sm" type="button" data-act="add-custom">+ Custom line</button>
            </div>
            <div id="items"></div>
          </section>

          <section class="panel">
            <h2 id="moneyHeading">${draft.type === 'receipt' ? 'Currency &amp; tax' : 'Currency, tax &amp; payment'}</h2>
            <div class="grid-3">
              <label>Currency
                <select name="currency">${s.currencies.map((c) => `<option value="${c.code}" ${draft.currency === c.code ? 'selected' : ''}>${esc(c.code)} — ${esc(c.name)}</option>`).join('')}</select>
              </label>
              <label>Discount on whole document (%)<input type="number" name="discount" min="0" max="100" step="0.01" value="${esc(draft.discount)}"></label>
              <label data-not-receipt>Withholding tax
                <select name="withholdingId"><option value="">None</option>${s.taxes.filter((t) => t.kind === 'withhold').map((t) => `<option value="${t.id}" ${draft.withholdingId === t.id ? 'selected' : ''}>${esc(t.name)} (${t.rate}%)</option>`).join('')}</select>
              </label>
              <label data-not-receipt>Deposit required (%)<input type="number" name="depositPercent" min="0" max="100" step="1" value="${esc(draft.depositPercent)}"></label>
              <label class="check" data-not-receipt><input type="checkbox" name="showDeposit" ${draft.showDeposit ? 'checked' : ''}> Show deposit &amp; balance split</label>
            </div>
            <fieldset class="banks" data-not-receipt>
              <legend>Show these payment details</legend>
              ${s.banks.length ? s.banks.map((b) => `<label class="check"><input type="checkbox" name="bank" value="${b.id}" ${draft.bankIds.includes(b.id) ? 'checked' : ''}> ${esc(b.label)}${b.accountNumber ? '' : ' <span class="muted">(no account number yet)</span>'}</label>`).join('') : '<p class="muted">No payment details yet — add them in <a href="#/settings">Settings</a>.</p>'}
            </fieldset>
          </section>

          <section class="panel" id="paymentsPanel" data-not-receipt>
            <div class="panel-head"><h2>Payments received</h2><button class="link" type="button" data-act="add-payment">+ Record payment</button></div>
            <div id="payments"></div>
          </section>

          <section class="panel">
            <h2>Notes &amp; terms</h2>
            <label>Notes to client<textarea name="notes" rows="3" placeholder="e.g. Project scope, delivery timeline…">${esc(draft.notes)}</textarea></label>
            <label>Terms &amp; conditions<textarea name="terms" rows="4">${esc(draft.terms)}</textarea></label>
          </section>
        </form>
        <div class="editor-preview">
          <div class="preview-label">Live preview</div>
          <div class="paper-wrap"><div id="preview"></div></div>
        </div>
      </div>`;

    const form = $('#docForm');
    applyTypeVisibility();
    drawItems();
    drawPayments();
    drawPreview();

    form.addEventListener('input', onFormInput);
    form.addEventListener('change', onFormInput);
    form.addEventListener('submit', (e) => { e.preventDefault(); saveDraft(); });
    $('#servicePicker').addEventListener('change', (e) => {
      const item = data.catalog.find((c) => c.id === e.target.value);
      if (item) {
        // Replace the single empty starter line instead of leaving it behind.
        if (draft.items.length === 1 && !draft.items[0].name && !num(draft.items[0].price)) draft.items = [];
        draft.items.push(blankItem(item));
        drawItems();
        drawPreview();
        if (!num(item.price)) toast(`${item.name} has no price yet — enter it on the line or set it in Services & prices.`);
      }
      e.target.value = '';
    });
    $('#app').onclick = onEditorClick;
    $('#topbarActions').onclick = onEditorClick;
  }

  // A standalone receipt records its one payment in the "Payment received" panel.
  function receiptPayment() {
    if (!draft.payments || !draft.payments.length) draft.payments = [{ id: uid(), date: draft.issueDate || today(), amount: 0, method: PAYMENT_METHODS[0], ref: '' }];
    return draft.payments[0];
  }

  function applyTypeVisibility() {
    const receipt = draft.type === 'receipt';
    $$('[data-not-receipt]').forEach((el) => { el.hidden = receipt; });
    $$('[data-receipt-only]').forEach((el) => { el.hidden = !receipt; });
    if (draft.type === 'quotation') $('#paymentsPanel').hidden = true;
    $('#clientHeading').textContent = receipt ? 'Received from' : 'Bill to';
    $('#itemsHeading').textContent = receipt ? 'What the payment was for' : 'Services & items';
    $('#moneyHeading').textContent = receipt ? 'Currency & tax' : 'Currency, tax & payment';
  }

  function statusOptions(type, current) {
    const allowed = type === 'quotation' ? ['draft', 'sent', 'accepted', 'declined', 'cancelled']
      : type === 'receipt' ? ['paid', 'cancelled']
        : ['draft', 'sent', 'cancelled'];
    if (!allowed.includes(current)) allowed.unshift(current);
    return allowed.map((k) => `<option value="${k}" ${current === k ? 'selected' : ''}>${STATUSES[k]}</option>`).join('');
  }

  function catalogOptions() {
    const groups = {};
    [...data.catalog].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0)).forEach((c) => { (groups[c.category] = groups[c.category] || []).push(c); });
    return Object.entries(groups).map(([cat, items]) => `<optgroup label="${esc(cat)}">${items.map((c) => `<option value="${c.id}">${esc(c.name)}${num(c.price) ? ` — ${money(c.price, data.settings.defaultCurrency)}` : ''}</option>`).join('')}</optgroup>`).join('');
  }

  function drawItems() {
    const taxes = data.settings.taxes.filter((t) => t.kind === 'add');
    $('#items').innerHTML = draft.items.length ? draft.items.map((it, i) => `
      <div class="item" data-index="${i}">
        <div class="item-head">
          <span class="item-num">${i + 1}</span>
          ${it.category ? `<span class="item-cat">${esc(it.category)}</span>` : ''}
          <span class="item-tools">
            <button type="button" class="icon-btn" data-act="up" ${i === 0 ? 'disabled' : ''} aria-label="Move up">↑</button>
            <button type="button" class="icon-btn" data-act="down" ${i === draft.items.length - 1 ? 'disabled' : ''} aria-label="Move down">↓</button>
            <button type="button" class="icon-btn danger" data-act="remove" aria-label="Remove line">✕</button>
          </span>
        </div>
        <div class="item-grid">
          <label class="span-2">Service / item<input data-f="name" value="${esc(it.name)}" placeholder="What are you billing for?"></label>
          <label class="span-2">Description <span class="muted">(optional)</span><input data-f="description" value="${esc(it.description)}" placeholder="Scope, sizes, quantities…"></label>
          <label>Qty<input type="number" data-f="qty" min="0" step="any" value="${esc(it.qty)}"></label>
          <label>Unit<input data-f="unit" value="${esc(it.unit)}" placeholder="e.g. per 100"></label>
          <label>Unit price<input type="number" data-f="price" min="0" step="0.01" value="${esc(it.price)}"></label>
          <label>Discount %<input type="number" data-f="discount" min="0" max="100" step="0.01" value="${esc(it.discount)}"></label>
          <label class="span-2">Tax
            <select data-f="taxId">${taxes.map((t) => `<option value="${t.id}" ${it.taxId === t.id ? 'selected' : ''}>${esc(t.name)} (${t.rate}%)</option>`).join('')}</select>
          </label>
          <div class="item-total span-2"><span>Line total</span><strong data-line-total>${money(lineNet(it), draft.currency)}</strong></div>
        </div>
      </div>`).join('') : '<p class="muted">No items yet. Choose a service above or add a custom line.</p>';
  }

  function lineNet(it) {
    const gross = num(it.qty) * num(it.price);
    return round2(gross - gross * Math.min(100, Math.max(0, num(it.discount))) / 100);
  }

  function drawPayments() {
    const el = $('#payments');
    if (!el) return;
    const pays = draft.payments || [];
    el.innerHTML = pays.length ? pays.map((p, i) => `
      <div class="payment" data-pay="${i}">
        <label>Date<input type="date" data-p="date" value="${esc(p.date)}"></label>
        <label>Amount<input type="number" data-p="amount" min="0" step="0.01" value="${esc(p.amount)}"></label>
        <label>Method<select data-p="method">${PAYMENT_METHODS.map((m) => `<option ${p.method === m ? 'selected' : ''}>${m}</option>`).join('')}</select></label>
        <label>Reference<input data-p="ref" value="${esc(p.ref)}" placeholder="Transaction ID"></label>
        <button type="button" class="icon-btn danger" data-act="remove-payment" aria-label="Remove payment">✕</button>
      </div>`).join('') : '<p class="muted">No payments recorded.</p>';
  }

  function drawPreview() {
    $('#preview').innerHTML = renderPaper(draft);
  }

  function onFormInput(e) {
    const t = e.target;
    if (t.id === 'servicePicker' || t.dataset.qc !== undefined) return;
    const itemEl = t.closest('.item');
    const payEl = t.closest('.payment');
    if (itemEl && t.dataset.f) {
      const it = draft.items[Number(itemEl.dataset.index)];
      it[t.dataset.f] = t.type === 'number' ? t.value : t.value;
      $('[data-line-total]', itemEl).textContent = money(lineNet(it), draft.currency);
    } else if (payEl && t.dataset.p) {
      draft.payments[Number(payEl.dataset.pay)][t.dataset.p] = t.value;
    } else if (t.dataset.rp) {
      receiptPayment()[t.dataset.rp] = t.value;
    } else if (t.name === 'bank') {
      draft.bankIds = $$('input[name="bank"]:checked').map((x) => x.value);
    } else if (t.name === 'showDeposit') {
      draft.showDeposit = t.checked;
    } else if (t.name === 'type') {
      const wasNew = !doc(draft.id);
      draft.type = t.value;
      if (wasNew) {
        draft.number = nextNumber(draft.type);
        $('input[name="number"]').value = draft.number;
      }
      draft.status = draft.type === 'receipt' ? 'paid' : 'draft';
      if (draft.type === 'receipt') {
        draft.payments = [receiptPayment()];
        draft.dueDate = '';
      }
      $('select[name="status"]').innerHTML = statusOptions(draft.type, draft.status);
      $('#dueLabel').textContent = draft.type === 'quotation' ? 'Valid until' : 'Due date';
      applyTypeVisibility();
      drawPayments();
      setTitle(`${wasNew ? 'New' : 'Edit'} ${DOC_TYPES[draft.type].label.toLowerCase()}`);
    } else if (t.name) {
      draft[t.name] = t.value;
      if (t.name === 'currency') $$('.item').forEach((el) => { $('[data-line-total]', el).textContent = money(lineNet(draft.items[Number(el.dataset.index)]), draft.currency); });
    }
    drawPreview();
  }

  function onEditorClick(e) {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const act = btn.dataset.act;
    const itemEl = btn.closest('.item');
    const idx = itemEl ? Number(itemEl.dataset.index) : -1;
    switch (act) {
      case 'save': saveDraft(); return;
      case 'cancel': location.hash = doc(draft.id) ? `#/view/${draft.id}` : '#/documents'; return;
      case 'add-custom': draft.items.push(blankItem()); drawItems(); break;
      case 'remove': draft.items.splice(idx, 1); drawItems(); break;
      case 'up': if (idx > 0) { [draft.items[idx - 1], draft.items[idx]] = [draft.items[idx], draft.items[idx - 1]]; drawItems(); } break;
      case 'down': if (idx < draft.items.length - 1) { [draft.items[idx + 1], draft.items[idx]] = [draft.items[idx], draft.items[idx + 1]]; drawItems(); } break;
      case 'add-payment': {
        const t = totals(draft);
        draft.payments.push({ id: uid(), date: today(), amount: Math.max(0, t.balance) || '', method: PAYMENT_METHODS[0], ref: '' });
        drawPayments();
        break;
      }
      case 'remove-payment': draft.payments.splice(Number(btn.closest('.payment').dataset.pay), 1); drawPayments(); break;
      case 'new-client': $('#quickClient').hidden = !$('#quickClient').hidden; return;
      case 'add-client': {
        const v = {};
        $$('[data-qc]').forEach((i) => { v[i.dataset.qc] = i.value.trim(); });
        if (!v.name) { toast('Enter the client’s contact name.', true); return; }
        const c = { id: uid(), ...v };
        data.clients.push(c);
        save();
        draft.clientId = c.id;
        const sel = $('select[name="clientId"]');
        sel.insertAdjacentHTML('beforeend', `<option value="${c.id}">${esc(c.company ? `${c.company} — ${c.name}` : c.name)}</option>`);
        sel.value = c.id;
        $('#quickClient').hidden = true;
        $$('[data-qc]').forEach((i) => { i.value = ''; });
        toast('Client added.');
        break;
      }
      default: return;
    }
    drawPreview();
  }

  function saveDraft() {
    const errors = [];
    if (!draft.number.trim()) errors.push('Enter a document number.');
    if (data.docs.some((d) => d.id !== draft.id && d.number.trim().toLowerCase() === draft.number.trim().toLowerCase())) errors.push(`Number ${draft.number} is already used.`);
    if (!draft.clientId) errors.push(draft.type === 'receipt' ? 'Choose who the payment was received from.' : 'Choose who this is billed to.');
    if (!draft.items.length || !draft.items.some((i) => i.name.trim())) errors.push('Add at least one service or item.');
    if (draft.items.some((i) => num(i.qty) < 0 || num(i.price) < 0)) errors.push('Quantities and prices cannot be negative.');
    if (draft.type === 'receipt' && totals(draft).payable <= 0) errors.push('The amount received must be greater than zero.');
    if (errors.length) { toast(errors[0], true); return; }

    draft.items = draft.items.filter((i) => i.name.trim() || num(i.price));
    if (draft.type === 'receipt') {
      // A standalone receipt has exactly one payment, always equal to its total.
      const p = receiptPayment();
      draft.payments = [{ ...p, date: p.date || draft.issueDate, amount: totals(draft).payable }];
      Object.assign(draft, { withholdingId: '', depositPercent: 0, showDeposit: false, bankIds: [], dueDate: '' });
    } else {
      draft.payments = (draft.payments || []).filter((p) => num(p.amount) > 0);
      draft.payments.forEach((p) => { if (!p.id) p.id = uid(); });
    }
    const before = doc(draft.id);
    if (before && draft.type !== 'receipt') syncReceipts(draft, before.payments);
    draft.discount = num(draft.discount);
    draft.depositPercent = num(draft.depositPercent);
    draft.updated = new Date().toISOString();
    commitNumber(draft);
    const i = data.docs.findIndex((d) => d.id === draft.id);
    if (i >= 0) data.docs[i] = draft; else data.docs.push(draft);
    if (save()) {
      toast(`${DOC_TYPES[draft.type].label} ${draft.number} saved.`);
      location.hash = `#/view/${draft.id}`;
    }
  }

  // ---------------------------------------------------------------- printable document

  function renderPaper(d) {
    const s = data.settings;
    const co = s.company;
    const c = client(d.clientId);
    const t = totals(d);
    const label = DOC_TYPES[d.type].label;
    const cur = currency(d.currency);
    // Accounts without a number are skipped so an unfinished setup never prints blank details.
    const banks = d.type === 'receipt' ? [] : s.banks.filter((b) => d.bankIds.includes(b.id) && b.accountNumber);
    const rd = d.type === 'receipt' ? receiptDetails(d) : null;
    const status = effectiveStatus(d);
    const zmwRate = d.currency !== 'ZMW' && cur && num(cur.rate) ? num(cur.rate) : 0;

    return `<article class="paper">
      ${status === 'paid' && d.type !== 'quotation' ? '<div class="stamp">PAID</div>' : ''}
      ${status === 'cancelled' ? '<div class="stamp stamp-void">VOID</div>' : ''}
      <header class="paper-head">
        <div class="paper-brand">
          <img src="${esc(co.logo || 'logo-dark.png')}" alt="${esc(co.name)}" class="paper-logo">
          <div class="paper-co">
            <strong>${esc(co.name)}</strong>
            ${co.address1 ? `<div>${esc(co.address1)}</div>` : ''}
            ${co.address2 ? `<div>${esc(co.address2)}</div>` : ''}
            <div>${[co.phone, co.email].filter(Boolean).map(esc).join(' · ')}</div>
            ${co.website ? `<div>${esc(co.website)}</div>` : ''}
            ${co.tpin ? `<div>TPIN: ${esc(co.tpin)}</div>` : ''}
          </div>
        </div>
        <div class="paper-title">
          <h2>${esc(label)}</h2>
          <table class="meta">
            <tr><th>No.</th><td>${esc(d.number)}</td></tr>
            <tr><th>Date</th><td>${fmtDate(d.issueDate)}</td></tr>
            ${d.type !== 'receipt' && d.dueDate ? `<tr><th>${d.type === 'quotation' ? 'Valid until' : 'Due'}</th><td>${fmtDate(d.dueDate)}</td></tr>` : ''}
            ${d.reference ? `<tr><th>Ref.</th><td>${esc(d.reference)}</td></tr>` : ''}
            <tr><th>Currency</th><td>${esc(d.currency)}</td></tr>
          </table>
        </div>
      </header>

      <section class="paper-parties">
        <div>
          <h3>${d.type === 'receipt' ? 'Received from' : d.type === 'quotation' ? 'Prepared for' : 'Bill to'}</h3>
          ${c ? `
            ${c.company ? `<strong>${esc(c.company)}</strong><div>${esc(c.name)}</div>` : `<strong>${esc(c.name)}</strong>`}
            ${c.address ? `<div>${nl2br(c.address)}</div>` : ''}
            ${c.phone ? `<div>${esc(c.phone)}</div>` : ''}
            ${c.email ? `<div>${esc(c.email)}</div>` : ''}
            ${c.tpin ? `<div>TPIN: ${esc(c.tpin)}</div>` : ''}` : '<span class="muted">No client selected</span>'}
        </div>
        <div class="paper-due">
          <span>${d.type === 'quotation' ? 'Quoted total' : d.type === 'receipt' ? 'Amount received' : 'Amount due'}</span>
          <strong>${money(d.type === 'receipt' ? t.paid || t.payable : d.type === 'quotation' ? t.payable : Math.max(0, t.balance), d.currency)}</strong>
        </div>
      </section>

      <table class="lines">
        <thead><tr><th>#</th><th>Service / item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Disc.</th><th>Tax</th><th class="num">Amount</th></tr></thead>
        <tbody>
          ${d.items.map((it, i) => {
            const l = t.lines[i];
            return `<tr>
              <td>${i + 1}</td>
              <td><strong>${esc(it.name) || '<span class="muted">Untitled item</span>'}</strong>
                ${it.category ? `<div class="line-cat">${esc(it.category)}</div>` : ''}
                ${it.description ? `<div class="line-desc">${esc(it.description)}</div>` : ''}</td>
              <td class="num">${esc(num(it.qty))}${it.unit ? ` <span class="unit">${esc(it.unit)}</span>` : ''}</td>
              <td class="num">${money(it.price, d.currency)}</td>
              <td class="num">${num(it.discount) ? `${num(it.discount)}%` : '—'}</td>
              <td>${l.tax ? `${esc(l.tax.rate)}%` : '—'}</td>
              <td class="num">${money(l.gross - l.lineDiscount, d.currency)}</td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>

      <section class="paper-bottom">
        <div class="paper-pay">
          ${banks.length ? `<h3>Payment details</h3>
            ${banks.map((b) => b.type === 'mobile' ? `
              <div class="bank"><strong>${esc(b.label || b.provider)}</strong>
                <div>Number: ${esc(b.accountNumber) || '<span class="muted">—</span>'}</div>
                ${b.accountName ? `<div>Name: ${esc(b.accountName)}</div>` : ''}</div>` : `
              <div class="bank"><strong>${esc(b.bankName || b.label)}</strong>
                ${b.accountName ? `<div>Account name: ${esc(b.accountName)}</div>` : ''}
                <div>Account no.: ${esc(b.accountNumber) || '<span class="muted">—</span>'}${b.currency ? ` (${esc(b.currency)})` : ''}</div>
                ${b.branch ? `<div>Branch: ${esc(b.branch)}${b.branchCode ? ` · Code ${esc(b.branchCode)}` : ''}</div>` : ''}
                ${b.swift ? `<div>SWIFT: ${esc(b.swift)}</div>` : ''}</div>`).join('')}
            <p class="small">Please use <strong>${esc(d.number)}</strong> as your payment reference.</p>` : ''}
          ${rd && rd.invoice ? `<h3>Applied to ${esc(rd.invoice.label.toLowerCase())} ${esc(rd.invoice.number)}</h3>
            <table class="sums">
              <tr><th>${esc(rd.invoice.label)} total</th><td>${money(rd.invoice.total, d.currency)}</td></tr>
              <tr><th>This payment</th><td>${money(rd.amount, d.currency)}</td></tr>
              <tr><th>Paid to date</th><td>${money(rd.invoice.paidToDate, d.currency)}</td></tr>
              <tr class="total"><th>Balance remaining</th><td>${money(rd.invoice.balance, d.currency)}</td></tr>
            </table>` : ''}
        </div>
        <table class="sums">
          <tr><th>Subtotal</th><td>${money(t.subtotal, d.currency)}</td></tr>
          ${t.discount ? `<tr><th>Discount (${num(d.discount)}%)</th><td>-${money(t.discount, d.currency)}</td></tr>` : ''}
          ${t.taxGroups.map((g) => `<tr><th>${esc(g.name)} ${g.rate}%<span class="sub">on ${money(g.base, d.currency)}</span></th><td>${money(g.amount, d.currency)}</td></tr>`).join('')}
          <tr class="total"><th>Total</th><td>${money(t.total, d.currency)}</td></tr>
          ${t.withholding ? `<tr><th>Less ${esc(t.wht.name)} ${t.wht.rate}%</th><td>-${money(t.withholding, d.currency)}</td></tr><tr class="total"><th>Amount payable</th><td>${money(t.payable, d.currency)}</td></tr>` : ''}
          ${d.showDeposit && num(d.depositPercent) > 0 && num(d.depositPercent) < 100 && d.type !== 'receipt' ? `
            <tr class="split"><th>Deposit ${num(d.depositPercent)}%</th><td>${money(t.deposit, d.currency)}</td></tr>
            <tr class="split"><th>Balance on completion</th><td>${money(t.payable - t.deposit, d.currency)}</td></tr>` : ''}
          ${d.type === 'receipt' ? `<tr class="total"><th>Amount received</th><td>${money(t.paid || t.payable, d.currency)}</td></tr>` : ''}
          ${t.paid && d.type !== 'quotation' && d.type !== 'receipt' ? `<tr><th>Paid</th><td>-${money(t.paid, d.currency)}</td></tr><tr class="total"><th>Balance due</th><td>${money(Math.max(0, t.balance), d.currency)}</td></tr>` : ''}
          ${zmwRate ? `<tr class="fx"><th>ZMW equivalent @ ${zmwRate}</th><td>K${round2(t.payable * zmwRate).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>` : ''}
        </table>
      </section>

      ${(d.payments || []).filter((p) => num(p.amount) > 0).length && d.type !== 'quotation' ? `
        <section class="paper-payments">
          <h3>${d.type === 'receipt' ? 'Payment details' : 'Payments received'}</h3>
          <table class="lines compact"><tbody>${d.payments.filter((p) => num(p.amount) > 0).map((p) => `<tr><td>${fmtDate(p.date)}</td><td>${esc(p.method)}</td><td>${esc(p.ref)}</td><td class="num">${money(p.amount, d.currency)}</td></tr>`).join('')}</tbody></table>
        </section>` : ''}

      ${d.notes ? `<section class="paper-notes"><h3>Notes</h3><p>${nl2br(d.notes)}</p></section>` : ''}
      ${d.terms ? `<section class="paper-notes"><h3>Terms &amp; conditions</h3><p>${nl2br(d.terms)}</p></section>` : ''}
      <footer class="paper-foot">${esc(s.footer)}<span>${esc(co.tagline)}</span></footer>
    </article>`;
  }

  // ---------------------------------------------------------------- view

  function renderView(id) {
    const d = doc(id);
    if (!d) return renderMissing();
    const label = DOC_TYPES[d.type].label;
    setTitle(`${label} ${d.number}`);
    const status = effectiveStatus(d);
    const linked = isLinkedReceipt(d);
    actions(`
      ${linked ? '' : `<a class="btn btn-outline btn-sm" href="#/edit/${d.id}">Edit</a>`}
      <button class="btn btn-gold btn-sm" type="button" data-act="print">Print / Save PDF</button>`);
    const t = totals(d);
    const rd = d.type === 'receipt' ? receiptDetails(d) : null;
    const pays = (d.payments || []).filter((p) => num(p.amount) > 0);
    const c = client(d.clientId);
    $('#app').innerHTML = `
      <div class="view-bar">
        <div>${statusBadge(status)}
          ${d.type !== 'quotation' && d.type !== 'receipt' && t.balance > 0 ? `<span class="muted">Balance ${money(t.balance, d.currency)}</span>` : ''}
          ${rd ? `<span class="muted">Received ${money(rd.amount, d.currency)}${rd.invoice ? ` · balance remaining on <a href="#/view/${rd.invoice.id}">${esc(rd.invoice.number)}</a>: ${money(rd.invoice.balance, d.currency)}` : ''}</span>` : ''}</div>
        <div class="view-actions">
          ${d.type === 'quotation' ? `<a class="btn btn-gold btn-sm" href="#/new/invoice?from=${d.id}">Convert to invoice</a><a class="btn btn-outline btn-sm" href="#/new/proforma?from=${d.id}">Convert to proforma</a>` : ''}
          ${d.type === 'proforma' ? `<a class="btn btn-gold btn-sm" href="#/new/invoice?from=${d.id}">Convert to invoice</a>` : ''}
          ${(d.type === 'invoice' || d.type === 'proforma') && t.balance > 0 && status !== 'cancelled' ? `<button class="btn btn-gold btn-sm" type="button" data-act="pay">Record payment</button>` : ''}
          ${['draft'].includes(d.status) && d.type !== 'receipt' ? `<button class="btn btn-outline btn-sm" type="button" data-act="mark-sent">Mark as sent</button>` : ''}
          ${d.type === 'receipt' ? `<button class="btn btn-outline btn-sm" type="button" data-act="void">${d.status === 'cancelled' ? 'Restore receipt' : 'Void receipt'}</button>` : ''}
          <button class="btn btn-outline btn-sm" type="button" data-act="email">Email</button>
          ${linked ? '' : '<button class="btn btn-outline btn-sm" type="button" data-act="duplicate">Duplicate</button>'}
          <button class="btn btn-danger btn-sm" type="button" data-act="delete">Delete</button>
        </div>
      </div>
      <form class="panel pay-form" id="payForm" hidden>
        <h2>Record a payment</h2>
        <div class="payment">
          <label>Date<input type="date" name="date" value="${today()}" required></label>
          <label>Amount (${esc(d.currency)})<input type="number" name="amount" min="0.01" step="0.01" value="${Math.max(0, t.balance)}" required></label>
          <label>Method<select name="method">${PAYMENT_METHODS.map((m) => `<option>${m}</option>`).join('')}</select></label>
          <label>Reference<input name="ref" placeholder="Transaction ID"></label>
          <button class="btn btn-gold btn-sm" type="submit">Save payment</button>
        </div>
        <label class="check"><input type="checkbox" name="issueReceipt" checked> Issue a receipt for this payment</label>
      </form>
      ${pays.length && (d.type === 'invoice' || d.type === 'proforma') ? `<section class="panel">
        <h2>Payments &amp; receipts</h2>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th>Receipt</th><th></th></tr></thead>
          <tbody>${pays.map((p) => {
            const r = p.receiptId ? doc(p.receiptId) : null;
            return `<tr>
              <td>${fmtDate(p.date)}</td><td>${esc(p.method)}</td><td>${esc(p.ref)}</td><td class="num">${money(p.amount, d.currency)}</td>
              <td>${r ? `<a href="#/view/${r.id}">${esc(r.number)}</a>${r.status === 'cancelled' ? ' <span class="muted">(void)</span>' : ''}` : `<button class="link" type="button" data-act="issue-receipt" data-pay="${esc(p.id)}">Issue receipt</button>`}</td>
              <td><button class="link danger" type="button" data-act="remove-pay" data-pay="${esc(p.id)}">Remove</button></td>
            </tr>`;
          }).join('')}</tbody>
        </table></div>
      </section>` : ''}
      <div class="paper-wrap single">${renderPaper(d)}</div>`;

    const handler = (e) => {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;
      switch (btn.dataset.act) {
        case 'print': window.print(); break;
        case 'pay': $('#payForm').hidden = false; $('#payForm input[name="amount"]').focus(); break;
        case 'mark-sent': d.status = 'sent'; save(); route(); toast('Marked as sent.'); break;
        case 'duplicate': {
          const copy = newDoc(d.type, d);
          copy.reference = '';
          // A copied standalone receipt is a fresh payment received today for the same amount.
          if (d.type === 'receipt') copy.payments = [{ ...(d.payments[0] || {}), id: uid(), date: today(), ref: '' }];
          commitNumber(copy);
          data.docs.push(copy);
          save();
          location.hash = `#/edit/${copy.id}`;
          toast(`Duplicated as ${copy.number}.`);
          break;
        }
        case 'issue-receipt': {
          const p = d.payments.find((x) => x.id === btn.dataset.pay);
          if (!p) break;
          const r = issueReceipt(d, p);
          save();
          location.hash = `#/view/${r.id}`;
          toast(`Receipt ${r.number} issued.`);
          break;
        }
        case 'remove-pay': {
          const p = d.payments.find((x) => x.id === btn.dataset.pay);
          if (!p || !confirm(`Remove the payment of ${money(p.amount, d.currency)}?${p.receiptId ? ' Its receipt will be voided.' : ''}`)) break;
          const prev = JSON.parse(JSON.stringify(d.payments));
          d.payments = d.payments.filter((x) => x !== p);
          // A receipt must never outlive the money it acknowledges.
          syncReceipts(d, prev);
          save();
          route();
          toast(p.receiptId ? 'Payment removed and its receipt voided.' : 'Payment removed.');
          break;
        }
        case 'void':
          d.status = d.status === 'cancelled' ? 'paid' : 'cancelled';
          save();
          route();
          toast(d.status === 'cancelled' ? 'Receipt voided.' : 'Receipt restored.');
          break;
        case 'email': {
          const tt = totals(d);
          const body = d.type === 'receipt'
            ? `Dear ${c ? c.name : 'client'},\n\nThank you for your payment of ${money(rd.amount, d.currency)} received on ${fmtDate((d.payments[0] || {}).date || d.issueDate)}.${rd.invoice ? `\nIt has been applied to ${rd.invoice.label.toLowerCase()} ${rd.invoice.number}. Balance remaining: ${money(rd.invoice.balance, d.currency)}.` : ''}\n\nYour receipt ${d.number} is attached.\n\nKind regards,\n${data.settings.company.name}\n${data.settings.company.phone}`
            : `Dear ${c ? c.name : 'client'},\n\nPlease find attached ${label.toLowerCase()} ${d.number} dated ${fmtDate(d.issueDate)} for ${money(tt.payable, d.currency)}.${d.type === 'invoice' && d.dueDate ? `\nPayment is due by ${fmtDate(d.dueDate)}.` : ''}\n\nPlease use ${d.number} as your payment reference.\n\nKind regards,\n${data.settings.company.name}\n${data.settings.company.phone}`;
          location.href = `mailto:${encodeURIComponent(c && c.email || '')}?subject=${encodeURIComponent(`${label} ${d.number} — ${data.settings.company.name}`)}&body=${encodeURIComponent(body)}`;
          toast('Tip: use Print / Save PDF to attach the document.');
          break;
        }
        case 'delete':
          if (confirm(`Delete ${label.toLowerCase()} ${d.number}? This cannot be undone.`)) {
            data.docs = data.docs.filter((x) => x.id !== d.id);
            // Unlink the payment a deleted receipt was issued for, so a new receipt can be issued.
            data.docs.forEach((x) => (x.payments || []).forEach((p) => { if (p.receiptId === d.id) delete p.receiptId; }));
            save();
            location.hash = '#/documents';
            toast('Deleted.');
          }
          break;
        default:
      }
    };
    $('#app').onclick = handler;
    $('#topbarActions').onclick = handler;
    $('#payForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const f = new FormData(e.target);
      const amount = num(f.get('amount'));
      if (amount <= 0) { toast('Enter an amount greater than zero.', true); return; }
      d.payments = d.payments || [];
      const payment = { id: uid(), date: f.get('date'), amount, method: f.get('method'), ref: String(f.get('ref') || '').trim() };
      d.payments.push(payment);
      if (d.status === 'draft') d.status = 'sent';
      const r = f.get('issueReceipt') ? issueReceipt(d, payment) : null;
      save();
      route();
      toast(r ? `Payment recorded. Receipt ${r.number} issued.` : 'Payment recorded.');
    });
  }

  function renderMissing() {
    setTitle('Not found');
    $('#app').innerHTML = '<div class="empty panel"><p>That document no longer exists.</p><a class="btn btn-gold" href="#/documents">Back to documents</a></div>';
  }

  // ---------------------------------------------------------------- clients

  function renderClients() {
    setTitle('Clients');
    actions('<button class="btn btn-gold btn-sm" type="button" data-act="add">+ Add client</button>');
    const draw = () => {
      const list = [...data.clients].sort((a, b) => (a.company || a.name).localeCompare(b.company || b.name));
      $('#clientList').innerHTML = list.length ? `<div class="table-wrap"><table class="table">
        <thead><tr><th>Client</th><th>Contact</th><th>TPIN</th><th class="num">Documents</th><th class="num">Outstanding</th><th></th></tr></thead>
        <tbody>${list.map((c) => {
          const docs = data.docs.filter((d) => d.clientId === c.id);
          const owed = {};
          docs.filter((d) => d.type === 'invoice' && !['draft', 'cancelled'].includes(d.status)).forEach((d) => { owed[d.currency] = (owed[d.currency] || 0) + Math.max(0, totals(d).balance); });
          const owedStr = Object.entries(owed).filter(([, v]) => v > 0).map(([k, v]) => money(v, k)).join(' + ') || '—';
          return `<tr>
            <td><strong>${esc(c.company || c.name)}</strong>${c.company ? `<div class="muted small">${esc(c.name)}</div>` : ''}</td>
            <td>${esc(c.email)}${c.phone ? `<div class="muted small">${esc(c.phone)}</div>` : ''}</td>
            <td>${esc(c.tpin) || '—'}</td>
            <td class="num">${docs.length}</td>
            <td class="num">${owedStr}</td>
            <td class="num nowrap"><button class="link" data-act="edit" data-id="${c.id}">Edit</button> · <a class="link" href="#/new/invoice" data-act="invoice" data-id="${c.id}">Invoice</a></td>
          </tr>`;
        }).join('')}</tbody></table></div>` : '<div class="empty"><p>No clients yet.</p></div>';
    };
    $('#app').innerHTML = `
      <form class="panel" id="clientForm" hidden>
        <h2 id="clientFormTitle">New client</h2>
        <input type="hidden" name="id">
        <div class="grid-3">
          <label>Contact name*<input name="name" required></label>
          <label>Company / organisation<input name="company"></label>
          <label>TPIN<input name="tpin"></label>
          <label>Email<input type="email" name="email"></label>
          <label>Phone<input name="phone"></label>
          <label>Address<textarea name="address" rows="2"></textarea></label>
        </div>
        <div class="form-actions">
          <button class="btn btn-gold btn-sm" type="submit">Save client</button>
          <button class="btn btn-outline btn-sm" type="button" data-act="close">Cancel</button>
          <button class="btn btn-danger btn-sm" type="button" data-act="delete" hidden>Delete client</button>
        </div>
      </form>
      <section class="panel" id="clientList"></section>`;
    const form = $('#clientForm');
    const open = (c) => {
      form.reset();
      form.hidden = false;
      $('#clientFormTitle').textContent = c ? 'Edit client' : 'New client';
      $('[data-act="delete"]', form).hidden = !c;
      if (c) Object.entries(c).forEach(([k, v]) => { if (form.elements[k]) form.elements[k].value = v || ''; });
      form.elements.name.focus();
    };
    const handler = (e) => {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;
      const act = btn.dataset.act;
      if (act === 'add') open(null);
      if (act === 'edit') open(client(btn.dataset.id));
      if (act === 'close') form.hidden = true;
      if (act === 'invoice') {
        e.preventDefault();
        pendingClient = btn.dataset.id;
        location.hash = '#/new/invoice';
      }
      if (act === 'delete') {
        const id = form.elements.id.value;
        if (data.docs.some((d) => d.clientId === id)) { toast('This client has documents, so it cannot be deleted.', true); return; }
        if (confirm('Delete this client?')) { data.clients = data.clients.filter((c) => c.id !== id); save(); form.hidden = true; draw(); }
      }
    };
    $('#app').onclick = handler;
    $('#topbarActions').onclick = handler;
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const f = Object.fromEntries(new FormData(form));
      Object.keys(f).forEach((k) => { f[k] = String(f[k]).trim(); });
      if (!f.name) return;
      if (f.id) Object.assign(client(f.id), f);
      else data.clients.push({ ...f, id: uid() });
      save();
      form.hidden = true;
      draw();
      toast('Client saved.');
    });
    draw();
  }

  let pendingClient = null;

  // ---------------------------------------------------------------- catalog

  function renderCatalog() {
    setTitle('Services & prices');
    actions('<button class="btn btn-gold btn-sm" type="button" data-act="save">Save prices</button>');
    const cur = currency(data.settings.defaultCurrency);
    const taxes = data.settings.taxes.filter((t) => t.kind === 'add');
    const cats = [...new Set(data.catalog.map((c) => c.category))];
    $('#app').innerHTML = `
      <p class="muted">These are BrandBay’s services and sub-services. Set your standard prices (in ${esc(cur.code)}) — they fill in automatically when you add a service to an invoice, and you can still change them per invoice.</p>
      <form id="catalogForm">
        ${cats.map((cat) => `
          <section class="panel">
            <h2>${esc(cat)}</h2>
            <div class="table-wrap"><table class="table cat-table">
              <thead><tr><th>Sub-service</th><th>Unit</th><th>Price (${esc(cur.symbol)})</th><th>Default tax</th><th></th></tr></thead>
              <tbody>${data.catalog.filter((c) => c.category === cat).sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0)).map((c) => `
                <tr data-id="${c.id}">
                  <td><input data-c="name" value="${esc(c.name)}" aria-label="Name"></td>
                  <td><input data-c="unit" value="${esc(c.unit)}" aria-label="Unit"></td>
                  <td><input type="number" data-c="price" min="0" step="0.01" value="${esc(c.price)}" aria-label="Price"></td>
                  <td><select data-c="taxId" aria-label="Tax">${taxes.map((t) => `<option value="${t.id}" ${c.taxId === t.id ? 'selected' : ''}>${esc(t.name)} (${t.rate}%)</option>`).join('')}</select></td>
                  <td><button type="button" class="icon-btn danger" data-act="remove" aria-label="Remove">✕</button></td>
                </tr>`).join('')}</tbody>
            </table></div>
            <button type="button" class="link" data-act="add" data-cat="${esc(cat)}">+ Add sub-service</button>
          </section>`).join('')}
        <section class="panel">
          <h2>Add a new service category</h2>
          <div class="add-service">
            <label class="grow">Category name<input id="newCat" placeholder="e.g. Photography & Video"></label>
            <button type="button" class="btn btn-outline btn-sm" data-act="add-cat">Add category</button>
          </div>
        </section>
      </form>`;

    const collect = () => {
      $$('#catalogForm tr[data-id]').forEach((tr) => {
        const item = data.catalog.find((c) => c.id === tr.dataset.id);
        if (!item) return;
        $$('[data-c]', tr).forEach((i) => { item[i.dataset.c] = i.dataset.c === 'price' ? round2(num(i.value)) : i.value.trim(); });
      });
      data.catalog = data.catalog.filter((c) => c.name);
    };
    const handler = (e) => {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;
      const act = btn.dataset.act;
      if (act === 'save') { collect(); save(); toast('Prices saved.'); return; }
      collect();
      if (act === 'remove') data.catalog = data.catalog.filter((c) => c.id !== btn.closest('tr').dataset.id);
      if (act === 'add') data.catalog.push({ id: uid(), category: btn.dataset.cat, name: 'New sub-service', price: 0, unit: 'per project', taxId: data.settings.defaultTaxId, sort: data.catalog.length });
      if (act === 'add-cat') {
        const name = $('#newCat').value.trim();
        if (!name) { toast('Enter a category name.', true); return; }
        data.catalog.push({ id: uid(), category: name, name: 'New sub-service', price: 0, unit: 'per project', taxId: data.settings.defaultTaxId, sort: data.catalog.length });
      }
      save();
      renderCatalog();
    };
    $('#app').onclick = handler;
    $('#topbarActions').onclick = handler;
    $('#catalogForm').addEventListener('change', () => { collect(); save(); });
  }

  // ---------------------------------------------------------------- settings

  function renderSettings() {
    setTitle('Settings');
    const s = data.settings;
    const co = s.company;
    actions('<button class="btn btn-gold btn-sm" type="button" data-act="save">Save settings</button>');
    $('#app').innerHTML = `
      <form id="settingsForm" class="settings">
        <section class="panel">
          <h2>Business details</h2>
          <div class="grid-3">
            <label>Business name<input data-co="name" value="${esc(co.name)}"></label>
            <label>Tagline<input data-co="tagline" value="${esc(co.tagline)}"></label>
            <label>TPIN<input data-co="tpin" value="${esc(co.tpin)}" placeholder="Shown on every document"></label>
            <label>Address line 1<input data-co="address1" value="${esc(co.address1)}"></label>
            <label>Address line 2<input data-co="address2" value="${esc(co.address2)}"></label>
            <label>Website<input data-co="website" value="${esc(co.website)}"></label>
            <label>Phone<input data-co="phone" value="${esc(co.phone)}"></label>
            <label>Email<input data-co="email" value="${esc(co.email)}"></label>
            <label>Logo <span class="muted">(PNG/JPG/SVG, max 500KB)</span><input type="file" id="logoInput" accept="image/png,image/jpeg,image/svg+xml,image/webp"></label>
          </div>
          ${co.logo ? `<div class="logo-preview"><img src="${esc(co.logo)}" alt="Logo"><button type="button" class="link danger" data-act="remove-logo">Remove logo</button></div>` : ''}
        </section>

        <section class="panel">
          <div class="panel-head"><h2>Banking &amp; Mobile Money details</h2>
            <span><button type="button" class="link" data-act="add-bank">+ Bank account</button> · <button type="button" class="link" data-act="add-mobile">+ Mobile Money</button></span></div>
          <p class="muted small">Ticked accounts are added to new documents by default. You can choose per document too.</p>
          ${s.banks.map((b) => `
            <div class="bank-row" data-bank="${b.id}">
              <div class="bank-row-head">
                <label class="check"><input type="checkbox" data-b="active" ${b.active ? 'checked' : ''}> Use by default</label>
                <span class="tag">${b.type === 'mobile' ? 'Mobile Money' : 'Bank'}</span>
                <button type="button" class="icon-btn danger" data-act="remove-bank" aria-label="Remove">✕</button>
              </div>
              <div class="grid-3">
                <label>Label<input data-b="label" value="${esc(b.label)}"></label>
                ${b.type === 'mobile' ? `
                  <label>Provider<select data-b="provider">${['MTN MoMo', 'Airtel Money', 'Zamtel Kwacha', 'Other'].map((p) => `<option ${b.provider === p ? 'selected' : ''}>${p}</option>`).join('')}</select></label>
                  <label>Mobile number<input data-b="accountNumber" value="${esc(b.accountNumber)}" placeholder="e.g. 0972015689"></label>
                  <label>Registered name<input data-b="accountName" value="${esc(b.accountName)}"></label>` : `
                  <label>Bank name<input data-b="bankName" value="${esc(b.bankName)}" placeholder="e.g. Zanaco, Stanbic, FNB"></label>
                  <label>Account name<input data-b="accountName" value="${esc(b.accountName)}"></label>
                  <label>Account number<input data-b="accountNumber" value="${esc(b.accountNumber)}"></label>
                  <label>Branch<input data-b="branch" value="${esc(b.branch)}"></label>
                  <label>Branch / sort code<input data-b="branchCode" value="${esc(b.branchCode)}"></label>
                  <label>SWIFT / BIC<input data-b="swift" value="${esc(b.swift)}"></label>
                  <label>Account currency<select data-b="currency">${s.currencies.map((c) => `<option ${b.currency === c.code ? 'selected' : ''}>${c.code}</option>`).join('')}</select></label>`}
              </div>
            </div>`).join('') || '<p class="muted">No payment details yet.</p>'}
        </section>

        <section class="panel">
          <div class="panel-head"><h2>Tax types</h2><button type="button" class="link" data-act="add-tax">+ Add tax type</button></div>
          <p class="muted small">“Charged” taxes are added on top of each line (e.g. VAT). “Withheld” taxes are deducted by the client from the amount payable (e.g. Withholding Tax). Confirm current rates with ZRA before use.</p>
          <div class="table-wrap"><table class="table">
            <thead><tr><th>Name</th><th>Rate %</th><th>Type</th><th>Default</th><th></th></tr></thead>
            <tbody>${s.taxes.map((t) => `
              <tr data-tax="${t.id}">
                <td><input data-t="name" value="${esc(t.name)}" aria-label="Tax name"></td>
                <td><input type="number" data-t="rate" min="0" max="100" step="0.01" value="${esc(t.rate)}" aria-label="Rate"></td>
                <td><select data-t="kind" aria-label="Type"><option value="add" ${t.kind === 'add' ? 'selected' : ''}>Charged</option><option value="withhold" ${t.kind === 'withhold' ? 'selected' : ''}>Withheld</option></select></td>
                <td>${t.kind === 'add' ? `<input type="radio" name="defaultTax" value="${t.id}" ${s.defaultTaxId === t.id ? 'checked' : ''} aria-label="Default tax">` : ''}</td>
                <td><button type="button" class="icon-btn danger" data-act="remove-tax" aria-label="Remove">✕</button></td>
              </tr>`).join('')}</tbody>
          </table></div>
        </section>

        <section class="panel">
          <div class="panel-head"><h2>Currencies</h2><button type="button" class="link" data-act="add-currency">+ Add currency</button></div>
          <p class="muted small">Exchange rate = how many Kwacha one unit is worth. When set, invoices in that currency also show the ZMW equivalent.</p>
          <div class="table-wrap"><table class="table">
            <thead><tr><th>Code</th><th>Symbol</th><th>Name</th><th>Rate to ZMW</th><th>Default</th><th></th></tr></thead>
            <tbody>${s.currencies.map((c, i) => `
              <tr data-cur="${i}">
                <td><input data-k="code" value="${esc(c.code)}" maxlength="3" aria-label="Code" ${c.code === 'ZMW' ? 'readonly' : ''}></td>
                <td><input data-k="symbol" value="${esc(c.symbol)}" maxlength="4" aria-label="Symbol"></td>
                <td><input data-k="name" value="${esc(c.name)}" aria-label="Name"></td>
                <td>${c.code === 'ZMW' ? '1' : `<input type="number" data-k="rate" min="0" step="0.0001" value="${esc(c.rate ?? '')}" placeholder="e.g. 26.5" aria-label="Rate">`}</td>
                <td><input type="radio" name="defaultCurrency" value="${esc(c.code)}" ${s.defaultCurrency === c.code ? 'checked' : ''} aria-label="Default currency"></td>
                <td>${c.code === 'ZMW' ? '' : '<button type="button" class="icon-btn danger" data-act="remove-currency" aria-label="Remove">✕</button>'}</td>
              </tr>`).join('')}</tbody>
          </table></div>
        </section>

        <section class="panel">
          <h2>Numbering &amp; defaults</h2>
          <div class="grid-3">
            ${Object.entries(DOC_TYPES).map(([k, v]) => `<label>Next ${v.label.toLowerCase()} number<input type="number" min="1" step="1" data-num="${k}" value="${esc(s.numbering[k] || 1)}"><span class="muted small">Next: ${esc(nextNumber(k))}</span></label>`).join('')}
            <label>Invoice due in (days)<input type="number" min="0" data-s="dueDays" value="${esc(s.dueDays)}"></label>
            <label>Quotation valid for (days)<input type="number" min="0" data-s="validDays" value="${esc(s.validDays)}"></label>
            <label>Default deposit (%)<input type="number" min="0" max="100" data-s="depositPercent" value="${esc(s.depositPercent)}"></label>
          </div>
          <label>Default terms &amp; conditions<textarea data-s="terms" rows="4">${esc(s.terms)}</textarea></label>
          <label>Document footer<input data-s="footer" value="${esc(s.footer)}"></label>
        </section>

        <section class="panel">
          <h2>Backup &amp; restore</h2>
          <p class="muted small">Everything is stored in this browser only. Export a backup regularly and to move to another computer.</p>
          <div class="form-actions">
            <button type="button" class="btn btn-gold btn-sm" data-act="export">Export backup (.json)</button>
            <label class="btn btn-outline btn-sm file-btn">Import backup<input type="file" id="importInput" accept="application/json,.json"></label>
            <button type="button" class="btn btn-danger btn-sm" data-act="reset">Erase all data</button>
          </div>
        </section>
      </form>`;

    const collect = () => {
      $$('[data-co]').forEach((i) => { co[i.dataset.co] = i.value.trim(); });
      $$('[data-bank]').forEach((row) => {
        const b = s.banks.find((x) => x.id === row.dataset.bank);
        $$('[data-b]', row).forEach((i) => { b[i.dataset.b] = i.type === 'checkbox' ? i.checked : i.value.trim(); });
      });
      $$('[data-tax]').forEach((row) => {
        const t = s.taxes.find((x) => x.id === row.dataset.tax);
        $$('[data-t]', row).forEach((i) => { t[i.dataset.t] = i.dataset.t === 'rate' ? num(i.value) : i.value.trim(); });
      });
      const dt = $('input[name="defaultTax"]:checked');
      if (dt) s.defaultTaxId = dt.value;
      $$('[data-cur]').forEach((row) => {
        const c = s.currencies[Number(row.dataset.cur)];
        $$('[data-k]', row).forEach((i) => { c[i.dataset.k] = i.dataset.k === 'rate' ? (i.value === '' ? null : num(i.value)) : i.dataset.k === 'code' ? i.value.trim().toUpperCase() : i.value.trim(); });
      });
      const dc = $('input[name="defaultCurrency"]:checked');
      if (dc) s.defaultCurrency = dc.value;
      $$('[data-num]').forEach((i) => { s.numbering[i.dataset.num] = Math.max(1, parseInt(i.value, 10) || 1); });
      $$('[data-s]').forEach((i) => { s[i.dataset.s] = i.type === 'number' ? num(i.value) : i.value; });
    };

    const handler = (e) => {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;
      const act = btn.dataset.act;
      collect();
      switch (act) {
        case 'save': save(); toast('Settings saved.'); return;
        case 'add-bank': s.banks.push({ id: uid(), type: 'bank', label: 'Bank account', bankName: '', accountName: co.name, accountNumber: '', branch: '', branchCode: '', swift: '', currency: 'ZMW', active: true }); break;
        case 'add-mobile': s.banks.push({ id: uid(), type: 'mobile', label: 'Mobile Money', provider: 'MTN MoMo', accountName: co.name, accountNumber: '', active: true }); break;
        case 'remove-bank': s.banks = s.banks.filter((b) => b.id !== btn.closest('[data-bank]').dataset.bank); break;
        case 'add-tax': s.taxes.push({ id: uid(), name: 'New tax', rate: 0, kind: 'add' }); break;
        case 'remove-tax': {
          const id = btn.closest('[data-tax]').dataset.tax;
          const used = data.docs.some((d) => d.withholdingId === id || d.items.some((i) => i.taxId === id));
          if (used) { toast('This tax is used on saved documents, so it cannot be removed. Rename it instead.', true); return; }
          if (s.defaultTaxId === id) { toast('Choose another default tax first.', true); return; }
          s.taxes = s.taxes.filter((t) => t.id !== id);
          data.catalog.forEach((c) => { if (c.taxId === id) c.taxId = s.defaultTaxId; });
          break;
        }
        case 'add-currency': s.currencies.push({ code: 'NEW', symbol: '', name: 'New currency', rate: null }); break;
        case 'remove-currency': {
          const c = s.currencies[Number(btn.closest('[data-cur]').dataset.cur)];
          if (data.docs.some((d) => d.currency === c.code)) { toast(`${c.code} is used on saved documents, so it cannot be removed.`, true); return; }
          if (s.defaultCurrency === c.code) s.defaultCurrency = 'ZMW';
          s.currencies = s.currencies.filter((x) => x !== c);
          break;
        }
        case 'remove-logo': co.logo = ''; break;
        case 'export': download(`brandbay-invoicing-backup-${today()}.json`, JSON.stringify(data, null, 2), 'application/json'); toast('Backup downloaded.'); return;
        case 'reset':
          if (confirm('Erase ALL invoices, clients and settings from this browser? Export a backup first if unsure.') && confirm('Are you absolutely sure?')) {
            data = defaultData();
            save();
            location.hash = '#/dashboard';
            toast('All data erased.');
          }
          return;
        default: return;
      }
      save();
      renderSettings();
    };
    $('#app').onclick = handler;
    $('#topbarActions').onclick = handler;

    $('#logoInput').addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      if (file.size > 500 * 1024) { toast('Logo must be 500KB or smaller.', true); return; }
      const reader = new FileReader();
      reader.onload = () => { collect(); co.logo = reader.result; if (save()) renderSettings(); };
      reader.readAsDataURL(file);
    });
    $('#importInput').addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const imported = JSON.parse(reader.result);
          if (!imported || !imported.settings || !Array.isArray(imported.docs) || !Array.isArray(imported.clients) || !Array.isArray(imported.catalog)) throw new Error('bad file');
          if (!confirm(`Replace current data with this backup (${imported.docs.length} documents, ${imported.clients.length} clients)?`)) return;
          data = imported;
          save();
          toast('Backup restored.');
          location.hash = '#/dashboard';
          route();
        } catch (err) {
          toast('That file is not a BrandBay Invoicing backup.', true);
        }
      };
      reader.readAsText(file);
    });
  }

  // ---------------------------------------------------------------- shell

  function closeMenu() {
    $('#sidebar').classList.remove('open');
    $('#menuBtn').setAttribute('aria-expanded', 'false');
  }
  $('#menuBtn').addEventListener('click', () => {
    const open = $('#sidebar').classList.toggle('open');
    $('#menuBtn').setAttribute('aria-expanded', String(open));
  });

  // Clicking a table row opens the document.
  document.addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a, button')) location.hash = tr.dataset.href;
  });

  window.addEventListener('hashchange', () => {
    route();
    if (pendingClient && draft && !doc(draft.id)) {
      draft.clientId = pendingClient;
      const sel = $('select[name="clientId"]');
      if (sel) sel.value = pendingClient;
      drawPreview();
    }
    pendingClient = null;
  });
  route();

  // Expose the totals maths for the test page.
  window.BrandBayInvoicing = { totals, defaultData };
})();
