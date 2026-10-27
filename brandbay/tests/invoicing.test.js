// Invoicing: maths, admin workflow, client visibility and access control.
const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'brandbay-inv-test-'));
process.env.NODE_ENV = 'test';
process.env.DATABASE_PATH = path.join(tmp, 'test.db');
process.env.UPLOAD_DIR = path.join(tmp, 'uploads');
process.env.PRIVATE_UPLOAD_DIR = path.join(tmp, 'private');
process.env.ADMIN_EMAIL = 'admin@test.local';
process.env.ADMIN_PASSWORD = 'admin-password';

const { seed } = require('../scripts/seed');
const { get, all } = require('../src/db');
const invoicing = require('../src/invoicing');
const app = require('../src/app');

let server;
let base;

before(async () => {
  seed();
  await new Promise((resolve) => { server = app.listen(0, resolve); });
  base = `http://127.0.0.1:${server.address().port}`;
});

after(() => {
  server.close();
  fs.rmSync(tmp, { recursive: true, force: true });
});

function browser() {
  let cookie = '';
  let csrf = '';
  async function request(method, url, body) {
    const headers = { cookie };
    let payload;
    if (body) {
      headers['content-type'] = 'application/x-www-form-urlencoded';
      const params = new URLSearchParams();
      params.append('_csrf', csrf);
      for (const [k, v] of Object.entries(body)) for (const x of [].concat(v)) params.append(k, x);
      payload = params.toString();
    }
    const res = await fetch(base + url, { method, headers, body: payload, redirect: 'manual' });
    const set = res.headers.get('set-cookie');
    if (set) cookie = set.split(';')[0];
    const text = await res.text();
    const m = text.match(/name="_csrf" value="([^"]+)"/);
    if (m) csrf = m[1];
    return { status: res.status, location: res.headers.get('location'), text };
  }
  return {
    get: (url) => request('GET', url),
    post: (url, body) => request('POST', url, body),
    async login(email, password) {
      await request('GET', '/login');
      return request('POST', '/login', { email, password });
    },
    async register(name, email) {
      await request('GET', '/register');
      return request('POST', '/register', { name, email, password: 'password123', password_confirm: 'password123' });
    },
  };
}

const idOf = (location) => Number(location.match(/\/admin\/invoices\/(\d+)/)[1]);

test('totals: line and document discounts, grouped tax, withholding, deposit and payments', () => {
  const t = invoicing.totals(
    { discount_percent: 10, withholding_rate: 15, deposit_percent: 70 },
    [
      { quantity: 1, unit_price: 2500, discount_percent: 0, tax_name: 'VAT', tax_rate: 16 },
      { quantity: 2, unit_price: 350, discount_percent: 10, tax_name: 'VAT', tax_rate: 16 },
      { quantity: 1, unit_price: 1000, discount_percent: 0, tax_name: 'VAT exempt', tax_rate: 0 },
    ],
    [{ amount: 1000 }],
  );
  assert.equal(t.subtotal, 4130);
  assert.equal(t.discount, 413);
  assert.equal(t.taxable, 3717);
  assert.equal(t.taxGroups.length, 2);
  assert.equal(t.taxTotal, 450.72); // 16% of (2250 + 567)
  assert.equal(t.total, 4167.72);
  assert.equal(t.withholding, 557.55);
  assert.equal(t.payable, 3610.17);
  assert.equal(t.deposit, 2527.12);
  assert.equal(t.balance, 2610.17);
});

test('defaults: every website sub-service is in the price list with a tax, plus currencies and taxes', () => {
  const subServices = all('SELECT items FROM service_categories').flatMap((c) => c.items.split('\n').filter(Boolean));
  assert.equal(get('SELECT COUNT(*) AS n FROM service_items').n, subServices.length);
  assert.ok(get("SELECT 1 AS x FROM tax_rates WHERE name LIKE 'VAT%' AND rate = 16 AND is_default = 1"));
  assert.ok(get("SELECT 1 AS x FROM tax_rates WHERE kind = 'withhold'"));
  assert.equal(get('SELECT code FROM currencies WHERE is_default = 1').code, 'ZMW');
  // Running the defaults again changes nothing.
  invoicing.ensureDefaults();
  assert.equal(get('SELECT COUNT(*) AS n FROM service_items').n, subServices.length);
});

