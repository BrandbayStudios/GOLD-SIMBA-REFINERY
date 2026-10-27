const path = require('node:path');
const express = require('express');
const { all, get, run } = require('../db');
const lib = require('../lib');
const mailer = require('../mailer');
const config = require('../config');
const RESOURCES = require('./admin-resources');
const invoicing = require('../invoicing');
const { flash, requireStaff, requireAdmin, imageUpload, verifyCsrf, uploadedPath, discardUpload } = require('../middleware');

const router = express.Router();
router.use(requireStaff);
router.use((req, res, next) => {
  res.locals.resources = RESOURCES;
  res.locals.counts = {
    newRequests: get("SELECT COUNT(*) AS n FROM service_requests WHERE status = 'new'").n,
    unreadMessages: get('SELECT COUNT(*) AS n FROM contact_messages WHERE is_read = 0').n,
    pendingOrders: get("SELECT COUNT(*) AS n FROM orders WHERE status = 'pending'").n,
    overdueInvoices: invoicing.withTotals(all("SELECT * FROM invoices WHERE type = 'invoice' AND status = 'sent' AND due_date < ?", invoicing.today()))
      .filter((d) => d.effective === 'overdue').length,
  };
  next();
});

router.use('/invoices', require('./admin-invoices'));

const PAGE_SIZE = 25;
const page = (req) => Math.max(1, parseInt(req.query.page, 10) || 1);

