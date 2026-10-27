// Admin: invoices, quotations, proformas and receipts, plus their prices and settings.
const express = require('express');
const { all, get, run, transaction } = require('../db');
const lib = require('../lib');
const inv = require('../invoicing');
const mailer = require('../mailer');
const config = require('../config');
const { flash, requireAdmin } = require('../middleware');

const router = express.Router();
const PAGE_SIZE = 25;

function editorData(doc, extra = {}) {
  const taxes = inv.taxes();
  return {
    doc,
    taxes,
    currencies: inv.currencies(),
    accounts: inv.accounts(),
    catalog: inv.catalog(),
    clients: all("SELECT id, name, email, phone, company FROM users WHERE role = 'client' ORDER BY name"),
    accountIds: JSON.parse(doc.inv.account_ids || '[]'),
    taxIdFor: (item) => inv.taxIdFor(item, taxes),
    withholdingId: doc.inv.withholding_name
      ? taxes.find((t) => t.kind === 'withhold' && t.name === doc.inv.withholding_name)?.id
        ?? taxes.find((t) => t.kind === 'withhold' && t.rate === doc.inv.withholding_rate)?.id ?? ''
      : '',
    inv,
    // Payment fields for receipts: the receipt's own payment when editing, else today's date.
    payment: doc.payment || (doc.payments && doc.payments[0]) || { paid_on: doc.inv.issue_date || inv.today(), method: inv.PAYMENT_METHODS[0], reference: '' },
    ...extra,
  };
}

// Receipts issued from an invoice payment mirror that payment, so only standalone receipts can be edited.
const linkedReceipt = (doc) => doc.inv.type === 'receipt' && Boolean(doc.inv.source_id);

async function emailReceipt(receiptId) {
  const r = inv.load('id = ?', receiptId);
  if (!r || !lib.isEmail(r.inv.client_email)) return false;
  const info = r.receipt;
  await mailer.send({
    to: r.inv.client_email,
    subject: `Receipt ${r.inv.number} from BrandBay Innovation Studios`,
    text: `Dear ${r.inv.client_name},\n\nThank you for your payment of ${inv.money(info.amount, r.inv.currency_symbol)}.${info.invoice ? `\nIt has been applied to ${info.invoice.label.toLowerCase()} ${info.invoice.number}. Balance remaining: ${inv.money(info.invoice.balance, r.inv.currency_symbol)}.` : ''}\n\nView or download your receipt here:\n${config.baseUrl}/invoice/${r.inv.public_token}\n\nKind regards,\nBrandBay Innovation Studios`,
  });
  run("UPDATE invoices SET sent_at = datetime('now') WHERE id = ?", receiptId);
  return true;
}

function find(req) {
  return inv.load('id = ?', req.params.id);
}

// ---------------------------------------------------------------- list

function filters(req) {
  const where = [];
  const params = [];
  const type = inv.DOC_TYPES[req.query.type] ? req.query.type : '';
  const q = lib.clean(req.query.q, 100);
  if (type) { where.push('type = ?'); params.push(type); }
  if (q) {
    where.push('(number LIKE ? OR client_name LIKE ? OR client_company LIKE ? OR client_email LIKE ? OR reference LIKE ?)');
    params.push(...Array(5).fill(`%${q}%`));
  }
  return { sql: where.join(' AND ') || '1', params, type, q, status: inv.STATUSES[req.query.status] ? req.query.status : '' };
}

