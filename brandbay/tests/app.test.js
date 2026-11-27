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
const { get, run, all } = require('../src/db');
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

test('SEO: titles, canonical, robots, structured data, sitemap', async () => {
  const b = browser();
  const ld = (html) => JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)[1]);

  const home = await b.get('/');
  assert.match(home.text, /<title>Branding &amp; Web Design Agency in Lusaka, Zambia \| BrandBay<\/title>/);
  assert.match(home.text, /<link rel="canonical" href="http:\/\/[^"]+\/">/);
  assert.match(home.text, /<meta name="robots" content="index, follow/);
  const homeGraph = ld(home.text)['@graph'];
  const org = homeGraph.find((n) => n['@type'] === 'ProfessionalService');
  assert.equal(org.address.addressLocality, 'Lusaka');
  assert.equal(org.hasOfferCatalog.itemListElement.length, 6);

  const service = await b.get('/services/graphic-design-brand-identity');
  assert.match(service.text, /<title>Graphic Design &amp; Brand Identity in Lusaka, Zambia \| BrandBay<\/title>/);
  assert.match(service.text, /designs logos and complete brand identities/);
  const types = ld(service.text)['@graph'].map((n) => n['@type']);
  assert.ok(types.includes('Service') && types.includes('BreadcrumbList'));

  const contact = await b.get('/contact');
  assert.ok(ld(contact.text)['@graph'].some((n) => n['@type'] === 'FAQPage'));

  const login = await b.get('/login');
  assert.match(login.text, /<meta name="robots" content="noindex, nofollow">/);
  assert.doesNotMatch(login.text, /rel="canonical"/);
  assert.match((await b.get('/no-such-page')).text, /noindex/);

  const robots = await b.get('/robots.txt');
  assert.match(robots.text, /Disallow: \/admin/);
  assert.match(robots.text, /Sitemap: http/);

  const sitemap = await b.get('/sitemap.xml');
  assert.match(sitemap.text, /<loc>http[^<]+\/services\/printing-artistry<\/loc>/);
  assert.match(sitemap.text, /<image:loc>http[^<]+\/media\/logos\/[^<]+\.webp<\/image:loc>/);
  assert.doesNotMatch(sitemap.text, /\/admin|\/login/);
});

test('plain page views start no session; the footer newsletter still works', async () => {
  run('UPDATE products SET published = 0'); // an empty store hides its links
  const page = await fetch(`${base}/about`);
  assert.equal(page.status, 200);
  assert.equal(page.headers.get('set-cookie'), null);
  const html = await page.text();
  assert.doesNotMatch(html, /href="\/store"/); // no published products yet
  assert.match(html, /\/brand\/logo-horizontal-gold-sm\.png/);
  const sub = await fetch(`${base}/subscribe`, {
    method: 'POST', redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: 'email=newsletter%40test.local',
  });
  assert.equal(sub.status, 302);
  assert.ok(get("SELECT id FROM subscribers WHERE email = 'newsletter@test.local'"));
  // Forms that need protection still require a token.
  const contact = await fetch(`${base}/contact`, {
    method: 'POST', redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: 'name=A&email=a%40test.local&message=Hello',
  });
  assert.equal(contact.status, 403);
});

test('packages page lists the starting prices and links into the quote form', async () => {
  const html = await (await fetch(`${base}/packages`)).text();
  for (const price of ['K1,500', 'K2,500', 'K4,500', 'K7,500']) assert.ok(html.includes(price), price);
  assert.match(html, /Most popular/);
  assert.match(html, /"@type":"OfferCatalog"/);
  const apply = await (await fetch(`${base}/apply?package=business-launch`)).text();
  assert.match(apply, /<option value="Package — Business Launch \(from K4,500\)" selected>/);
  assert.match(await (await fetch(`${base}/sitemap.xml`)).text(), /\/packages</);
});

