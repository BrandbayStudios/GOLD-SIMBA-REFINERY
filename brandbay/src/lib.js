const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { get, all, run } = require('./db');
const config = require('./config');

const REQUEST_STATUSES = {
  new: 'New',
  reviewing: 'Under review',
  quoted: 'Quote sent',
  in_progress: 'In progress',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

const ORDER_STATUSES = {
  pending: 'Pending',
  confirmed: 'Confirmed',
  in_production: 'In production',
  ready: 'Ready',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
};

const PAYMENT_STATUSES = { unpaid: 'Unpaid', deposit: 'Deposit paid', paid: 'Paid', refunded: 'Refunded' };

const PAYMENT_METHODS = {
  mobile_money: 'Mobile Money (MTN / Airtel)',
  bank_transfer: 'Bank transfer',
  cash: 'Cash on collection',
};

const BUDGETS = ['Under K1,000', 'K1,000 – K5,000', 'K5,000 – K15,000', 'K15,000 – K50,000', 'Above K50,000', 'Not sure yet'];
const TIMELINES = ['As soon as possible', 'Within 2 weeks', 'Within a month', '1–3 months', 'Flexible'];

function slugify(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80) || crypto.randomBytes(4).toString('hex');
}

function uniqueSlug(table, base, excludeId) {
  const root = slugify(base);
  let slug = root;
  for (let i = 2; ; i++) {
    const row = get(`SELECT id FROM ${table} WHERE slug = ?`, slug);
    if (!row || row.id === Number(excludeId)) return slug;
    slug = `${root}-${i}`;
  }
}

function money(value) {
  const n = Number(value) || 0;
  return `${config.currency}${n.toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function makeReference(prefix, id) {
  return `${prefix}-${new Date().getFullYear()}-${String(id).padStart(4, '0')}`;
}

function formatDate(value, withTime = false) {
  if (!value) return '';
  const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
  if (Number.isNaN(d.getTime())) return value;
  const opts = { day: 'numeric', month: 'short', year: 'numeric' };
  if (withTime) Object.assign(opts, { hour: '2-digit', minute: '2-digit' });
  return d.toLocaleString('en-GB', opts);
}

// Plain text -> paragraphs; content is escaped by the caller's template.
function paragraphs(text) {
  return String(text || '').split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean);
}

function lines(text) {
  return String(text || '').split('\n').map((l) => l.trim()).filter(Boolean);
}

const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || '').trim());

function clean(v, max = 5000) {
  return String(v ?? '').trim().slice(0, max);
}

// Service card photo: /media/services/<slug>.webp when it exists, otherwise a generic studio photo.
const serviceImageCache = new Map();
function serviceImage(slug) {
  if (!serviceImageCache.has(slug)) {
    const file = path.join(config.root, 'public', 'media', 'services', `${path.basename(String(slug))}.webp`);
    serviceImageCache.set(slug, fs.existsSync(file) ? `/media/services/${path.basename(String(slug))}.webp` : '/media/bg/bg-laptop-desk.webp');
  }
  return serviceImageCache.get(slug);
}

function getSettings() {
  const out = {};
  for (const row of all('SELECT key, value FROM settings')) out[row.key] = row.value;
  return out;
}

function setSetting(key, value) {
  run('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', key, value);
}

module.exports = {
  REQUEST_STATUSES,
  ORDER_STATUSES,
  PAYMENT_STATUSES,
  PAYMENT_METHODS,
  BUDGETS,
  TIMELINES,
  slugify,
  uniqueSlug,
  money,
  makeReference,
  formatDate,
  paragraphs,
  lines,
  isEmail,
  clean,
  getSettings,
  serviceImage,
  setSetting,
};