router.get('/', (req, res) => {
  const f = filters(req);
  // Status partly depends on payments and dates, so it is filtered after totals are computed.
  let docs = inv.list(f.sql, f.params, { limit: 100000 });
  if (f.status) docs = docs.filter((d) => d.effective === f.status);
  const total = docs.length;
  const p = Math.max(1, parseInt(req.query.page, 10) || 1);
  const cur = inv.defaultCurrency();
  const live = inv.list("type = 'invoice' AND status NOT IN ('draft','cancelled') AND currency = ?", [cur.code], { limit: 100000 });
  const summary = {
    symbol: cur.symbol,
    invoiced: live.reduce((s, d) => s + d.t.payable, 0),
    received: live.reduce((s, d) => s + d.t.paid, 0),
    outstanding: live.reduce((s, d) => s + Math.max(0, d.t.balance), 0),
    overdue: live.filter((d) => d.effective === 'overdue').reduce((s, d) => s + Math.max(0, d.t.balance), 0),
  };
  res.render('admin/invoices', {
    title: 'Invoices & quotes',
    docs: docs.slice((p - 1) * PAGE_SIZE, p * PAGE_SIZE),
    filters: f, page: p, pages: Math.ceil(total / PAGE_SIZE), total, summary, inv,
  });
});

router.get('/export.csv', (req, res) => {
  const f = filters(req);
  let docs = inv.list(f.sql, f.params, { limit: 100000 });
  if (f.status) docs = docs.filter((d) => d.effective === f.status);
  const rows = docs.map((d) => ({
    number: d.number, type: d.label, status: inv.STATUSES[d.effective], client: d.client_company || d.client_name,
    email: d.client_email, issue_date: d.issue_date, due_date: d.due_date, currency: d.currency,
    subtotal: d.t.subtotal, discount: d.t.discount, tax: d.t.taxTotal, total: d.t.total,
    withholding: d.t.withholding, payable: d.t.payable, paid: d.t.paid, balance: d.t.balance, reference: d.reference,
  }));
  const cols = Object.keys(rows[0] || { number: '' });
  const esc = (v) => {
    let s = String(v ?? '');
    if (/^[=+\-@]/.test(s) && Number.isNaN(Number(s))) s = `'${s}`;
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  res.attachment('invoices.csv').type('text/csv').send([cols.join(','), ...rows.map((r) => cols.map((c) => esc(r[c])).join(','))].join('\n'));
});

// ---------------------------------------------------------------- prices (catalogue)

router.get('/catalog', (req, res) => {
  res.render('admin/invoice-catalog', {
    title: 'Service prices',
    items: inv.catalog(),
    categories: all('SELECT id, name FROM service_categories ORDER BY sort_order, id'),
    taxes: inv.taxes().filter((t) => t.kind === 'add'),
    currency: inv.defaultCurrency(),
  });
});

router.post('/catalog', (req, res) => {
  const rows = parseKeyed(req.body, 'item');
  const taxIds = new Set(inv.taxes().map((t) => String(t.id)));
  transaction(() => {
    for (const [key, r] of Object.entries(rows)) {
      const name = lib.clean(r.name, 200);
      const price = inv.round2(Math.max(0, inv.num(r.price)));
      const taxId = taxIds.has(String(r.tax_id)) ? Number(r.tax_id) : null;
      const unit = lib.clean(r.unit, 60);
      if (key === 'new') {
        const cat = get('SELECT id, name FROM service_categories WHERE id = ?', r.category_id);
        if (name && cat) {
          run('INSERT INTO service_items (category_id, category_name, name, unit, price, tax_id, sort_order) VALUES (?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM service_items))',
            cat.id, cat.name, name, unit, price, taxId);
        }
      } else if (r.delete) {
        run('DELETE FROM service_items WHERE id = ?', key);
      } else if (name) {
        run('UPDATE service_items SET name = ?, unit = ?, price = ?, tax_id = ? WHERE id = ?', name, unit, price, taxId, key);
      }
    }
  });
  flash(req, 'success', 'Prices saved.');
  res.redirect('/admin/invoices/catalog');
});

// ---------------------------------------------------------------- settings (taxes, currencies, accounts, defaults)

// Parses fields named "<prefix>.<key>.<field>" into { key: { field: value } }.
function parseKeyed(body, prefix) {
  const out = {};
  for (const [name, value] of Object.entries(body)) {
    const m = name.match(new RegExp(`^${prefix}\\.(\\w+)\\.(\\w+)$`));
    if (!m) continue;
    (out[m[1]] = out[m[1]] || {})[m[2]] = Array.isArray(value) ? value[value.length - 1] : value;
  }
  return out;
}

router.get('/settings', requireAdmin, (req, res) => {
  res.render('admin/invoice-settings', {
    title: 'Invoice settings',
    taxes: inv.taxes(),
    currencies: inv.currencies(),
    accounts: inv.accounts(),
    s: inv.invoiceSettings(),
    counters: Object.fromEntries(Object.keys(inv.DOC_TYPES).map((t) => [t, lib.getSettings()[`invoice_next_${t}`] || '1'])),
    inv,
  });
});

router.post('/settings', requireAdmin, (req, res) => {
  const b = req.body;
  const errors = [];
  transaction(() => {
    // Taxes
    const taxes = parseKeyed(b, 'tax');
    for (const [key, t] of Object.entries(taxes)) {
      const name = lib.clean(t.name, 80);
      const rate = Math.min(100, Math.max(0, inv.num(t.rate)));
      const kind = t.kind === 'withhold' ? 'withhold' : 'add';
      if (key === 'new') {
        if (name) run('INSERT INTO tax_rates (name, rate, kind, sort_order) VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tax_rates))', name, rate, kind);
      } else if (t.delete) {
        run('UPDATE service_items SET tax_id = NULL WHERE tax_id = ?', key);
        run('DELETE FROM tax_rates WHERE id = ?', key);
      } else if (name) {
        run('UPDATE tax_rates SET name = ?, rate = ?, kind = ? WHERE id = ?', name, rate, kind, key);
      }
    }
    const defTax = get("SELECT id FROM tax_rates WHERE id = ? AND kind = 'add'", b.default_tax);
    if (defTax) {
      run('UPDATE tax_rates SET is_default = 0');
      run('UPDATE tax_rates SET is_default = 1 WHERE id = ?', defTax.id);
    }
    if (!get("SELECT 1 AS x FROM tax_rates WHERE kind = 'add' AND is_default = 1")) {
      run("UPDATE tax_rates SET is_default = 1 WHERE id = (SELECT id FROM tax_rates WHERE kind = 'add' ORDER BY sort_order, id LIMIT 1)");
    }
    if (!get("SELECT 1 AS x FROM tax_rates WHERE kind = 'add'")) {
      run("INSERT INTO tax_rates (name, rate, kind, is_default) VALUES ('No tax', 0, 'add', 1)");
      errors.push('At least one charged tax is needed, so "No tax" was added.');
    }

    // Currencies
    const curs = parseKeyed(b, 'cur');
    for (const [key, c] of Object.entries(curs)) {
      const code = lib.clean(c.code, 3).toUpperCase();
      const symbol = lib.clean(c.symbol, 4);
      const name = lib.clean(c.name, 60);
      const rate = c.rate === '' || c.rate === undefined ? null : Math.max(0, inv.num(c.rate)) || null;
      if (key === 'new') {
        if (/^[A-Z]{3}$/.test(code) && symbol && !get('SELECT 1 AS x FROM currencies WHERE code = ?', code)) {
          run('INSERT INTO currencies (code, symbol, name, rate_to_zmw, sort_order) VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM currencies))', code, symbol, name || code, rate);
        } else if (code || symbol) {
          errors.push('New currencies need a unique 3-letter code and a symbol.');
        }
      } else if (c.delete && key !== 'ZMW') {
        run('DELETE FROM currencies WHERE code = ?', key);
      } else {
        run('UPDATE currencies SET symbol = ?, name = ?, rate_to_zmw = ? WHERE code = ?', symbol || key, name || key, key === 'ZMW' ? 1 : rate, key);
      }
    }
    if (get('SELECT 1 AS x FROM currencies WHERE code = ?', b.default_currency)) {
      run('UPDATE currencies SET is_default = (code = ?)', b.default_currency);
    }

    // Bank and Mobile Money accounts
    const accts = parseKeyed(b, 'acc');
    const fields = ['label', 'bank_name', 'provider', 'account_name', 'account_number', 'branch', 'branch_code', 'swift', 'currency'];
    for (const [key, a] of Object.entries(accts)) {
      const v = Object.fromEntries(fields.map((f) => [f, lib.clean(a[f], 120)]));
      if (key === 'newbank' || key === 'newmobile') {
        if (v.account_number || v.bank_name) {
          run(`INSERT INTO payment_accounts (type, ${fields.join(', ')}, is_default, sort_order)
               VALUES (?, ${fields.map(() => '?').join(', ')}, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM payment_accounts))`,
            key === 'newbank' ? 'bank' : 'mobile', ...fields.map((f) => v[f] || (f === 'label' ? (key === 'newbank' ? v.bank_name || 'Bank account' : v.provider || 'Mobile Money') : null)), a.is_default ? 1 : 0);
        }
      } else if (a.delete) {
        run('DELETE FROM payment_accounts WHERE id = ?', key);
      } else {
        run(`UPDATE payment_accounts SET ${fields.map((f) => `${f} = ?`).join(', ')}, is_default = ? WHERE id = ?`,
          ...fields.map((f) => v[f] || null), a.is_default ? 1 : 0, key);
      }
    }

    // Defaults and numbering
    lib.setSetting('invoice_tpin', lib.clean(b.tpin, 40));
    lib.setSetting('invoice_due_days', String(Math.max(0, parseInt(b.due_days, 10) || 0)));
    lib.setSetting('invoice_valid_days', String(Math.max(0, parseInt(b.valid_days, 10) || 0)));
    lib.setSetting('invoice_deposit_percent', String(Math.min(100, Math.max(0, inv.num(b.deposit_percent)))));
    lib.setSetting('invoice_terms', lib.clean(b.terms, 5000));
    lib.setSetting('invoice_footer', lib.clean(b.footer, 300));
    for (const t of Object.keys(inv.DOC_TYPES)) {
      if (b[`next_${t}`] !== undefined) lib.setSetting(`invoice_next_${t}`, String(Math.max(1, parseInt(b[`next_${t}`], 10) || 1)));
    }
  });
  for (const e of errors) flash(req, 'error', e);
  flash(req, 'success', 'Invoice settings saved.');
  res.redirect('/admin/invoices/settings');
});

// ---------------------------------------------------------------- create / edit

router.get('/new', (req, res) => {
  const type = inv.DOC_TYPES[req.query.type] ? req.query.type : 'invoice';
  const request = req.query.request ? get('SELECT * FROM service_requests WHERE id = ?', req.query.request) : null;
  const user = req.query.user ? get('SELECT * FROM users WHERE id = ?', req.query.user) : null;
  const doc = inv.draft(type, { request, user });
  res.render('admin/invoice-form', { title: `New ${inv.DOC_TYPES[type].label.toLowerCase()}`, ...editorData(doc) });
});

router.post('/', (req, res) => {
  const { inv: data, items, errors, payment } = inv.readForm(req.body);
  if (errors.length) {
    return res.status(422).render('admin/invoice-form', { title: `New ${inv.DOC_TYPES[data.type].label.toLowerCase()}`, errors, ...editorData({ inv: data, items, payment }) });
  }
  // A suggested number taken by a colleague in the meantime is reassigned automatically; a hand-typed one is not.
  const autoPattern = new RegExp(`^${inv.DOC_TYPES[data.type].prefix}-\\d{4}-\\d{4,}$`);
  if (data.number && !autoPattern.test(data.number) && get('SELECT 1 AS x FROM invoices WHERE number = ?', data.number)) {
    return res.status(422).render('admin/invoice-form', { title: `New ${inv.DOC_TYPES[data.type].label.toLowerCase()}`, errors: [`Number ${data.number} is already used.`], ...editorData({ inv: data, items, payment }) });
  }
  const payments = payment ? [{ ...payment, amount: inv.totals(data, items).payable }] : [];
  const id = inv.create(data, items, { createdBy: req.user.id, payments });
  const saved = inv.load('id = ?', id);
  flash(req, 'success', `${saved.label} ${saved.inv.number} created.`);
  res.redirect(`/admin/invoices/${id}`);
});

router.get('/:id', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  res.render('admin/invoice', {
    title: `${doc.label} ${doc.inv.number}`,
    doc,
    inv,
    is: inv.invoiceSettings(),
    request: doc.inv.request_id ? get('SELECT id, reference, service FROM service_requests WHERE id = ?', doc.inv.request_id) : null,
    source: doc.inv.source_id ? get('SELECT id, number, type FROM invoices WHERE id = ?', doc.inv.source_id) : null,
    related: all('SELECT id, number, type FROM invoices WHERE source_id = ? ORDER BY id', doc.inv.id),
    publicUrl: `${config.baseUrl}/invoice/${doc.inv.public_token}`,
  });
});

