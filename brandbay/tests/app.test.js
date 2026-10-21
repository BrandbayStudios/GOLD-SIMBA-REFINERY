// End-to-end tests against a real server and a throwaway database.
const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'brandbay-test-'));
process.env.NODE_ENV = 'test';
process.env.DATABASE_PATH = path.join(tmp, 'test.db');
process.env.UPLOAD_DIR = path.join(tmp, 'uploads');
process.env.PRIVATE_UPLOAD_DIR = path.join(tmp, 'private');
process.env.ADMIN_EMAIL = 'admin@test.local';
process.env.ADMIN_PASSWORD = 'admin-password';

const { seed } = require('../scripts/seed');
const { get, run } = require('../src/db');
const app = require('../src/app');

let server;
let base;

before(async () => {
  seed();
  await new Promise((resolve) => {
    server = app.listen(0, resolve);
  });
  base = `http://127.0.0.1:${server.address().port}`;
});

after(() => {
  server.close();
  fs.rmSync(tmp, { recursive: true, force: true });
});

// A tiny browser: keeps cookies and reads the CSRF token from the last page it loaded.
function browser() {
  let cookie = '';
  let csrf = '';
  async function request(method, url, body) {
    const headers = { cookie };
    let payload;
    if (body instanceof FormData) {
      if (!body.has('_csrf')) body.set('_csrf', csrf);
      payload = body;
    } else if (body) {
      headers['content-type'] = 'application/x-www-form-urlencoded';
      payload = new URLSearchParams({ _csrf: csrf, ...body }).toString();
    }
    const res = await fetch(base + url, { method, headers, body: payload, redirect: 'manual' });
    const set = res.headers.get('set-cookie');
    if (set) cookie = set.split(';')[0];
    const text = await res.text();
    const m = text.match(/name="_csrf" value="([^"]+)"/);
    if (m) csrf = m[1];
    return { status: res.status, location: res.headers.get('location'), text, headers: res.headers };
  }
  return {
    get: (url) => request('GET', url),
    post: (url, body) => request('POST', url, body),
    async login(email, password) {
      await request('GET', '/login');
      return request('POST', '/login', { email, password });
    },
  };
}

test('public pages render with seeded content', async () => {
  const b = browser();
  const home = await b.get('/');
  assert.equal(home.status, 200);
  assert.match(home.text, /Engineered to Win/);
  assert.match(home.text, /Graphic Design &amp; Brand Identity/);
  assert.match(home.text, /Eugine Mavunga/);
  for (const url of ['/about', '/services', '/services/printing-artistry', '/projects', '/projects/altura', '/gallery', '/contact', '/apply', '/store', '/terms', '/sitemap.xml']) {
    assert.equal((await b.get(url)).status, 200, url);
  }
  assert.equal((await b.get('/does-not-exist')).status, 404);
  const contact = await b.get('/contact');
  assert.match(contact.text, /70% upfront deposit/);
});

test('forms reject requests without a valid CSRF token', async () => {
  const res = await fetch(`${base}/contact`, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: 'email=a@b.co&message=hello there',
  });
  assert.equal(res.status, 403);
});

test('contact form stores the message and newsletter signup is idempotent', async () => {
  const b = browser();
  await b.get('/contact');
  const bad = await b.post('/contact', { email: 'not-an-email', message: 'hi' });
  assert.equal(bad.status, 422);
  const ok = await b.post('/contact', { name: 'Test', email: 'contact@test.local', message: 'I would like a logo.' });
  assert.equal(ok.status, 302);
  assert.ok(get("SELECT id FROM contact_messages WHERE email = 'contact@test.local'"));

  await b.post('/subscribe', { email: 'news@test.local' });
  await b.post('/subscribe', { email: 'NEWS@test.local' });
  assert.equal(get("SELECT COUNT(*) AS n FROM subscribers WHERE email = 'news@test.local'").n, 1);
});

test('service request → client account → quote → acceptance', async () => {
  const guest = browser();
  await guest.get('/apply');
  const form = new FormData();
  form.set('name', 'Jane Client');
  form.set('email', 'jane@test.local');
  form.set('service', 'Graphic Design & Brand Identity — Logo Design');
  form.set('budget', 'K1,000 – K5,000');
  form.set('description', 'A logo for my bakery.');
  form.set('attachment', new Blob(['%PDF-1.4 brief'], { type: 'application/pdf' }), 'brief.pdf');
  const submitted = await guest.post('/apply', form);
  assert.equal(submitted.status, 302);
  assert.equal(submitted.location, '/apply/thank-you');
  const thanks = await guest.get('/apply/thank-you');
  const reference = thanks.text.match(/BB-\d{4}-\d{4}/)[0];
  const request = get('SELECT * FROM service_requests WHERE reference = ?', reference);
  assert.equal(request.user_id, null);
  assert.ok(request.attachment);
  // Client documents are not publicly reachable.
  assert.equal((await guest.get(`/uploads/${request.attachment}`)).status, 404);

  // Registering with the same email claims the request.
  const client = browser();
  await client.get('/register');
  const reg = await client.post('/register', {
    name: 'Jane Client', email: 'jane@test.local', password: 'password123', password_confirm: 'password123',
  });
  assert.equal(reg.status, 302);
  const dash = await client.get('/account');
  assert.match(dash.text, new RegExp(reference));

  // Admin sends a quote and a message.
  const admin = browser();
  const login = await admin.login('admin@test.local', 'admin-password');
  assert.equal(login.location, '/admin');
  assert.equal((await admin.get('/admin')).status, 200);
  const adminView = await admin.get(`/admin/requests/${request.id}`);
  assert.match(adminView.text, /Download brief/);
  const att = await admin.get(`/admin/requests/${request.id}/attachment`);
  assert.equal(att.status, 200);
  await admin.post(`/admin/requests/${request.id}`, { status: 'quoted', quote_amount: '2500', admin_notes: 'Rush job' });
  await admin.post(`/admin/requests/${request.id}/messages`, { body: 'Your quote is ready.' });

  const clientView = await client.get(`/account/requests/${request.id}`);
  assert.match(clientView.text, /K2,500\.00/);
  assert.match(clientView.text, /K1,750\.00/); // 70% deposit
  assert.match(clientView.text, /Your quote is ready/);
  await client.post(`/account/requests/${request.id}/accept`, {});
  assert.equal(get('SELECT status FROM service_requests WHERE id = ?', request.id).status, 'in_progress');

  // Another client cannot see it.
  const other = browser();
  await other.get('/register');
  await other.post('/register', { name: 'Other', email: 'other@test.local', password: 'password123', password_confirm: 'password123' });
  assert.equal((await other.get(`/account/requests/${request.id}`)).status, 404);
  assert.equal((await other.get('/admin')).status, 403);
});