function csv(rows, columns) {
  const esc = (v) => {
    let s = String(v ?? '');
    // Neutralise spreadsheet formulas.
    if (/^[=+\-@]/.test(s)) s = `'${s}`;
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  return [columns.join(','), ...rows.map((r) => columns.map((c) => esc(r[c])).join(','))].join('\n');
}

// ---------- Dashboard ----------

function billingSummary() {
  const cur = invoicing.defaultCurrency();
  const live = invoicing.list("type = 'invoice' AND status NOT IN ('draft','cancelled') AND currency = ?", [cur.code], { limit: 100000 });
  return {
    symbol: cur.symbol,
    outstanding: live.reduce((sum, d) => sum + Math.max(0, d.t.balance), 0),
    overdue: live.filter((d) => d.effective === 'overdue').reduce((sum, d) => sum + Math.max(0, d.t.balance), 0),
    openQuotes: get("SELECT COUNT(*) AS n FROM invoices WHERE type = 'quotation' AND status IN ('draft','sent')").n,
  };
}

router.get('/', (req, res) => {
  const revenue = get("SELECT COALESCE(SUM(subtotal), 0) AS total FROM orders WHERE payment_status = 'paid'").total;
  const quoted = get("SELECT COALESCE(SUM(quote_amount), 0) AS total FROM service_requests WHERE status IN ('quoted','in_progress','completed')").total;
  const byStatus = Object.fromEntries(Object.keys(lib.REQUEST_STATUSES).map((s) => [s, 0]));
  for (const r of all('SELECT status, COUNT(*) AS n FROM service_requests GROUP BY status')) byStatus[r.status] = r.n;
  const byService = all('SELECT service, COUNT(*) AS n FROM service_requests GROUP BY service ORDER BY n DESC LIMIT 6');
  const monthly = all(`
    SELECT strftime('%Y-%m', created_at) AS month, COUNT(*) AS n
    FROM service_requests WHERE created_at >= date('now', 'start of month', '-5 months')
    GROUP BY month ORDER BY month`);
  res.render('admin/dashboard', {
    title: 'Dashboard',
    stats: {
      requests: get('SELECT COUNT(*) AS n FROM service_requests').n,
      openRequests: get("SELECT COUNT(*) AS n FROM service_requests WHERE status NOT IN ('completed','cancelled')").n,
      orders: get('SELECT COUNT(*) AS n FROM orders').n,
      clients: get("SELECT COUNT(*) AS n FROM users WHERE role = 'client'").n,
      subscribers: get('SELECT COUNT(*) AS n FROM subscribers').n,
      revenue,
      quoted,
    },
    byStatus,
    byService,
    monthly,
    recentRequests: all('SELECT * FROM service_requests ORDER BY id DESC LIMIT 6'),
    recentOrders: all('SELECT * FROM orders ORDER BY id DESC LIMIT 5'),
    recentMessages: all('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 5'),
    billing: billingSummary(),
  });
});

// ---------- Service requests ----------

function requestFilters(req) {
  const where = [];
  const params = [];
  const status = lib.REQUEST_STATUSES[req.query.status] ? req.query.status : '';
  const q = lib.clean(req.query.q, 100);
  if (status) {
    where.push('status = ?');
    params.push(status);
  }
  if (q) {
    where.push('(reference LIKE ? OR name LIKE ? OR email LIKE ? OR company LIKE ? OR service LIKE ?)');
    params.push(...Array(5).fill(`%${q}%`));
  }
  return { sql: where.length ? `WHERE ${where.join(' AND ')}` : '', params, status, q };
}

router.get('/requests', (req, res) => {
  const f = requestFilters(req);
  const total = get(`SELECT COUNT(*) AS n FROM service_requests ${f.sql}`, ...f.params).n;
  const p = page(req);
  const requests = all(`SELECT * FROM service_requests ${f.sql} ORDER BY id DESC LIMIT ? OFFSET ?`, ...f.params, PAGE_SIZE, (p - 1) * PAGE_SIZE);
  res.render('admin/requests', { title: 'Service requests', requests, filters: f, page: p, pages: Math.ceil(total / PAGE_SIZE), total });
});

router.get('/requests.csv', (req, res) => {
  const f = requestFilters(req);
  const rows = all(`SELECT * FROM service_requests ${f.sql} ORDER BY id DESC`, ...f.params);
  res.attachment('service-requests.csv').type('text/csv').send(
    csv(rows, ['reference', 'created_at', 'status', 'name', 'email', 'phone', 'company', 'service', 'budget', 'timeline', 'quote_amount', 'description']),
  );
});

function findRequest(req) {
  return get('SELECT * FROM service_requests WHERE id = ?', req.params.id);
}

router.get('/requests/:id', (req, res, next) => {
  const request = findRequest(req);
  if (!request) return next();
  res.render('admin/request', {
    title: request.reference,
    request,
    messages: all('SELECT * FROM request_messages WHERE request_id = ? ORDER BY id', request.id),
    client: request.user_id ? get('SELECT id, name, email, phone, company, created_at FROM users WHERE id = ?', request.user_id) : null,
    invoices: invoicing.list('request_id = ?', [request.id]),
    invoicing,
  });
});

router.post('/requests/:id', (req, res, next) => {
  const request = findRequest(req);
  if (!request) return next();
  const status = lib.REQUEST_STATUSES[req.body.status] ? req.body.status : request.status;
  const quote = req.body.quote_amount === '' ? null : Number(req.body.quote_amount);
  if (quote !== null && (!Number.isFinite(quote) || quote < 0)) {
    flash(req, 'error', 'The quote must be a positive number.');
    return res.redirect(`/admin/requests/${request.id}`);
  }
  run(
    "UPDATE service_requests SET status = ?, quote_amount = ?, admin_notes = ?, updated_at = datetime('now') WHERE id = ?",
    status, quote, lib.clean(req.body.admin_notes, 5000), request.id,
  );

  const changes = [];
  if (status !== request.status) changes.push(`Status: ${lib.REQUEST_STATUSES[status]}`);
  if (quote !== null && quote !== request.quote_amount) {
    changes.push(`Quote: ${lib.money(quote)} (${config.depositPercent}% deposit: ${lib.money((quote * config.depositPercent) / 100)})`);
  }
  if (changes.length && req.body.notify_client) {
    mailer.send({
      to: request.email,
      subject: `Update on your request ${request.reference}`,
      text: `Hi ${request.name},\n\nThere is an update on your ${request.service} request:\n\n${changes.join('\n')}\n\nView the details and reply at ${config.baseUrl}/account/requests/${request.id}\n(Sign in or create an account with ${request.email}.)\n\nBrandBay Innovation Studios`,
    });
  }
  flash(req, 'success', 'Request updated.');
  res.redirect(`/admin/requests/${request.id}`);
});

router.post('/requests/:id/messages', (req, res, next) => {
  const request = findRequest(req);
  if (!request) return next();
  const body = lib.clean(req.body.body, 5000);
  if (body) {
    run('INSERT INTO request_messages (request_id, author_id, author_name, from_staff, body) VALUES (?, ?, ?, 1, ?)',
      request.id, req.user.id, req.user.name, body);
    run("UPDATE service_requests SET updated_at = datetime('now'), status = CASE status WHEN 'new' THEN 'reviewing' ELSE status END WHERE id = ?", request.id);
    mailer.send({
      to: request.email,
      subject: `New message about ${request.reference}`,
      text: `Hi ${request.name},\n\n${req.user.name} from BrandBay wrote:\n\n${body}\n\nReply at ${config.baseUrl}/account/requests/${request.id}\n\nBrandBay Innovation Studios`,
    });
  }
  res.redirect(`/admin/requests/${request.id}#messages`);
});

router.get('/requests/:id/attachment', (req, res, next) => {
  const request = findRequest(req);
  if (!request?.attachment) return next();
  res.download(path.join(config.privateDir, path.basename(request.attachment)));
});

router.post('/requests/:id/delete', requireAdmin, (req, res, next) => {
  const request = findRequest(req);
  if (!request) return next();
  run('DELETE FROM service_requests WHERE id = ?', request.id);
  if (request.attachment) discardUpload({ path: path.join(config.privateDir, path.basename(request.attachment)) });
  flash(req, 'success', `${request.reference} deleted.`);
  res.redirect('/admin/requests');
});

// ---------- Contact messages ----------

router.get('/messages', (req, res) => {
  const p = page(req);
  const total = get('SELECT COUNT(*) AS n FROM contact_messages').n;
  res.render('admin/messages', {
    title: 'Messages',
    messages: all('SELECT * FROM contact_messages ORDER BY id DESC LIMIT ? OFFSET ?', PAGE_SIZE, (p - 1) * PAGE_SIZE),
    page: p,
    pages: Math.ceil(total / PAGE_SIZE),
  });
});

router.get('/messages/:id', (req, res, next) => {
  const message = get('SELECT * FROM contact_messages WHERE id = ?', req.params.id);
  if (!message) return next();
  run('UPDATE contact_messages SET is_read = 1 WHERE id = ?', message.id);
  res.render('admin/message', { title: 'Message', message });
});

router.post('/messages/:id/delete', (req, res) => {
  run('DELETE FROM contact_messages WHERE id = ?', req.params.id);
  flash(req, 'success', 'Message deleted.');
  res.redirect('/admin/messages');
});

// ---------- Orders ----------

router.get('/orders', (req, res) => {
  const status = lib.ORDER_STATUSES[req.query.status] ? req.query.status : '';
  const p = page(req);
  const where = status ? 'WHERE status = ?' : '';
  const params = status ? [status] : [];
  const total = get(`SELECT COUNT(*) AS n FROM orders ${where}`, ...params).n;
  res.render('admin/orders', {
    title: 'Orders',
    orders: all(`SELECT * FROM orders ${where} ORDER BY id DESC LIMIT ? OFFSET ?`, ...params, PAGE_SIZE, (p - 1) * PAGE_SIZE),
    status,
    page: p,
    pages: Math.ceil(total / PAGE_SIZE),
  });
});

router.get('/orders/:id', (req, res, next) => {
  const order = get('SELECT * FROM orders WHERE id = ?', req.params.id);
  if (!order) return next();
  res.render('admin/order', { title: order.reference, order, items: all('SELECT * FROM order_items WHERE order_id = ?', order.id) });
});

router.post('/orders/:id', (req, res, next) => {
  const order = get('SELECT * FROM orders WHERE id = ?', req.params.id);
  if (!order) return next();
  const status = lib.ORDER_STATUSES[req.body.status] ? req.body.status : order.status;
  const payment = lib.PAYMENT_STATUSES[req.body.payment_status] ? req.body.payment_status : order.payment_status;
  run("UPDATE orders SET status = ?, payment_status = ?, updated_at = datetime('now') WHERE id = ?", status, payment, order.id);
  // Return stock when an order is cancelled.
  if (status === 'cancelled' && order.status !== 'cancelled') {
    for (const item of all('SELECT product_id, quantity FROM order_items WHERE order_id = ?', order.id)) {
      run('UPDATE products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', item.quantity, item.product_id);
    }
  }
  if ((status !== order.status || payment !== order.payment_status) && req.body.notify_client) {
    mailer.send({
      to: order.email,
      subject: `Order ${order.reference}: ${lib.ORDER_STATUSES[status]}`,
      text: `Hi ${order.name},\n\nYour order ${order.reference} is now: ${lib.ORDER_STATUSES[status]} (payment: ${lib.PAYMENT_STATUSES[payment]}).\n\nBrandBay Innovation Studios`,
    });
  }
  flash(req, 'success', 'Order updated.');
  res.redirect(`/admin/orders/${order.id}`);
});

// ---------- Subscribers ----------

router.get('/subscribers', (req, res) => {
  res.render('admin/subscribers', { title: 'Subscribers', subscribers: all('SELECT * FROM subscribers ORDER BY id DESC') });
});

router.get('/subscribers.csv', (req, res) => {
  res.attachment('subscribers.csv').type('text/csv').send(csv(all('SELECT email, created_at FROM subscribers ORDER BY id'), ['email', 'created_at']));
});

router.post('/subscribers/:id/delete', (req, res) => {
  run('DELETE FROM subscribers WHERE id = ?', req.params.id);
  flash(req, 'success', 'Subscriber removed.');
  res.redirect('/admin/subscribers');
});

// ---------- Users ----------

router.get('/users', requireAdmin, (req, res) => {
  res.render('admin/users', {
    title: 'Users',
    users: all(`
      SELECT u.id, u.name, u.email, u.phone, u.company, u.role, u.created_at,
        (SELECT COUNT(*) FROM service_requests r WHERE r.user_id = u.id) AS requests,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders
      FROM users u ORDER BY u.role = 'client', u.id DESC`),
  });
});

router.post('/users/:id/role', requireAdmin, (req, res) => {
  const role = ['client', 'staff', 'admin'].includes(req.body.role) ? req.body.role : null;
  if (!role) {
    flash(req, 'error', 'Unknown role.');
  } else if (Number(req.params.id) === req.user.id) {
    flash(req, 'error', 'You cannot change your own role.');
  } else {
    run('UPDATE users SET role = ? WHERE id = ?', role, req.params.id);
    flash(req, 'success', 'Role updated.');
  }
  res.redirect('/admin/users');
});

// ---------- Settings ----------

const SETTING_FIELDS = [
  { name: 'site_name', label: 'Business name' },
  { name: 'tagline', label: 'Tagline' },
  { name: 'hero_title', label: 'Home page headline' },
  { name: 'hero_text', label: 'Home page introduction', type: 'textarea' },
  { name: 'phone', label: 'Phone' },
  { name: 'whatsapp', label: 'WhatsApp number (digits only, with country code)' },
  { name: 'email', label: 'Email' },
  { name: 'address_1', label: 'Address (primary)' },
  { name: 'address_2', label: 'Address (secondary)' },
  { name: 'facebook_url', label: 'Facebook URL' },
  { name: 'instagram_url', label: 'Instagram URL' },
  { name: 'linkedin_url', label: 'LinkedIn URL' },
  { name: 'google_site_verification', label: 'Google Search Console verification code (content of the meta tag)' },
  { name: 'bing_site_verification', label: 'Bing Webmaster Tools verification code' },
  { name: 'payment_instructions', label: 'Payment instructions (shown after checkout)', type: 'textarea' },
  { name: 'terms', label: "Terms & Conditions (blank line between paragraphs; short lines become headings)", type: 'textarea', rows: 16 },
];

router.get('/settings', requireAdmin, (req, res) => {
  res.render('admin/settings', { title: 'Settings', fields: SETTING_FIELDS, values: lib.getSettings(), mailEnabled: mailer.enabled });
});

router.post('/settings', requireAdmin, (req, res) => {
  for (const f of SETTING_FIELDS) {
    let value = lib.clean(req.body[f.name], 20000);
    if (f.name.endsWith('_url') && value && !/^https?:\/\//i.test(value)) value = `https://${value}`;
    // Accept either the bare code or the whole <meta ... content="..."> tag pasted from Google or Bing.
    if (f.name.endsWith('_verification')) value = (value.match(/content=["']([^"']+)["']/) || [null, value])[1].replace(/[^\w-]/g, '');
    lib.setSetting(f.name, value);
  }
  flash(req, 'success', 'Settings saved.');
  res.redirect('/admin/settings');
});

// ---------- Content (generic CRUD) ----------

function resource(req, res, next) {
  const def = RESOURCES[req.params.resource];
  if (!def) return next('route');
  req.resource = def;
  res.locals.resourceKey = req.params.resource;
  res.locals.resource = def;
  next();
}

router.get('/content/:resource', resource, (req, res) => {
  const def = req.resource;
  const filterValue = def.filter ? lib.clean(req.query[def.filter], 60) : '';
  const rows = filterValue
    ? all(`SELECT * FROM ${def.table} WHERE ${def.filter} = ? ORDER BY ${def.fields.some((f) => f.name === 'sort_order') ? 'sort_order, ' : ''}id DESC`, filterValue)
    : all(`SELECT * FROM ${def.table} ORDER BY ${def.fields.some((f) => f.name === 'sort_order') ? 'sort_order, ' : ''}id DESC`);
  res.render('admin/content-list', { title: def.label, rows, filterValue });
});

router.get('/content/:resource/new', resource, (req, res) => {
  const row = {};
  for (const f of req.resource.fields) if (f.default !== undefined) row[f.name] = f.default;
  if (req.resource.filter && req.query[req.resource.filter]) row[req.resource.filter] = req.query[req.resource.filter];
  res.render('admin/content-form', { title: `New ${req.resource.singular}`, row });
});

router.get('/content/:resource/:id', resource, (req, res, next) => {
  const row = get(`SELECT * FROM ${req.resource.table} WHERE id = ?`, req.params.id);
  if (!row) return next();
  res.render('admin/content-form', { title: `Edit ${req.resource.singular}`, row });
});

function readFields(def, body, files, existing = {}) {
  const values = {};
  const errors = [];
  for (const f of def.fields) {
    let v;
    switch (f.type) {
      case 'checkbox':
        v = body[f.name] ? 1 : 0;
        break;
      case 'number':
      case 'money': {
        const raw = String(body[f.name] ?? '').trim();
        if (raw === '') v = f.nullable ? null : f.type === 'money' && f.required ? NaN : f.default ?? 0;
        else v = f.type === 'money' ? Number(raw) : parseInt(raw, 10);
        if (Number.isNaN(v)) errors.push(`${f.label} must be a number.`);
        else if (v !== null && v < (f.min ?? 0)) errors.push(`${f.label} must be at least ${f.min ?? 0}.`);
        else if (v !== null && f.max !== undefined && v > f.max) errors.push(`${f.label} must be at most ${f.max}.`);
        break;
      }
      case 'image': {
        const file = files?.[f.name]?.[0];
        if (file) v = uploadedPath(file);
        else if (body[`${f.name}_remove`]) v = null;
        else v = existing[f.name] ?? null;
        if (f.required && !v) errors.push(`${f.label} is required.`);
        break;
      }
      case 'select':
        v = f.options.includes(body[f.name]) ? body[f.name] : f.options[0];
        break;
      default:
        v = lib.clean(body[f.name], f.type === 'textarea' ? 50000 : 300);
        if (f.required && !v) errors.push(`${f.label} is required.`);
    }
    values[f.name] = v;
  }
  return { values, errors };
}

function imageFields(def) {
  return def.fields.filter((f) => f.type === 'image').map((f) => ({ name: f.name, maxCount: 1 }));
}

function discardFiles(files) {
  for (const list of Object.values(files || {})) for (const f of list) discardUpload(f);
}

function contentUpload(req, res, next) {
  imageUpload.fields(imageFields(req.resource))(req, res, next);
}

function saveContent(req, res, next) {
  const def = req.resource;
  const existing = req.params.id ? get(`SELECT * FROM ${def.table} WHERE id = ?`, req.params.id) : null;
  if (req.params.id && !existing) return next();
  const { values, errors } = readFields(def, req.body, req.files, existing || {});
  if (def.table === 'products' && values.published && !(values.price > 0)) errors.push('Set a price before publishing a product.');
  if (errors.length) {
    discardFiles(req.files);
    return res.status(422).render('admin/content-form', {
      title: existing ? `Edit ${def.singular}` : `New ${def.singular}`,
      row: { ...existing, ...values },
      errors,
    });
  }
  if (def.slugFrom) values.slug = lib.uniqueSlug(def.table, req.body.slug || values[def.slugFrom], existing?.id);
  if (def.table === 'posts' && values.published && !existing?.published_at) values.published_at = new Date().toISOString().slice(0, 19).replace('T', ' ');

  const cols = Object.keys(values);
  if (existing) {
    run(`UPDATE ${def.table} SET ${cols.map((c) => `${c} = ?`).join(', ')} WHERE id = ?`, ...cols.map((c) => values[c]), existing.id);
  } else {
    run(`INSERT INTO ${def.table} (${cols.join(', ')}) VALUES (${cols.map(() => '?').join(', ')})`, ...cols.map((c) => values[c]));
  }
  flash(req, 'success', `${def.singular[0].toUpperCase()}${def.singular.slice(1)} saved.`);
  res.redirect(`/admin/content/${req.params.resource}`);
}

router.post('/content/:resource', resource, contentUpload, verifyCsrf, saveContent);
router.post('/content/:resource/:id', resource, contentUpload, verifyCsrf, saveContent);

router.post('/content/:resource/:id/delete', resource, (req, res) => {
  run(`DELETE FROM ${req.resource.table} WHERE id = ?`, req.params.id);
  flash(req, 'success', `${req.resource.singular[0].toUpperCase()}${req.resource.singular.slice(1)} deleted.`);
  res.redirect(`/admin/content/${req.params.resource}`);
});

module.exports = router;