test('instant quote estimator prices the selection on the server and saves a lead', async () => {
  const visitor = browser();
  const page = await visitor.get('/quote');
  assert.equal(page.status, 200);
  assert.match(page.text, /Logo design/);
  const logo = get("SELECT * FROM estimate_items WHERE slug = 'logo-design'");
  const flyer = get("SELECT * FROM estimate_items WHERE slug = 'flyer-or-poster-design'");

  const empty = await visitor.post('/quote', { name: 'Chipo', email: 'chipo@test.local' });
  assert.equal(empty.status, 422);
  assert.match(empty.text, /Tick at least one service/);

  const sent = await visitor.post('/quote', {
    name: 'Chipo Mwale', email: 'chipo@test.local', phone: '0977000000', timeline: 'Within 2 weeks',
    [`item_${logo.id}`]: '1', [`item_${flyer.id}`]: '1', [`qty_${flyer.id}`]: '3',
    notes: 'Fashion boutique', price: '1', // a tampered price field is ignored
  });
  assert.equal(sent.status, 302);
  assert.equal(sent.location, '/apply/thank-you');
  const lead = get("SELECT * FROM service_requests WHERE email = 'chipo@test.local'");
  assert.equal(lead.estimate_amount, logo.price + flyer.price * 3);
  assert.match(lead.service, /Instant estimate — 2 services/);
  assert.match(lead.description, /Flyer or poster design × 3/);
  assert.match(lead.description, /Fashion boutique/);
  const thanks = await visitor.get('/apply/thank-you');
  assert.match(thanks.text, new RegExp(`from K${(logo.price + flyer.price * 3).toLocaleString('en-US')}`));

  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');
  const view = await admin.get(`/admin/requests/${lead.id}`);
  assert.match(view.text, /Instant estimate/);
});

test('Google reviews stay hidden until set, then show the badge and the /review link', async () => {
  const before = await (await fetch(`${base}/`)).text();
  assert.doesNotMatch(before, /google-badge/);
  assert.equal((await fetch(`${base}/review`, { redirect: 'manual' })).headers.get('location'), '/contact');

  const admin = browser();
  await admin.login('admin@test.local', 'admin-password');
  await admin.get('/admin/settings');
  const settings = Object.fromEntries(all('SELECT key, value FROM settings').map((r) => [r.key, r.value]));
  await admin.post('/admin/settings', { ...settings, google_place_id: 'ChIJ_test-Place1', google_rating: '4.8', google_review_count: '12' });

  const review = await fetch(`${base}/review`, { redirect: 'manual' });
  assert.equal(review.headers.get('location'), 'https://search.google.com/local/writereview?placeid=ChIJ_test-Place1');
  const after = await (await fetch(`${base}/`)).text();
  assert.match(after, /4\.8 out of 5 on Google · 12 reviews/);
  assert.match(after, /Leave us a Google review/);

  const lead = get('SELECT id FROM service_requests LIMIT 1');
  assert.match((await admin.get(`/admin/requests/${lead.id}`)).text, /Ask for a Google review/);
});

test('website projects link to the live site, except private systems', async () => {
  const site = await (await fetch(`${base}/projects/saferoute-logistics-website`)).text();
  assert.match(site, /href="https:\/\/saferouteglobal\.com\/"[^>]*>Visit live site/);
  assert.match(site, /Quote calculator/);
  const app = await (await fetch(`${base}/projects/saferoute-document-system`)).text();
  assert.doesNotMatch(app, /Visit live site/);
  const tech = await (await fetch(`${base}/services/technological-solutions`)).text();
  assert.match(tech, /Websites we’ve built/);
  assert.match(await (await fetch(`${base}/packages`)).text(), /saferoute-logistics-website/);
});