router.get('/:id/edit', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  if (linkedReceipt(doc)) {
    flash(req, 'info', 'This receipt mirrors a payment on an invoice. To change it, edit or remove that payment, or void this receipt.');
    return res.redirect(`/admin/invoices/${doc.inv.id}`);
  }
  res.render('admin/invoice-form', { title: `Edit ${doc.label.toLowerCase()} ${doc.inv.number}`, ...editorData(doc, { existing: doc.inv }) });
});

router.post('/:id', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  if (linkedReceipt(doc)) return res.redirect(`/admin/invoices/${doc.inv.id}/edit`);
  const { inv: data, items, errors, payment } = inv.readForm({ ...req.body, type: doc.inv.type });
  if (!data.number) errors.push('Enter a document number.');
  else if (get('SELECT 1 AS x FROM invoices WHERE number = ? AND id != ?', data.number, doc.inv.id)) errors.push(`Number ${data.number} is already used.`);
  if (errors.length) {
    return res.status(422).render('admin/invoice-form', {
      title: `Edit ${doc.label.toLowerCase()} ${doc.inv.number}`, errors, ...editorData({ inv: { ...data, id: doc.inv.id }, items, payment }, { existing: doc.inv }),
    });
  }
  transaction(() => {
    inv.update(doc.inv.id, data, items);
    if (payment) {
      // A standalone receipt has exactly one payment, always equal to its total.
      run('DELETE FROM invoice_payments WHERE invoice_id = ?', doc.inv.id);
      run('INSERT INTO invoice_payments (invoice_id, paid_on, amount, method, reference, recorded_by) VALUES (?, ?, ?, ?, ?, ?)',
        doc.inv.id, payment.paid_on, inv.totals(data, items).payable, payment.method, payment.reference, req.user.id);
    }
  });
  flash(req, 'success', `${doc.label} ${data.number} saved.`);
  res.redirect(`/admin/invoices/${doc.inv.id}`);
});