test('full workflow: request → quotation → invoice → payment → receipt, visible to the client', async () => {
  // A client applies and has an account.
  const client = browser();
  await client.register('Mwila Banda', 'mwila@test.local');
  await client.get('/apply');
  await client.post('/apply', { name: 'Mwila Banda', email: 'mwila@test.local', service: 'Graphic Design & Brand Identity — Logo Design' });
  const request = get("SELECT * FROM service_requests WHERE email = 'mwila@test.local'");

  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');

  // Set a bank account and a Logo Design price.
  const bank = get("SELECT id FROM payment_accounts WHERE type = 'bank'");
  await admin.get('/admin/invoices/settings');
  const settingsRes = await admin.post('/admin/invoices/settings', {
    [`acc.${bank.id}.label`]: 'Bank account', [`acc.${bank.id}.bank_name`]: 'Zanaco', [`acc.${bank.id}.account_number`]: '0123456789',
    [`acc.${bank.id}.account_name`]: 'BrandBay Innovation Studios', [`acc.${bank.id}.is_default`]: '1',
    tpin: '1002003004', due_days: '14', valid_days: '30', deposit_percent: '70', terms: 'Pay on time.', footer: 'Thanks!',
    default_currency: 'ZMW', default_tax: String(invoicing.defaultTax().id),
  });
  assert.equal(settingsRes.status, 302);
  const logo = get("SELECT * FROM service_items WHERE name = 'Logo Design'");
  await admin.get('/admin/invoices/catalog');
  await admin.post('/admin/invoices/catalog', {
    [`item.${logo.id}.name`]: 'Logo Design', [`item.${logo.id}.unit`]: 'per project', [`item.${logo.id}.price`]: '2500', [`item.${logo.id}.tax_id`]: String(logo.tax_id),
  });
  assert.equal(get('SELECT price FROM service_items WHERE id = ?', logo.id).price, 2500);

  // The quotation form is pre-filled from the request with the catalogue price.
  const form = await admin.get(`/admin/invoices/new?type=quotation&request=${request.id}`);
  assert.equal(form.status, 200);
  assert.match(form.text, /value="Logo Design"/);
  assert.match(form.text, /name="item_price" value="2500"/);
  assert.match(form.text, /value="Mwila Banda"/);

  const vat = invoicing.defaultTax();
  const created = await admin.post('/admin/invoices', {
    type: 'quotation', number: invoicing.nextNumber('quotation').number, status: 'sent', request_id: String(request.id),
    user_id: String(get("SELECT id FROM users WHERE email = 'mwila@test.local'").id),
    client_name: 'Mwila Banda', client_email: 'mwila@test.local', issue_date: '2026-10-01', due_date: '2026-10-31', currency: 'ZMW',
    item_category: ['Graphic Design & Brand Identity', ''], item_name: ['Logo Design', 'Brand guidelines PDF'], item_description: ['', ''],
    item_qty: ['1', '1'], item_unit: ['per project', ''], item_price: ['2500', '500'], item_discount: ['0', '0'],
    item_tax_id: [String(vat.id), String(vat.id)], discount_percent: '0', show_deposit: '1', deposit_percent: '70',
    account_ids: String(bank.id), notes: '', terms: 'Valid 30 days.',
  });
  assert.equal(created.status, 302);
  const quoteId = idOf(created.location);
  const quote = invoicing.load('id = ?', quoteId);
  assert.equal(quote.inv.number, `QUO-${new Date().getFullYear()}-0001`);
  assert.equal(quote.t.payable, 3480); // 3000 + 16% VAT
  assert.equal(quote.inv.request_id, request.id);

  // Request page links to it; client sees it on their dashboard and can open it.
  assert.match((await admin.get(`/admin/requests/${request.id}`)).text, /QUO-\d{4}-0001/);
  const dash = await client.get('/account');
  assert.match(dash.text, /QUO-\d{4}-0001/);
  const clientView = await client.get(`/account/invoices/${quoteId}`);
  assert.equal(clientView.status, 200);
  assert.match(clientView.text, /K3,480\.00/);
  assert.match(clientView.text, /Zanaco/);
  assert.match(clientView.text, /TPIN: 1002003004/);

  // Convert to an invoice.
  const conv = await admin.post(`/admin/invoices/${quoteId}/convert`, { to: 'invoice' });
  const invoiceId = Number(conv.location.match(/\/admin\/invoices\/(\d+)\/edit/)[1]);
  const invoiceDoc = invoicing.load('id = ?', invoiceId);
  assert.equal(invoiceDoc.inv.type, 'invoice');
  assert.equal(invoiceDoc.inv.reference, quote.inv.number);
  assert.equal(invoiceDoc.items.length, 2);
  assert.equal(invoiceDoc.t.payable, 3480);
  assert.equal(get('SELECT status FROM invoices WHERE id = ?', quoteId).status, 'accepted');

  // Part payment with a receipt, then full payment without one.
  await admin.get(`/admin/invoices/${invoiceId}`);
  await admin.post(`/admin/invoices/${invoiceId}/payments`, { amount: '2436', paid_on: '2026-10-05', method: 'Mobile Money', reference: 'MP123', issue_receipt: '1' });
  let after = invoicing.load('id = ?', invoiceId);
  assert.equal(after.status, 'partial');
  assert.equal(after.t.balance, 1044);
  await admin.post(`/admin/invoices/${invoiceId}/payments`, { amount: '1044', paid_on: '2026-10-20', method: 'Bank transfer' });
  after = invoicing.load('id = ?', invoiceId);
  assert.equal(after.status, 'paid');

  // The first payment's receipt acknowledges that payment only, and the balance left after it.
  const [first, second] = after.payments;
  assert.ok(first.receipt_id);
  assert.equal(second.receipt_id, null);
  const receipt = invoicing.load('id = ?', first.receipt_id);
  assert.equal(receipt.inv.type, 'receipt');
  assert.equal(receipt.inv.reference, after.inv.number);
  assert.equal(receipt.inv.issue_date, '2026-10-05');
  assert.equal(receipt.t.paid, 2436);
  assert.equal(receipt.status, 'paid');
  assert.deepEqual(receipt.receipt.invoice && [receipt.receipt.invoice.total, receipt.receipt.invoice.paidToDate, receipt.receipt.invoice.balance], [3480, 2436, 1044]);
  const receiptPaper = await browser().get(`/invoice/${receipt.inv.public_token}`);
  assert.match(receiptPaper.text, /Amount received/);
  assert.match(receiptPaper.text, /Balance remaining/);
  assert.match(receiptPaper.text, /K1,044\.00/);
  // Linked receipts can't be edited directly.
  assert.equal((await admin.get(`/admin/invoices/${receipt.inv.id}/edit`)).status, 302);

  // Issue a receipt for the second payment afterwards; it shows the invoice as settled.
  await admin.get(`/admin/invoices/${invoiceId}`);
  await admin.post(`/admin/invoices/${invoiceId}/payments/${second.id}/receipt`, {});
  const secondReceiptId = get('SELECT receipt_id FROM invoice_payments WHERE id = ?', second.id).receipt_id;
  const secondReceipt = invoicing.load('id = ?', secondReceiptId);
  assert.equal(secondReceipt.t.paid, 1044);
  assert.equal(secondReceipt.receipt.invoice.balance, 0);
  assert.match((await admin.get(`/admin/invoices/${invoiceId}`)).text, new RegExp(secondReceipt.inv.number));

  // Public link works without signing in; the print page shows the PAID stamp.
  const pub = await browser().get(`/invoice/${after.inv.public_token}`);
  assert.equal(pub.status, 200);
  assert.match(pub.text, /PAID/);
  assert.equal((await browser().get(`/invoice/${'0'.repeat(48)}`)).status, 404);

  // Changing VAT later does not alter the issued invoice.
  await admin.get('/admin/invoices/settings');
  await admin.post('/admin/invoices/settings', {
    [`tax.${vat.id}.name`]: vat.name, [`tax.${vat.id}.rate`]: '18', [`tax.${vat.id}.kind`]: 'add', default_tax: String(vat.id), default_currency: 'ZMW',
  });
  assert.equal(invoicing.load('id = ?', invoiceId).t.payable, 3480);

  // CSV export
  const csv = await admin.get('/admin/invoices/export.csv');
  assert.match(csv.text, /^number,type,status/);
  assert.match(csv.text, /INV-\d{4}-0001,Invoice,Paid/);
});