test('branding, print and solar jobs appear under their own tabs', async () => {
  const html = await (await fetch(`${base}/projects`)).text();
  for (const tab of ['Vehicle Branding', 'Signage', 'Shopfront Branding', 'Stickers &amp; Labels', 'Pull-up Banners', 'Exhibitions &amp; Events', 'T-Shirt Printing', 'Branded Merchandise', 'Flyers &amp; Brochures', 'Embroidery', 'ID Cards &amp; Badges', 'Business Cards', 'Office Branding']) assert.ok(html.includes(`data-filter="${tab}"`), tab);
  assert.match(html, /SafeRoute Logistics Car Branding/);
  assert.match(html, /Solar Inverter &amp; Battery Installation/);
  assert.equal((await fetch(`${base}/projects/solar-installation`)).status, 200);
  assert.match(await (await fetch(`${base}/projects/izack-shopfront-branding`)).text(), /Photos from the job/);
});

test('WhatsApp alerts: sent for new leads through the chosen provider, never break the form, keep keys private', async () => {
  const setting = (k, v) => run('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', k, v);
  const realFetch = global.fetch;
  const calls = [];
  let outcome = { ok: true, status: 200 };
  global.fetch = (url, opts) => (String(url).startsWith(base) ? realFetch(url, opts) : (calls.push({ url: String(url), opts }), Promise.resolve(outcome)));
  const lead = async (email) => {
    const v = browser();
    await v.get('/quote');
    const logo = get("SELECT id FROM estimate_items WHERE slug = 'logo-design'");
    return v.post('/quote', { name: 'Alert Test', email, phone: '0977123456', [`item_${logo.id}`]: '1' });
  };
  try {
    setting('whatsapp_alert_provider', 'off');
    assert.equal((await lead('off@test.local')).status, 302);
    assert.equal(calls.length, 0, 'nothing is sent while alerts are off');

    setting('whatsapp_alert_provider', 'callmebot'); setting('whatsapp_alert_to', '260972015689'); setting('callmebot_apikey', 'KEY123');
    assert.equal((await lead('cmb@test.local')).status, 302);
    await new Promise((r) => setTimeout(r, 50));
    assert.equal(calls.length, 1);
    const u = new URL(calls[0].url);
    assert.equal(u.hostname, 'api.callmebot.com');
    assert.equal(u.searchParams.get('phone'), '260972015689');
    assert.equal(u.searchParams.get('apikey'), 'KEY123');
    assert.match(u.searchParams.get('text'), /Instant estimate/);
    assert.match(u.searchParams.get('text'), /\/admin\/requests\/\d+/);

    calls.length = 0; outcome = { ok: false, status: 500 };
    assert.equal((await lead('fail@test.local')).status, 302, 'a failing provider never breaks the form');

    outcome = { ok: true, status: 200 };
    setting('whatsapp_alert_provider', 'meta'); setting('meta_wa_phone_id', '12345'); setting('meta_wa_token', 'SECRETTOKEN'); setting('meta_wa_template', 'new_lead');
    assert.equal((await lead('meta@test.local')).status, 302);
    await new Promise((r) => setTimeout(r, 50));
    const meta = calls.at(-1);
    assert.match(meta.url, /graph\.facebook\.com\/v21\.0\/12345\/messages/);
    assert.equal(meta.opts.headers.Authorization, 'Bearer SECRETTOKEN');
    const body = JSON.parse(meta.opts.body);
    assert.equal(body.template.name, 'new_lead');
    assert.ok(body.template.components[0].parameters.every((p) => !/\n/.test(p.text)));

    // Keys never appear on public pages or are echoed back in the settings form.
    const home = await (await fetch(`${base}/`)).text();
    assert.ok(!home.includes('SECRETTOKEN') && !home.includes('KEY123'));
    const admin = browser();
    await admin.login('admin@test.local', 'admin-password');
    const form = await admin.get('/admin/settings');
    assert.ok(!form.text.includes('SECRETTOKEN') && !form.text.includes('KEY123'));
    assert.match(form.text, /Saved — leave blank to keep it/);
    await admin.post('/admin/settings', { ...Object.fromEntries(all('SELECT key, value FROM settings').map((r) => [r.key, r.value])), meta_wa_token: '', callmebot_apikey: '' });
    assert.equal(get("SELECT value FROM settings WHERE key = 'meta_wa_token'").value, 'SECRETTOKEN', 'a blank secret keeps the saved one');

    calls.length = 0;
    const tested = await admin.post('/admin/settings/test-alert', {});
    assert.equal(tested.status, 302);
    assert.equal(calls.length, 1);
  } finally {
    global.fetch = realFetch;
    setting('whatsapp_alert_provider', 'off');
  }
});

