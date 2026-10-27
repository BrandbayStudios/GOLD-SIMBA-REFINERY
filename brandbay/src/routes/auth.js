const crypto = require('node:crypto');
const express = require('express');
const bcrypt = require('bcryptjs');
const rateLimit = require('express-rate-limit');
const { get, run } = require('../db');
const lib = require('../lib');
const mailer = require('../mailer');
const config = require('../config');
const { flash } = require('../middleware');

const router = express.Router();

const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  limit: process.env.NODE_ENV === 'test' ? 1000 : 10,
  skipSuccessfulRequests: true,
  handler: (req, res, next) => {
    const err = new Error('Too many attempts. Please wait 15 minutes and try again.');
    err.status = 429;
    next(err);
  },
});

// Only allow redirects back into this site.
function safeNext(value, fallback) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : fallback;
}

function home(user) {
  return user.role === 'client' ? '/account' : '/admin';
}

// Requests and orders made with this email before the account existed now belong to it.
function claimGuestRecords(user) {
  run('UPDATE service_requests SET user_id = ? WHERE user_id IS NULL AND email = ? COLLATE NOCASE', user.id, user.email);
  run('UPDATE orders SET user_id = ? WHERE user_id IS NULL AND email = ? COLLATE NOCASE', user.id, user.email);
}

function signIn(req, user, done) {
  req.session.regenerate((err) => {
    if (err) return done(err);
    req.session.userId = user.id;
    done();
  });
}

router.get('/login', (req, res) => {
  if (req.user) return res.redirect(home(req.user));
  res.render('auth/login', { noindex: true, title: 'Sign in', next: safeNext(req.query.next, ''), form: {} });
});

router.post('/login', authLimiter, (req, res, next) => {
  const email = lib.clean(req.body.email, 200);
  const user = get('SELECT * FROM users WHERE email = ?', email);
  const ok = user && bcrypt.compareSync(String(req.body.password || ''), user.password_hash);
  if (!ok) {
    return res.status(401).render('auth/login', { noindex: true,
      title: 'Sign in', next: safeNext(req.body.next, ''), form: { email }, errors: ['Incorrect email or password.'],
    });
  }
  const cart = req.session.cart;
  signIn(req, user, (err) => {
    if (err) return next(err);
    if (cart) req.session.cart = cart;
    claimGuestRecords(user);
    flash(req, 'success', `Welcome back, ${user.name.split(' ')[0]}.`);
    res.redirect(safeNext(req.body.next, home(user)));
  });
});

router.get('/register', (req, res) => {
  if (req.user) return res.redirect(home(req.user));
  res.render('auth/register', { noindex: true, title: 'Create account', form: {}, next: safeNext(req.query.next, '') });
});

router.post('/register', authLimiter, (req, res, next) => {
  const form = {
    name: lib.clean(req.body.name, 120),
    email: lib.clean(req.body.email, 200),
    phone: lib.clean(req.body.phone, 40),
    company: lib.clean(req.body.company, 160),
  };
  const password = String(req.body.password || '');
  const errors = [];
  if (!form.name) errors.push('Please enter your name.');
  if (!lib.isEmail(form.email)) errors.push('Please enter a valid email address.');
  if (password.length < 8) errors.push('Your password must be at least 8 characters.');
  if (password !== req.body.password_confirm) errors.push('The passwords do not match.');
  if (!errors.length && get('SELECT id FROM users WHERE email = ?', form.email)) {
    errors.push('An account with that email already exists. Try signing in instead.');
  }
  if (errors.length) {
    return res.status(422).render('auth/register', { noindex: true, title: 'Create account', form, errors, next: safeNext(req.body.next, '') });
  }
  const { lastInsertRowid } = run(
    'INSERT INTO users (name, email, phone, company, password_hash) VALUES (?, ?, ?, ?, ?)',
    form.name, form.email, form.phone, form.company, bcrypt.hashSync(password, 12),
  );
  const user = get('SELECT * FROM users WHERE id = ?', lastInsertRowid);
  claimGuestRecords(user);
  const cart = req.session.cart;
  signIn(req, user, (err) => {
    if (err) return next(err);
    if (cart) req.session.cart = cart;
    flash(req, 'success', 'Your account is ready. Any requests made with this email now appear on your dashboard.');
    res.redirect(safeNext(req.body.next, '/account'));
  });
});

router.post('/logout', (req, res, next) => {
  req.session.destroy((err) => {
    if (err) return next(err);
    res.clearCookie('bb.sid');
    res.redirect('/');
  });
});

router.get('/forgot-password', (req, res) => {
  res.render('auth/forgot', { noindex: true, title: 'Reset password' });
});

router.post('/forgot-password', authLimiter, async (req, res) => {
  const email = lib.clean(req.body.email, 200);
  const user = lib.isEmail(email) && get('SELECT id, name, email FROM users WHERE email = ?', email);
  if (user) {
    const token = crypto.randomBytes(32).toString('hex');
    const hash = crypto.createHash('sha256').update(token).digest('hex');
    run('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?', hash, Date.now() + 60 * 60 * 1000, user.id);
    await mailer.send({
      to: user.email,
      subject: 'Reset your BrandBay password',
      text: `Hi ${user.name},\n\nUse this link within one hour to choose a new password:\n${config.baseUrl}/reset-password/${token}\n\nIf you did not ask for this, you can ignore this email.`,
    });
  }
  // Same response either way so the form cannot be used to discover accounts.
  flash(req, 'success', 'If that email has an account, a reset link is on its way.');
  res.redirect('/login');
});

function userForToken(token) {
  const hash = crypto.createHash('sha256').update(String(token)).digest('hex');
  return get('SELECT id FROM users WHERE reset_token = ? AND reset_expires > ?', hash, Date.now());
}

router.get('/reset-password/:token', (req, res) => {
  if (!userForToken(req.params.token)) {
    flash(req, 'error', 'That reset link is invalid or has expired.');
    return res.redirect('/forgot-password');
  }
  res.render('auth/reset', { noindex: true, title: 'Choose a new password', token: req.params.token });
});

router.post('/reset-password/:token', authLimiter, (req, res) => {
  const user = userForToken(req.params.token);
  if (!user) {
    flash(req, 'error', 'That reset link is invalid or has expired.');
    return res.redirect('/forgot-password');
  }
  const password = String(req.body.password || '');
  if (password.length < 8 || password !== req.body.password_confirm) {
    return res.status(422).render('auth/reset', { noindex: true,
      title: 'Choose a new password', token: req.params.token,
      errors: ['Passwords must match and be at least 8 characters.'],
    });
  }
  run('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?', bcrypt.hashSync(password, 12), user.id);
  // Sign the account out everywhere.
  run('DELETE FROM sessions WHERE data LIKE ? OR data LIKE ?', `%"userId":${user.id},%`, `%"userId":${user.id}}%`);
  flash(req, 'success', 'Your password has been changed. Please sign in.');
  res.redirect('/login');
});

module.exports = router;