test('drafts are hidden from clients and other clients cannot see documents', async () => {
  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');
  await admin.get('/admin/invoices/new');
  const vat = invoicing.defaultTax();
  const res = await admin.post('/admin/invoices', {
    type: 'invoice', number: '', status: 'draft', client_name: 'Mwila Banda', client_email: 'mwila@test.local',
    issue_date: '2026-10-10', due_date: '2026-10-24', currency: 'USD',
    item_name: 'Website & App Design', item_qty: '1', item_price: '1200', item_discount: '0', item_tax_id: String(vat.id), discount_percent: '0',
  });
  const id = idOf(res.location);
  assert.equal(invoicing.load('id = ?', id).inv.currency_symbol, '$');

  const owner = browser();
  await owner.login('mwila@test.local', 'password123');
  assert.equal((await owner.get(`/account/invoices/${id}`)).status, 404); // draft

  await admin.get(`/admin/invoices/${id}`);
  await admin.post(`/admin/invoices/${id}/send`, {});
  assert.equal(get('SELECT status FROM invoices WHERE id = ?', id).status, 'sent');
  assert.equal((await owner.get(`/account/invoices/${id}`)).status, 200);

  const stranger = browser();
  await stranger.register('Stranger', 'stranger@test.local');
  assert.equal((await stranger.get(`/account/invoices/${id}`)).status, 404);
  assert.equal((await stranger.get('/admin/invoices')).status, 403);
});