// ---------------------------------------------------------------- actions

router.post('/:id/status', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  if (inv.MANUAL_STATUSES[doc.inv.type].includes(req.body.status)) {
    run("UPDATE invoices SET status = ?, updated_at = datetime('now') WHERE id = ?", req.body.status, doc.inv.id);
    flash(req, 'success', `Marked as ${inv.STATUSES[req.body.status].toLowerCase()}.`);
  }
  res.redirect(`/admin/invoices/${doc.inv.id}`);
});

router.post('/:id/send', async (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  if (!lib.isEmail(doc.inv.client_email)) {
    flash(req, 'error', 'Add a valid client email address before sending.');
    return res.redirect(`/admin/invoices/${doc.inv.id}`);
  }
  if (doc.inv.type === 'receipt') {
    await emailReceipt(doc.inv.id);
    flash(req, 'success', mailer.enabled ? `Receipt sent to ${doc.inv.client_email}.` : 'Email is not configured yet, so share the receipt link instead.');
    return res.redirect(`/admin/invoices/${doc.inv.id}`);
  }
  const amount = doc.inv.type === 'quotation' ? doc.t.payable : Math.max(0, doc.t.balance);
  const accountsText = doc.accounts.map((a) => a.type === 'mobile'
    ? `${a.label}: ${a.account_number}${a.account_name ? ` (${a.account_name})` : ''}`
    : `${a.bank_name || a.label}: ${a.account_name || ''}, account ${a.account_number}${a.branch ? `, ${a.branch} branch` : ''}${a.swift ? `, SWIFT ${a.swift}` : ''}`).join('\n');
  await mailer.send({
    to: doc.inv.client_email,
    subject: `${doc.label} ${doc.inv.number} from BrandBay Innovation Studios`,
    text: `Dear ${doc.inv.client_name},\n\nPlease find your ${doc.label.toLowerCase()} ${doc.inv.number} for ${inv.money(amount, doc.inv.currency_symbol)}.${doc.inv.type === 'invoice' && doc.inv.due_date ? `\nPayment is due by ${lib.formatDate(doc.inv.due_date)}.` : ''}\n\nView or download it here:\n${config.baseUrl}/invoice/${doc.inv.public_token}\n${accountsText ? `\nPayment details:\n${accountsText}\nPlease use ${doc.inv.number} as your payment reference.\n` : ''}\nKind regards,\nBrandBay Innovation Studios`,
  });
  if (doc.inv.status === 'draft') run("UPDATE invoices SET status = 'sent' WHERE id = ?", doc.inv.id);
  run("UPDATE invoices SET sent_at = datetime('now') WHERE id = ?", doc.inv.id);
  flash(req, 'success', mailer.enabled ? `Sent to ${doc.inv.client_email}.` : `Marked as sent. Email is not configured yet, so share the client link instead.`);
  res.redirect(`/admin/invoices/${doc.inv.id}`);
});

