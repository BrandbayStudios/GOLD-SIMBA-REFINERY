// Invoices, quotations, proforma invoices and receipts: maths, numbering, persistence and defaults.
const crypto = require('node:crypto');
const { all, get, run, transaction } = require('./db');
const lib = require('./lib');

const DOC_TYPES = {
  invoice: { label: 'Invoice', prefix: 'INV' },
  quotation: { label: 'Quotation', prefix: 'QUO' },
  proforma: { label: 'Proforma Invoice', prefix: 'PRO' },
  receipt: { label: 'Receipt', prefix: 'REC' },
};

const STATUSES = {
  draft: 'Draft', sent: 'Sent', partial: 'Partially paid', paid: 'Paid', overdue: 'Overdue',
  accepted: 'Accepted', declined: 'Declined', cancelled: 'Cancelled',
};

// Statuses staff can set by hand; partial/paid/overdue are derived from payments and dates.
const MANUAL_STATUSES = {
  invoice: ['draft', 'sent', 'cancelled'],
  proforma: ['draft', 'sent', 'cancelled'],
  quotation: ['draft', 'sent', 'accepted', 'declined', 'cancelled'],
  receipt: ['paid'],
};

const PAYMENT_METHODS = ['Mobile Money', 'Bank transfer', 'Cash', 'Cheque', 'Card'];

const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const num = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
const clampPct = (v) => Math.min(100, Math.max(0, num(v)));

