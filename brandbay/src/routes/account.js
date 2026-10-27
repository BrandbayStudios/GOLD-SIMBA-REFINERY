const path = require('node:path');
const express = require('express');
const bcrypt = require('bcryptjs');
const { all, get, run } = require('../db');
const lib = require('../lib');
const mailer = require('../mailer');
const config = require('../config');
const { flash, requireAuth } = require('../middleware');
const invoicing = require('../invoicing');

const router = express.Router();
router.use(requireAuth);
router.use((req, res, next) => {
  res.locals.noindex = true;
  next();
});

function ownRequest(req) {
  return get('SELECT * FROM service_requests WHERE id = ? AND user_id = ?', req.params.id, req.user.id);
}

router.get('/', (req, res) => {
  const requests = all('SELECT * FROM service_requests WHERE user_id = ? ORDER BY id DESC', req.user.id);
  const orders = all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', req.user.id);
  const invoices = invoicing.list(...clientInvoices(req.user));
  res.render('account/dashboard', { title: 'My dashboard', requests, orders, invoices, invoicing });
});

router.get('/requests/:id', (req, res, next) => {
  const request = ownRequest(req);
  if (!request) return next();
  const messages = all('SELECT * FROM request_messages WHERE request_id = ? ORDER BY id', request.id);
  res.render('account/request', { title: request.reference, request, messages });
});

router.post('/requests/:id/messages', (req, res, next) => {
  const request = ownRequest(req);
  if (!request) return next();
  const body = lib.clean(req.body.body, 5000);
  if (body) {
    run('INSERT INTO request_messages (request_id, author_id, author_name, from_staff, body) VALUES (?, ?, ?, 0, ?)',
      request.id, req.user.id, req.user.name, body);
    run("UPDATE service_requests SET updated_at = datetime('now') WHERE id = ?", request.id);
    mailer.notifyStudio(`New message on ${request.reference}`,
      `${req.user.name} wrote:\n\n${body}\n\n${config.baseUrl}/admin/requests/${request.id}`);
  }
  res.redirect(`/account/requests/${request.id}#messages`);
});

router.post('/requests/:id/accept', (req, res, next) => {
  const request = ownRequest(req);
  if (!request) return next();
  if (request.status === 'quoted') {
    run("UPDATE service_requests SET status = 'in_progress', updated_at = datetime('now') WHERE id = ?", request.id);
    run('INSERT INTO request_messages (request_id, author_id, author_name, from_staff, body) VALUES (?, ?, ?, 0, ?)',
      request.id, req.user.id, req.user.name, `I accept the quote of ${lib.money(request.quote_amount)}.`);
    mailer.notifyStudio(`Quote accepted: ${request.reference}`,
      `${req.user.name} accepted the quote of ${lib.money(request.quote_amount)}.\n\n${config.baseUrl}/admin/requests/${request.id}`);
    flash(req, 'success', 'Quote accepted. Our team will send deposit payment details and get started.');
  }
  res.redirect(`/account/requests/${request.id}`);
});

router.get('/requests/:id/attachment', (req, res, next) => {
  const request = ownRequest(req);
  if (!request?.attachment) return next();
  res.download(path.join(config.privateDir, path.basename(request.attachment)));
});

// Clients see documents addressed to their account or their email address, once they are no longer drafts.
function clientInvoices(user) {
  return ["status != 'draft' AND (user_id = ? OR client_email = ? COLLATE NOCASE)", [user.id, user.email]];
}

router.get('/invoices/:id', (req, res, next) => {
  const [where, params] = clientInvoices(req.user);
  const doc = invoicing.load(`id = ? AND ${where}`, req.params.id, ...params);
  if (!doc) return next();
  res.render('account/invoice', { title: `${doc.label} ${doc.inv.number}`, doc, invoicing, is: invoicing.invoiceSettings() });
});

router.get('/orders/:id', (req, res, next) => {
  const order = get('SELECT * FROM orders WHERE id = ? AND user_id = ?', req.params.id, req.user.id);
  if (!order) return next();
  const items = all('SELECT * FROM order_items WHERE order_id = ?', order.id);
  res.render('account/order', { title: order.reference, order, items });
});

router.get('/profile', (req, res) => {
  res.render('account/profile', { title: 'My profile', form: req.user });
});

router.post('/profile', (req, res) => {
  const form = {
    name: lib.clean(req.body.name, 120),
    phone: lib.clean(req.body.phone, 40),
    company: lib.clean(req.body.company, 160),
  };
  if (!form.name) {
    return res.status(422).render('account/profile', { title: 'My profile', form: { ...req.user, ...form }, errors: ['Please enter your name.'] });
  }
  run('UPDATE users SET name = ?, phone = ?, company = ? WHERE id = ?', form.name, form.phone, form.company, req.user.id);
  flash(req, 'success', 'Profile updated.');
  res.redirect('/account/profile');
});

router.post('/password', (req, res) => {
  const user = get('SELECT password_hash FROM users WHERE id = ?', req.user.id);
  const next_ = String(req.body.new_password || '');
  let error;
  if (!bcrypt.compareSync(String(req.body.current_password || ''), user.password_hash)) error = 'Your current password is incorrect.';
  else if (next_.length < 8) error = 'Your new password must be at least 8 characters.';
  else if (next_ !== req.body.new_password_confirm) error = 'The new passwords do not match.';
  if (error) {
    flash(req, 'error', error);
  } else {
    run('UPDATE users SET password_hash = ? WHERE id = ?', bcrypt.hashSync(next_, 12), req.user.id);
    flash(req, 'success', 'Password changed.');
  }
  res.redirect('/account/profile');
});

module.exports = router;