router.post('/:id/payments', async (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  if (doc.inv.type === 'receipt' || doc.inv.type === 'quotation') return res.redirect(`/admin/invoices/${doc.inv.id}`);
  const amount = inv.round2(inv.num(req.body.amount));
  if (amount <= 0) {
    flash(req, 'error', 'Enter a payment amount greater than zero.');
    return res.redirect(`/admin/invoices/${doc.inv.id}`);
  }
  const payment = {
    paid_on: inv.isIsoDate(req.body.paid_on) ? req.body.paid_on : inv.today(),
    amount,
    method: inv.PAYMENT_METHODS.includes(req.body.method) ? req.body.method : inv.PAYMENT_METHODS[0],
    reference: lib.clean(req.body.reference, 120),
    recorded_by: req.user.id,
  };
  const { lastInsertRowid } = run('INSERT INTO invoice_payments (invoice_id, paid_on, amount, method, reference, recorded_by) VALUES (?, ?, ?, ?, ?, ?)',
    doc.inv.id, payment.paid_on, payment.amount, payment.method, payment.reference, payment.recorded_by);
  if (doc.inv.status === 'draft') run("UPDATE invoices SET status = 'sent' WHERE id = ?", doc.inv.id);
  let message = `Payment of ${inv.money(amount, doc.inv.currency_symbol)} recorded.`;
  if (req.body.issue_receipt) {
    const receiptId = inv.issueReceipt(doc, { ...payment, id: Number(lastInsertRowid) }, req.user.id);
    const number = get('SELECT number FROM invoices WHERE id = ?', receiptId).number;
    message += ` Receipt ${number} issued`;
    if (req.body.email_receipt && await emailReceipt(receiptId)) message += mailer.enabled ? ` and emailed to ${doc.inv.client_email}` : ' (email is not configured yet, so share the receipt link instead)';
    message += '.';
  }
  flash(req, 'success', message);
  res.redirect(`/admin/invoices/${doc.inv.id}`);
});