function today() {
  const d = new Date();
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

function addDays(iso, days) {
  const d = new Date(`${iso}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + Number(days || 0));
  return d.toISOString().slice(0, 10);
}

function isIsoDate(v) {
  return /^\d{4}-\d{2}-\d{2}$/.test(String(v || '')) && !Number.isNaN(new Date(`${v}T00:00:00Z`).getTime());
}

function money(amount, symbol) {
  const n = round2(amount);
  return `${n < 0 ? '-' : ''}${symbol ?? ''}${Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// ---------------------------------------------------------------- maths

function totals(inv, items, payments = []) {
  const lines = items.map((it) => {
    const gross = round2(num(it.quantity) * num(it.unit_price));
    const lineDiscount = round2((gross * clampPct(it.discount_percent)) / 100);
    const afterLine = round2(gross - lineDiscount);
    // The document-level discount is spread over the lines so tax is charged on the discounted amount.
    const docDiscount = round2((afterLine * clampPct(inv.discount_percent)) / 100);
    const net = round2(afterLine - docDiscount);
    const tax = round2((net * num(it.tax_rate)) / 100);
    return { gross, lineDiscount, afterLine, docDiscount, net, tax };
  });
  const subtotal = round2(lines.reduce((s, l) => s + l.afterLine, 0));
  const discount = round2(lines.reduce((s, l) => s + l.docDiscount, 0));
  const taxable = round2(subtotal - discount);
  const groups = new Map();
  items.forEach((it, i) => {
    if (!it.tax_name && !num(it.tax_rate)) return;
    const key = `${it.tax_name}|${num(it.tax_rate)}`;
    const g = groups.get(key) || { name: it.tax_name || 'Tax', rate: num(it.tax_rate), base: 0, amount: 0 };
    g.base = round2(g.base + lines[i].net);
    g.amount = round2(g.amount + lines[i].tax);
    groups.set(key, g);
  });
  const taxGroups = [...groups.values()];
  const taxTotal = round2(taxGroups.reduce((s, g) => s + g.amount, 0));
  const total = round2(taxable + taxTotal);
  const withholding = round2((taxable * num(inv.withholding_rate)) / 100);
  const payable = round2(total - withholding);
  const paid = round2(payments.reduce((s, p) => s + num(p.amount), 0));
  const balance = round2(payable - paid);
  const deposit = round2((payable * clampPct(inv.deposit_percent)) / 100);
  return { lines, subtotal, discount, taxable, taxGroups, taxTotal, total, withholding, payable, paid, balance, deposit };
}

function effectiveStatus(inv, t) {
  if (inv.status === 'cancelled' || inv.status === 'draft') return inv.status;
  if (inv.type === 'receipt') return 'paid';
  if (inv.type === 'invoice' || inv.type === 'proforma') {
    const overdue = inv.type === 'invoice' && inv.due_date && inv.due_date < today();
    if (t.paid > 0 && t.balance <= 0.004) return 'paid';
    if (t.paid > 0) return overdue ? 'overdue' : 'partial';
    if (overdue) return 'overdue';
  }
  return inv.status;
}

// ---------------------------------------------------------------- loading

function load(where, ...params) {
  const inv = get(`SELECT * FROM invoices WHERE ${where}`, ...params);
  if (!inv) return null;
  const items = all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position, id', inv.id);
  const payments = all('SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY paid_on, id', inv.id);
  const t = totals(inv, items, payments);
  const ids = safeJson(inv.account_ids, []);
  const accounts = ids.length
    ? all(`SELECT * FROM payment_accounts WHERE id IN (${ids.map(() => '?').join(',')}) ORDER BY sort_order, id`, ...ids)
        .filter((a) => a.account_number)
    : [];
  return { inv, items, payments, t, accounts, status: effectiveStatus(inv, t), label: DOC_TYPES[inv.type].label };
}

function safeJson(text, fallback) {
  try { return JSON.parse(text); } catch { return fallback; }
}

// Lightweight list rows: one query for documents, one for items, one for payments.
function list(where = '1', params = [], { limit = 50, offset = 0 } = {}) {
  const docs = all(`SELECT * FROM invoices WHERE ${where} ORDER BY issue_date DESC, id DESC LIMIT ? OFFSET ?`, ...params, limit, offset);
  return withTotals(docs);
}

function withTotals(docs) {
  if (!docs.length) return [];
  const ids = docs.map((d) => d.id);
  const marks = ids.map(() => '?').join(',');
  const items = all(`SELECT * FROM invoice_items WHERE invoice_id IN (${marks})`, ...ids);
  const payments = all(`SELECT * FROM invoice_payments WHERE invoice_id IN (${marks})`, ...ids);
  return docs.map((inv) => {
    const t = totals(inv, items.filter((i) => i.invoice_id === inv.id), payments.filter((p) => p.invoice_id === inv.id));
    return { ...inv, t, effective: effectiveStatus(inv, t), label: DOC_TYPES[inv.type].label };
  });
}

// ---------------------------------------------------------------- numbering

function nextNumber(type) {
  const counter = Math.max(1, parseInt(lib.getSettings()[`invoice_next_${type}`], 10) || 1);
  const year = new Date().getFullYear();
  for (let n = counter; ; n++) {
    const number = `${DOC_TYPES[type].prefix}-${year}-${String(n).padStart(4, '0')}`;
    if (!get('SELECT 1 AS x FROM invoices WHERE number = ?', number)) return { number, n };
  }
}

// ---------------------------------------------------------------- reference data

function taxes() { return all('SELECT * FROM tax_rates ORDER BY sort_order, id'); }
function currencies() { return all('SELECT * FROM currencies ORDER BY sort_order, code'); }
function accounts() { return all('SELECT * FROM payment_accounts ORDER BY sort_order, id'); }
function catalog() {
  return all(`
    SELECT si.*, t.name AS tax_name, t.rate AS tax_rate
    FROM service_items si
    LEFT JOIN service_categories c ON c.id = si.category_id
    LEFT JOIN tax_rates t ON t.id = si.tax_id
    ORDER BY COALESCE(c.sort_order, 999), si.category_name, si.sort_order, si.id`);
}
function defaultTax() { return get("SELECT * FROM tax_rates WHERE kind = 'add' ORDER BY is_default DESC, sort_order LIMIT 1"); }
function defaultCurrency() { return get('SELECT * FROM currencies ORDER BY is_default DESC, sort_order LIMIT 1'); }

function invoiceSettings() {
  const s = lib.getSettings();
  return {
    tpin: s.invoice_tpin || '',
    dueDays: num(s.invoice_due_days ?? 14),
    validDays: num(s.invoice_valid_days ?? 30),
    depositPercent: num(s.invoice_deposit_percent ?? 70),
    terms: s.invoice_terms || '',
    footer: s.invoice_footer || '',
  };
}

// ---------------------------------------------------------------- create / update

// Reads the editor form. Line fields arrive as parallel arrays (item_name[], item_qty[], …).
function readForm(body) {
  const arr = (k) => (body[k] === undefined ? [] : [].concat(body[k]));
  const names = arr('item_name');
  const taxById = new Map(taxes().map((t) => [String(t.id), t]));
  const items = names.map((name, i) => {
    const t = taxById.get(String(arr('item_tax_id')[i]));
    return {
      position: i,
      category: lib.clean(arr('item_category')[i], 160),
      name: lib.clean(name, 300),
      description: lib.clean(arr('item_description')[i], 2000),
      quantity: num(arr('item_qty')[i]),
      unit: lib.clean(arr('item_unit')[i], 60),
      unit_price: round2(num(arr('item_price')[i])),
      discount_percent: clampPct(arr('item_discount')[i]),
      tax_name: t && t.kind === 'add' ? t.name : null,
      tax_rate: t && t.kind === 'add' ? num(t.rate) : 0,
    };
  }).filter((it) => it.name || it.unit_price);

  const type = DOC_TYPES[body.type] ? body.type : 'invoice';
  const cur = get('SELECT * FROM currencies WHERE code = ?', body.currency) || defaultCurrency();
  const wht = body.withholding_id ? get("SELECT * FROM tax_rates WHERE id = ? AND kind = 'withhold'", body.withholding_id) : null;
  const userId = body.user_id ? get("SELECT id FROM users WHERE id = ?", body.user_id)?.id ?? null : null;
  const requestId = body.request_id ? get('SELECT id FROM service_requests WHERE id = ?', body.request_id)?.id ?? null : null;
  const accountIds = arr('account_ids').map(Number).filter((id) => get('SELECT id FROM payment_accounts WHERE id = ?', id));
  const status = MANUAL_STATUSES[type].includes(body.status) ? body.status : MANUAL_STATUSES[type][0];

  const inv = {
    type,
    number: lib.clean(body.number, 40),
    status,
    user_id: userId,
    request_id: requestId,
    client_name: lib.clean(body.client_name, 160),
    client_company: lib.clean(body.client_company, 160),
    client_email: lib.clean(body.client_email, 200),
    client_phone: lib.clean(body.client_phone, 40),
    client_address: lib.clean(body.client_address, 500),
    client_tpin: lib.clean(body.client_tpin, 40),
    issue_date: isIsoDate(body.issue_date) ? body.issue_date : today(),
    due_date: type === 'receipt' ? null : isIsoDate(body.due_date) ? body.due_date : null,
    reference: lib.clean(body.reference, 120),
    currency: cur.code,
    currency_symbol: cur.symbol,
    rate_to_zmw: cur.code === 'ZMW' ? 1 : cur.rate_to_zmw,
    discount_percent: clampPct(body.discount_percent),
    withholding_name: wht ? wht.name : null,
    withholding_rate: wht ? num(wht.rate) : 0,
    deposit_percent: body.show_deposit ? clampPct(body.deposit_percent) : 0,
    account_ids: JSON.stringify(accountIds),
    notes: lib.clean(body.notes, 5000),
    terms: lib.clean(body.terms, 5000),
  };

  const errors = [];
  if (!inv.client_name) errors.push('Enter who this is billed to.');
  if (inv.client_email && !lib.isEmail(inv.client_email)) errors.push('The client email address is not valid.');
  if (!items.some((it) => it.name)) errors.push('Add at least one service or item.');
  if (items.some((it) => it.quantity < 0 || it.unit_price < 0)) errors.push('Quantities and prices cannot be negative.');
  return { inv, items, errors };
}

const INV_COLUMNS = [
  'type', 'number', 'status', 'user_id', 'request_id', 'client_name', 'client_company', 'client_email', 'client_phone',
  'client_address', 'client_tpin', 'issue_date', 'due_date', 'reference', 'currency', 'currency_symbol', 'rate_to_zmw',
  'discount_percent', 'withholding_name', 'withholding_rate', 'deposit_percent', 'account_ids', 'notes', 'terms',
];

function insertItems(invoiceId, items) {
  run('DELETE FROM invoice_items WHERE invoice_id = ?', invoiceId);
  items.forEach((it, i) => run(
    `INSERT INTO invoice_items (invoice_id, position, category, name, description, quantity, unit, unit_price, discount_percent, tax_name, tax_rate)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    invoiceId, i, it.category, it.name, it.description, it.quantity, it.unit, it.unit_price, it.discount_percent, it.tax_name, it.tax_rate,
  ));
}

// Creates a document. Takes the number from the counter when none is given (or it is taken) and advances the counter.
function create(inv, items, { createdBy = null, sourceId = null, payments = [] } = {}) {
  return transaction(() => {
    let number = inv.number;
    const auto = nextNumber(inv.type);
    if (!number || get('SELECT 1 AS x FROM invoices WHERE number = ?', number)) number = auto.number;
    if (number === auto.number) lib.setSetting(`invoice_next_${inv.type}`, String(auto.n + 1));
    const row = { ...inv, number };
    const { lastInsertRowid: id } = run(
      `INSERT INTO invoices (${INV_COLUMNS.join(', ')}, public_token, source_id, created_by)
       VALUES (${INV_COLUMNS.map(() => '?').join(', ')}, ?, ?, ?)`,
      ...INV_COLUMNS.map((c) => row[c] ?? null), crypto.randomBytes(24).toString('hex'), sourceId, createdBy,
    );
    insertItems(id, items);
    for (const p of payments) {
      run('INSERT INTO invoice_payments (invoice_id, paid_on, amount, method, reference, recorded_by) VALUES (?, ?, ?, ?, ?, ?)',
        id, p.paid_on, p.amount, p.method, p.reference, p.recorded_by ?? createdBy);
    }
    return Number(id);
  });
}

function update(id, inv, items) {
  transaction(() => {
    const cols = INV_COLUMNS.filter((c) => c !== 'type');
    run(`UPDATE invoices SET ${cols.map((c) => `${c} = ?`).join(', ')}, updated_at = datetime('now') WHERE id = ?`,
      ...cols.map((c) => inv[c] ?? null), id);
    insertItems(id, items);
  });
}

// A blank document pre-filled from settings, and optionally from a service request, a client or another document.
function draft(type, { request, user, source } = {}) {
  const s = invoiceSettings();
  const cur = defaultCurrency();
  const tax = defaultTax();
  const inv = {
    type,
    number: nextNumber(type).number,
    status: type === 'receipt' ? 'paid' : 'draft',
    user_id: null,
    request_id: null,
    client_name: '', client_company: '', client_email: '', client_phone: '', client_address: '', client_tpin: '',
    issue_date: today(),
    due_date: type === 'receipt' ? null : addDays(today(), type === 'quotation' ? s.validDays : s.dueDays),
    reference: '',
    currency: cur.code,
    currency_symbol: cur.symbol,
    discount_percent: 0,
    withholding_name: null,
    withholding_rate: 0,
    deposit_percent: type === 'receipt' ? 0 : s.depositPercent,
    account_ids: JSON.stringify(accounts().filter((a) => a.is_default).map((a) => a.id)),
    notes: '',
    terms: type === 'receipt' ? '' : s.terms,
  };
  let items = [];

  if (source) {
    Object.assign(inv, {
      user_id: source.inv.user_id, request_id: source.inv.request_id,
      client_name: source.inv.client_name, client_company: source.inv.client_company, client_email: source.inv.client_email,
      client_phone: source.inv.client_phone, client_address: source.inv.client_address, client_tpin: source.inv.client_tpin,
      currency: source.inv.currency, currency_symbol: source.inv.currency_symbol, reference: source.inv.number,
      discount_percent: source.inv.discount_percent, withholding_name: source.inv.withholding_name,
      withholding_rate: source.inv.withholding_rate, account_ids: source.inv.account_ids, notes: source.inv.notes,
    });
    items = source.items.map(({ id, invoice_id, ...it }) => it);
  }
  if (request) {
    Object.assign(inv, {
      request_id: request.id, user_id: request.user_id, reference: request.reference,
      client_name: request.name, client_company: request.company || '', client_email: request.email, client_phone: request.phone || '',
    });
    // "Category — Sub-service" from the apply form maps onto a priced catalogue line when possible.
    const [catName, subName] = request.service.split(' — ');
    const match = catalog().find((c) => c.name === (subName || '') && c.category_name === catName)
      || catalog().find((c) => c.name === request.service);
    items = [{
      category: match ? match.category_name : subName ? catName : '',
      name: match ? match.name : subName || request.service,
      description: '',
      quantity: 1,
      unit: match ? match.unit : '',
      unit_price: request.quote_amount ?? (match ? match.price : 0),
      discount_percent: 0,
      tax_name: (match && match.tax_name) || tax?.name || null,
      tax_rate: match && match.tax_name ? match.tax_rate : tax?.rate ?? 0,
    }];
  }
  if (user && !request && !source) {
    Object.assign(inv, { user_id: user.id, client_name: user.name, client_company: user.company || '', client_email: user.email, client_phone: user.phone || '' });
  }
  if (!items.length) {
    items = [{ category: '', name: '', description: '', quantity: 1, unit: '', unit_price: 0, discount_percent: 0, tax_name: tax?.name ?? null, tax_rate: tax?.rate ?? 0 }];
  }
  return { inv, items };
}

// Matches a stored line back to a tax rate row for the editor's dropdown.
function taxIdFor(item, taxList) {
  const exact = taxList.find((t) => t.kind === 'add' && t.name === item.tax_name && num(t.rate) === num(item.tax_rate));
  return exact ? exact.id : taxList.find((t) => t.kind === 'add' && num(t.rate) === num(item.tax_rate))?.id ?? '';
}

// ---------------------------------------------------------------- defaults

const SUB_SERVICE_UNITS = { 'Printing & Artistry Solutions': 'per unit' };

function ensureDefaults() {
  transaction(() => {
    if (!get('SELECT 1 AS x FROM tax_rates LIMIT 1')) {
      [
        ['VAT (standard rate)', 16, 'add', 1],
        ['VAT zero-rated', 0, 'add', 0],
        ['VAT exempt', 0, 'add', 0],
        ['Turnover Tax', 5, 'add', 0],
        ['No tax', 0, 'add', 0],
        ['Withholding Tax', 15, 'withhold', 0],
      ].forEach(([name, rate, kind, isDefault], i) =>
        run('INSERT INTO tax_rates (name, rate, kind, is_default, sort_order) VALUES (?, ?, ?, ?, ?)', name, rate, kind, isDefault, i));
    }
    if (!get('SELECT 1 AS x FROM currencies LIMIT 1')) {
      [
        ['ZMW', 'K', 'Zambian Kwacha', 1, 1],
        ['USD', '$', 'US Dollar', null, 0],
        ['ZAR', 'R', 'South African Rand', null, 0],
        ['GBP', '£', 'British Pound', null, 0],
        ['EUR', '€', 'Euro', null, 0],
      ].forEach(([code, symbol, name, rate, isDefault], i) =>
        run('INSERT INTO currencies (code, symbol, name, rate_to_zmw, is_default, sort_order) VALUES (?, ?, ?, ?, ?, ?)', code, symbol, name, rate, isDefault, i));
    }
    if (!get('SELECT 1 AS x FROM payment_accounts LIMIT 1')) {
      // Account numbers are left blank on purpose: accounts without a number never print.
      run("INSERT INTO payment_accounts (type, label, account_name, currency, is_default, sort_order) VALUES ('bank', 'Bank account', 'BrandBay Innovation Studios', 'ZMW', 1, 0)");
      run("INSERT INTO payment_accounts (type, label, provider, account_name, is_default, sort_order) VALUES ('mobile', 'MTN Mobile Money', 'MTN MoMo', 'BrandBay Innovation Studios', 1, 1)");
      run("INSERT INTO payment_accounts (type, label, provider, account_name, is_default, sort_order) VALUES ('mobile', 'Airtel Money', 'Airtel Money', 'BrandBay Innovation Studios', 0, 2)");
    }
    if (!get('SELECT 1 AS x FROM service_items LIMIT 1')) {
      const vat = defaultTax();
      let n = 0;
      for (const cat of all('SELECT * FROM service_categories ORDER BY sort_order, id')) {
        for (const name of lib.lines(cat.items)) {
          run('INSERT INTO service_items (category_id, category_name, name, unit, price, tax_id, sort_order) VALUES (?, ?, ?, ?, 0, ?, ?)',
            cat.id, cat.name, name, SUB_SERVICE_UNITS[cat.name] || 'per project', vat?.id ?? null, n++);
        }
      }
    }
    const s = lib.getSettings();
    const defaults = {
      invoice_tpin: '',
      invoice_due_days: '14',
      invoice_valid_days: '30',
      invoice_deposit_percent: '70',
      invoice_terms: 'Payment structure: 70% upfront deposit and 30% upon completion. Printing jobs may require full upfront payment.\nEditable/source files are provided on request after full payment.\nPost-delivery changes are treated as a new request.',
      invoice_footer: 'Thank you for choosing BrandBay Innovation Studios.',
      invoice_next_invoice: '1', invoice_next_quotation: '1', invoice_next_proforma: '1', invoice_next_receipt: '1',
    };
    for (const [k, v] of Object.entries(defaults)) if (!(k in s)) lib.setSetting(k, v);
  });
}

module.exports = {
  DOC_TYPES, STATUSES, MANUAL_STATUSES, PAYMENT_METHODS,
  round2, num, today, addDays, isIsoDate, money,
  totals, effectiveStatus, load, list, withTotals, nextNumber,
  taxes, currencies, accounts, catalog, defaultTax, defaultCurrency, invoiceSettings,
  readForm, create, update, draft, taxIdFor, ensureDefaults,
};