test('store: admin publishes a product, customer checks out, stock is tracked', async () => {
  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');
  const product = get("SELECT * FROM products WHERE slug = 'business-cards'");
  assert.equal(product.published, 0);

  await admin.get(`/admin/content/products/${product.id}`);
  const noPrice = new FormData();
  noPrice.set('name', 'Business Cards');
  noPrice.set('price', '0');
  noPrice.set('published', '1');
  assert.equal((await admin.post(`/admin/content/products/${product.id}`, noPrice)).status, 422);

  const fd = new FormData();
  fd.set('name', 'Business Cards');
  fd.set('category', 'Printing');
  fd.set('price', '350');
  fd.set('unit', 'per 100');
  fd.set('stock', '5');
  fd.set('published', '1');
  assert.equal((await admin.post(`/admin/content/products/${product.id}`, fd)).status, 302);

  const shopper = browser();
  const store = await shopper.get('/store');
  assert.match(store.text, /K350\.00/);
  await shopper.post('/cart/add', { product_id: String(product.id), qty: '2' });
  const cart = await shopper.get('/cart');
  assert.match(cart.text, /K700\.00/);

  await shopper.get('/checkout');
  const missing = await shopper.post('/checkout', { name: 'Shopper', email: 'shop@test.local', phone: '', payment_method: 'mobile_money' });
  assert.equal(missing.status, 422);
  const done = await shopper.post('/checkout', {
    name: 'Shopper', email: 'shop@test.local', phone: '0977000000', delivery_method: 'collect', payment_method: 'mobile_money',
  });
  assert.equal(done.location, '/checkout/complete');
  const complete = await shopper.get('/checkout/complete');
  assert.match(complete.text, /ORD-\d{4}-\d{4}/);
  assert.equal(get('SELECT stock FROM products WHERE id = ?', product.id).stock, 3);

  const order = get("SELECT * FROM orders WHERE email = 'shop@test.local'");
  assert.equal(order.subtotal, 700);
  await admin.post(`/admin/orders/${order.id}`, { status: 'cancelled', payment_status: 'unpaid' });
  assert.equal(get('SELECT stock FROM products WHERE id = ?', product.id).stock, 5);
});

test('admin content editor creates FAQs and users cannot escalate themselves', async () => {
  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');
  await admin.get('/admin/content/faqs/new');
  const fd = new FormData();
  fd.set('question', 'Do you deliver outside Lusaka?');
  fd.set('answer', 'Yes, nationwide.');
  fd.set('published', '1');
  assert.equal((await admin.post('/admin/content/faqs', fd)).status, 302);
  assert.match((await admin.get('/contact')).text, /Do you deliver outside Lusaka/);

  const staffId = get("SELECT id FROM users WHERE email = 'other@test.local'").id;
  await admin.get('/admin/users');
  await admin.post(`/admin/users/${staffId}/role`, { role: 'staff' });
  const staff = browser();
  await staff.login('other@test.local', 'password123');
  assert.equal((await staff.get('/admin/requests')).status, 200);
  assert.equal((await staff.get('/admin/settings')).status, 403);
  assert.equal((await staff.get('/admin/users')).status, 403);
});

test('login rejects wrong passwords and open redirects', async () => {
  const b = browser();
  const bad = await b.login('admin@test.local', 'nope');
  assert.equal(bad.status, 401);
  await b.get('/login');
  const res = await b.post('/login', { email: 'jane@test.local', password: 'password123', next: '//evil.example' });
  assert.equal(res.location, '/account');
});

test('password reset works once and expires the token', async () => {
  const b = browser();
  await b.get('/forgot-password');
  await b.post('/forgot-password', { email: 'jane@test.local' });
  // The emailed token is hashed in the database; issue a known one for the test.
  const crypto = require('node:crypto');
  const token = 'a'.repeat(64);
  run('UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?',
    crypto.createHash('sha256').update(token).digest('hex'), Date.now() + 60000, 'jane@test.local');
  assert.equal((await b.get(`/reset-password/${token}`)).status, 200);
  const res = await b.post(`/reset-password/${token}`, { password: 'newpassword1', password_confirm: 'newpassword1' });
  assert.equal(res.location, '/login');
  assert.equal((await b.get(`/reset-password/${token}`)).status, 302);
  assert.equal((await browser().login('jane@test.local', 'newpassword1')).status, 302);
});