router.post('/:id/payments/:pid/receipt', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  const payment = get('SELECT * FROM invoice_payments WHERE id = ? AND invoice_id = ?', req.params.pid, doc.inv.id);
  if (!payment) return next();
  const existing = payment.receipt_id && get('SELECT id FROM invoices WHERE id = ?', payment.receipt_id);
  if (existing) return res.redirect(`/admin/invoices/${existing.id}`);
  const id = inv.issueReceipt(doc, payment, req.user.id);
  flash(req, 'success', 'Receipt issued.');
  res.redirect(`/admin/invoices/${id}`);
});

router.post('/:id/payments/:pid/delete', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  const payment = get('SELECT receipt_id FROM invoice_payments WHERE id = ? AND invoice_id = ?', req.params.pid, doc.inv.id);
  transaction(() => {
    run('DELETE FROM invoice_payments WHERE id = ? AND invoice_id = ?', req.params.pid, doc.inv.id);
    // A receipt must never outlive the money it acknowledges.
    if (payment?.receipt_id) run("UPDATE invoices SET status = 'cancelled', updated_at = datetime('now') WHERE id = ? AND type = 'receipt'", payment.receipt_id);
  });
  flash(req, 'success', payment?.receipt_id ? 'Payment removed and its receipt voided.' : 'Payment removed.');
  res.redirect(`/admin/invoices/${doc.inv.id}`);
});