test('admin login reset sets the contact email and a new password once', async () => {
  const bcrypt = require('bcryptjs');
  const { resetAdminLogin } = require('../scripts/seed');
  const before = get("SELECT id, email FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
  const contact = get("SELECT value FROM settings WHERE key = 'email'").value.toLowerCase();
  const email = resetAdminLogin(bcrypt.hashSync('Reset-Test-Pass-1', 4));
  assert.equal(email, contact);
  const admin = browser();
  assert.equal((await admin.login(contact, 'Reset-Test-Pass-1')).status, 302);
  assert.equal((await admin.get('/admin')).status, 200);
  // restore the test admin so later tests keep working
  run('UPDATE users SET email = ?, password_hash = ? WHERE id = ?', before.email, bcrypt.hashSync('admin-password', 4), before.id);
});

test('courses page lists every course and enrolment lands as a request', async () => {
  const html = await (await fetch(`${base}/courses`)).text();
  for (const name of ['Graphic Design Basics', 'Build Your First Website', 'Cyber Safety for Everyone', 'Social Media Management']) assert.ok(html.includes(name), name);
  assert.match(html, /K1,200/);
  assert.match(html, /href="\/apply\?course=graphic-design-basics"/);
  const apply = await (await fetch(`${base}/apply?course=graphic-design-basics`)).text();
  assert.match(apply, /<option value="Course — Graphic Design Basics \(from K1,200\)" selected>/);
  assert.match(await (await fetch(`${base}/sitemap.xml`)).text(), /\/courses</);
  const b = browser();
  await b.get('/apply?course=graphic-design-basics');
  const res = await b.post('/apply', { name: 'Test Student', email: 'student@test.local', phone: '0977000000', service: 'Course — Graphic Design Basics (from K1,200)', budget: '', timeline: '', description: 'Next intake please' });
  assert.equal(res.status, 302);
  assert.ok(get("SELECT id FROM service_requests WHERE service LIKE 'Course — Graphic Design Basics%'"));
});

test('team training page lists the one-week workshops and a quote request becomes a lead', async () => {
  const html = await (await fetch(`${base}/team-training?w=cyber-awareness-week`)).text();
  for (const name of ['Cyber Security Awareness Week', 'Digital Skills for Staff Week', 'Design and Branding Week']) assert.ok(html.includes(name), name);
  assert.match(html, /name="w_cyber-awareness-week" value="1" checked/);
  assert.match(await (await fetch(`${base}/sitemap.xml`)).text(), /\/team-training</);
  const b = browser();
  await b.get('/team-training');
  const bad = await b.post('/team-training', { company: 'Test Unit', name: 'Officer', email: 'bad' });
  assert.equal(bad.status, 422);
  const ok = await b.post('/team-training', { company: 'Test Unit', name: 'Officer', email: 'unit@test.local', people: '25', w_cyber_dummy: '', 'w_cyber-awareness-week': '1', 'w_digital-skills-week': '1', timeline: 'Within a month' });
  assert.equal(ok.status, 302);
  const row = get("SELECT * FROM service_requests WHERE service LIKE 'Team training%' ORDER BY id DESC");
  assert.equal(row.service, 'Team training — 2 workshops');
  assert.match(row.description, /Participants: 25/);
  assert.match(row.description, /Cyber Security Awareness Week/);
});