test('validation and number uniqueness', async () => {
  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');
  await admin.get('/admin/invoices/new');
  const missing = await admin.post('/admin/invoices', { type: 'invoice', client_name: '', item_name: 'X', item_price: '1', currency: 'ZMW' });
  assert.equal(missing.status, 422);
  assert.match(missing.text, /Enter who this is billed to/);

  const existing = get("SELECT * FROM invoices WHERE type = 'invoice' LIMIT 1");
  await admin.get(`/admin/invoices/${existing.id}/edit`);
  const other = get('SELECT * FROM invoices WHERE id != ? LIMIT 1', existing.id);
  const dup = await admin.post(`/admin/invoices/${existing.id}`, {
    number: other.number, status: 'sent', client_name: 'A', issue_date: '2026-10-01', currency: 'ZMW', item_name: 'Thing', item_price: '10',
  });
  assert.equal(dup.status, 422);
  assert.match(dup.text, /already used/);
});

test('standalone receipts, and removing a payment voids its receipt', async () => {
  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');
  const form = await admin.get('/admin/invoices/new?type=receipt');
  assert.equal(form.status, 200);
  assert.match(form.text, /Payment received/);
  assert.match(form.text, /name="type" value="receipt"/);
  assert.doesNotMatch(form.text, /name="withholding_id"/);

  const vat = invoicing.defaultTax();
  const gross = (n) => Math.round(n * (100 + vat.rate)) / 100;
  const zero = await admin.post('/admin/invoices', {
    type: 'receipt', client_name: 'Walk-in', issue_date: '2026-10-12', currency: 'ZMW', item_name: 'Nothing', item_price: '0', item_tax_id: String(vat.id),
  });
  assert.equal(zero.status, 422);
  assert.match(zero.text, /greater than zero/);

  const res = await admin.post('/admin/invoices', {
    type: 'receipt', number: '', status: 'paid', client_name: 'Walk-in client', issue_date: '2026-10-12', currency: 'ZMW',
    paid_on: '2026-10-12', method: 'Cash', payment_reference: 'CASH-1',
    item_name: 'Business cards', item_qty: '1', item_price: '500', item_discount: '0', item_tax_id: String(vat.id), discount_percent: '0',
    withholding_id: String(get("SELECT id FROM tax_rates WHERE kind = 'withhold'").id), show_deposit: '1', deposit_percent: '50',
  });
  assert.equal(res.status, 302);
  const id = idOf(res.location);
  let r = invoicing.load('id = ?', id);
  assert.match(r.inv.number, /^REC-\d{4}-\d{4}$/);
  assert.equal(r.inv.withholding_rate, 0);
  assert.equal(r.inv.deposit_percent, 0);
  assert.equal(r.t.paid, gross(500));
  assert.equal(r.payments[0].method, 'Cash');
  assert.equal(r.receipt.invoice, null);

  // Editing re-records the single payment at the new total.
  await admin.get(`/admin/invoices/${id}/edit`);
  const saved = await admin.post(`/admin/invoices/${id}`, {
    number: r.inv.number, status: 'paid', client_name: 'Walk-in client', issue_date: '2026-10-12', currency: 'ZMW',
    paid_on: '2026-10-13', method: 'Mobile Money', payment_reference: 'MM-9',
    item_name: 'Business cards', item_qty: '2', item_price: '500', item_discount: '0', item_tax_id: String(vat.id), discount_percent: '0',
  });
  assert.equal(saved.status, 302, saved.text.match(/<ul class="errors?[^>]*>[\s\S]*?<\/ul>/)?.[0]);
  r = invoicing.load('id = ?', id);
  assert.equal(r.payments.length, 1);
  assert.equal(r.t.paid, gross(1000));
  assert.equal(r.payments[0].paid_on, '2026-10-13');
  assert.match((await browser().get(`/invoice/${r.inv.public_token}`)).text, new RegExp(`K${gross(1000).toLocaleString('en-US', { minimumFractionDigits: 2 })}`));

  // A payment's receipt is voided when the payment is removed.
  const invRes = await admin.post('/admin/invoices', {
    type: 'invoice', number: '', status: 'sent', client_name: 'Chanda', issue_date: '2026-10-01', due_date: '2026-10-30', currency: 'ZMW',
    item_name: 'Flyer', item_qty: '1', item_price: '100', item_discount: '0', item_tax_id: String(vat.id), discount_percent: '0',
  });
  const invoiceId = idOf(invRes.location);
  await admin.get(`/admin/invoices/${invoiceId}`);
  await admin.post(`/admin/invoices/${invoiceId}/payments`, { amount: String(gross(100)), paid_on: '2026-10-02', method: 'Cash', issue_receipt: '1' });
  const pay = get('SELECT * FROM invoice_payments WHERE invoice_id = ?', invoiceId);
  assert.ok(pay.receipt_id);
  // Quotations and receipts don't take payments.
  await admin.post(`/admin/invoices/${pay.receipt_id}/payments`, { amount: '1', paid_on: '2026-10-02', method: 'Cash' });
  assert.equal(all('SELECT id FROM invoice_payments WHERE invoice_id = ?', pay.receipt_id).length, 1);

  await admin.post(`/admin/invoices/${invoiceId}/payments/${pay.id}/delete`, {});
  assert.equal(get('SELECT status FROM invoices WHERE id = ?', pay.receipt_id).status, 'cancelled');
  assert.equal(invoicing.load('id = ?', invoiceId).t.paid, 0);
});