// Quotation/proforma → invoice (or proforma), carrying over client, lines and terms.
router.post('/:id/convert', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  const to = ['invoice', 'proforma'].includes(req.body.to) ? req.body.to : 'invoice';
  const fresh = inv.draft(to, { source: doc });
  const id = inv.create(fresh.inv, fresh.items, { createdBy: req.user.id, sourceId: doc.inv.id });
  if (doc.inv.type === 'quotation' && ['draft', 'sent'].includes(doc.inv.status)) run("UPDATE invoices SET status = 'accepted' WHERE id = ?", doc.inv.id);
  flash(req, 'success', `Created ${inv.DOC_TYPES[to].label.toLowerCase()} from ${doc.inv.number}. Review it, then send.`);
  res.redirect(`/admin/invoices/${id}/edit`);
});

router.post('/:id/duplicate', (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  if (linkedReceipt(doc)) return res.redirect(`/admin/invoices/${doc.inv.id}`);
  const fresh = inv.draft(doc.inv.type, { source: doc });
  fresh.inv.reference = '';
  // A copied standalone receipt starts as a fresh payment received today for the same amount.
  const payments = doc.inv.type === 'receipt'
    ? [{ paid_on: inv.today(), amount: doc.t.payable, method: doc.payments[0]?.method || inv.PAYMENT_METHODS[0], reference: '' }]
    : [];
  if (doc.inv.type === 'receipt') Object.assign(fresh.inv, { status: 'paid', issue_date: inv.today() });
  const id = inv.create(fresh.inv, fresh.items, { createdBy: req.user.id, payments });
  flash(req, 'success', 'Duplicated. Review and save the copy.');
  res.redirect(`/admin/invoices/${id}/edit`);
});

router.post('/:id/delete', requireAdmin, (req, res, next) => {
  const doc = find(req);
  if (!doc) return next();
  transaction(() => {
    run('UPDATE invoice_payments SET receipt_id = NULL WHERE receipt_id = ?', doc.inv.id);
    run('DELETE FROM invoices WHERE id = ?', doc.inv.id);
  });
  flash(req, 'success', `${doc.label} ${doc.inv.number} deleted.`);
  res.redirect('/admin/invoices');
});

module.exports = router;
