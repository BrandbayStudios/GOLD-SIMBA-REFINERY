'use strict';

const path = require('path');
const crypto = require('crypto');
const express = require('express');
const db = require('./db');
const { issueToken, requireAuth } = require('./auth');

const app = express();
app.use(express.json());

const PORT = process.env.PORT || 4000;
const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'ADMINGSR2026';

const PAYMENT_STATUSES = ['Pending', 'Partially Paid', 'Paid', 'Refunded', 'Cancelled'];
const CURRENCIES = ['ZMW', 'USD'];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function round2(n) {
  return Math.round((Number(n) || 0) * 100) / 100;
}

function computeBalance(assayingFee, amountPaid) {
  return round2((Number(assayingFee) || 0) - (Number(amountPaid) || 0));
}

function generateReferenceNumber() {
  const existing = db.prepare('SELECT 1 FROM bookings WHERE referenceNumber = ?');
  for (let attempt = 0; attempt < 25; attempt++) {
    const digits = String(crypto.randomInt(0, 1000000)).padStart(6, '0');
    const ref = 'GSR-' + digits;
    if (!existing.get(ref)) return ref;
  }
  throw new Error('Could not generate a unique reference number.');
}

function str(v) {
  return v == null ? '' : String(v);
}
function num(v, fallback) {
  const n = Number(v);
  return Number.isFinite(n) ? n : (fallback == null ? 0 : fallback);
}

function sanitizeCurrency(v) {
  return CURRENCIES.includes(v) ? v : 'ZMW';
}
function sanitizeStatus(v) {
  return PAYMENT_STATUSES.includes(v) ? v : 'Pending';
}

function bookingRowToJson(row) {
  return {
    referenceNumber: row.referenceNumber,
    dateOfBooking: row.dateOfBooking,
    testingDate: row.testingDate,
    testingTime: row.testingTime,
    testingType: row.testingType,
    bookingMadeBy: row.bookingMadeBy,
    sellerName: row.sellerName,
    sellerContact: row.sellerContact,
    sellerNRC: row.sellerNRC,
    sellerCompany: row.sellerCompany,
    sellerAddress: row.sellerAddress,
    buyerName: row.buyerName,
    buyerContact: row.buyerContact,
    quantity: row.quantity,
    quantityUnit: row.quantityUnit,
    assayingFee: row.assayingFee,
    amountPaid: row.amountPaid,
    balance: row.balance,
    currency: row.currency,
    paymentStatus: row.paymentStatus,
    notes: row.notes,
    createdAt: row.createdAt,
    paidAt: row.paidAt
  };
}

// Fields a public visitor is allowed to see when they submit a booking
// request — never the fee, payment status, or who logged it.
function publicRequestConfirmation(row) {
  return {
    referenceNumber: row.referenceNumber,
    dateOfBooking: row.dateOfBooking,
    testingDate: row.testingDate,
    testingTime: row.testingTime,
    testingType: row.testingType,
    quantity: row.quantity,
    quantityUnit: row.quantityUnit,
    sellerName: row.sellerName,
    createdAt: row.createdAt
  };
}

// Fields the customer tracker (#track) is allowed to see. Deliberately
// excludes notes, sellerNRC, sellerAddress, bookingMadeBy, buyer details,
// and seller contact/company — everything the tracker's UI actually reads.
function trackerBookingView(row) {
  return {
    referenceNumber: row.referenceNumber,
    dateOfBooking: row.dateOfBooking,
    testingDate: row.testingDate,
    testingTime: row.testingTime,
    testingType: row.testingType,
    quantity: row.quantity,
    quantityUnit: row.quantityUnit,
    sellerName: row.sellerName,
    assayingFee: row.assayingFee,
    amountPaid: row.amountPaid,
    balance: row.balance,
    currency: row.currency,
    paymentStatus: row.paymentStatus
  };
}

function serviceRowToJson(row) {
  return {
    serviceId: row.serviceId,
    name: row.name,
    description: row.description,
    price: row.price,
    currency: row.currency,
    unit: row.unit,
    turnaround: row.turnaround,
    active: !!row.active,
    createdAt: row.createdAt,
    updatedAt: row.updatedAt
  };
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

// GET /api/services — active services only, for the public booking form's
// Testing Type selector.
app.get('/api/services', (req, res) => {
  const rows = db.prepare('SELECT * FROM services WHERE active = 1').all();
  res.json({ services: rows.map(serviceRowToJson) });
});

// POST /api/request — a public visitor submitting a booking request from
// the contact form. Only customer-facing fields are accepted; fee, amount
// paid, balance, payment status, and who-made-the-booking are always
// server-set, never taken from the request body.
app.post('/api/request', (req, res) => {
  const body = req.body || {};

  if (!str(body.sellerName).trim()) {
    return res.status(400).json({ error: 'Your name is required.' });
  }

  const now = new Date();
  const referenceNumber = generateReferenceNumber();
  const row = {
    referenceNumber,
    dateOfBooking: now.toISOString().slice(0, 10),
    testingDate: str(body.testingDate).trim(),
    testingTime: str(body.testingTime).trim(),
    testingType: str(body.testingType).trim(),
    bookingMadeBy: 'Website (self-service)',
    sellerName: str(body.sellerName).trim(),
    sellerContact: str(body.sellerContact).trim(),
    sellerNRC: str(body.sellerNRC).trim(),
    sellerCompany: str(body.sellerCompany).trim(),
    sellerAddress: str(body.sellerAddress).trim(),
    buyerName: str(body.buyerName).trim(),
    buyerContact: str(body.buyerContact).trim(),
    quantity: num(body.quantity, 0),
    quantityUnit: str(body.quantityUnit).trim() || 'g',
    assayingFee: 0,
    amountPaid: 0,
    balance: 0,
    currency: 'ZMW',
    paymentStatus: 'Pending',
    notes: str(body.notes).trim(),
    createdAt: now.toISOString(),
    paidAt: null
  };

  db.prepare(`
    INSERT INTO bookings (
      referenceNumber, dateOfBooking, testingDate, testingTime, testingType, bookingMadeBy,
      sellerName, sellerContact, sellerNRC, sellerCompany, sellerAddress,
      buyerName, buyerContact, quantity, quantityUnit,
      assayingFee, amountPaid, balance, currency, paymentStatus, notes, createdAt, paidAt
    ) VALUES (
      @referenceNumber, @dateOfBooking, @testingDate, @testingTime, @testingType, @bookingMadeBy,
      @sellerName, @sellerContact, @sellerNRC, @sellerCompany, @sellerAddress,
      @buyerName, @buyerContact, @quantity, @quantityUnit,
      @assayingFee, @amountPaid, @balance, @currency, @paymentStatus, @notes, @createdAt, @paidAt
    )
  `).run(row);

  res.status(201).json({ booking: publicRequestConfirmation(row) });
});

// GET /api/lookup?ref=REFERENCE — the customer booking tracker (#track).
app.get('/api/lookup', (req, res) => {
  const ref = str(req.query.ref).trim().toUpperCase();
  if (!ref) return res.status(400).json({ error: 'Enter a reference number.' });
  const row = db.prepare('SELECT * FROM bookings WHERE referenceNumber = ?').get(ref);
  if (!row) return res.status(404).json({ error: "We couldn't find a booking with that reference number." });
  res.json({ booking: trackerBookingView(row) });
});

// POST /api/login — staff sign-in, checked server-side against
// ADMIN_USERNAME/ADMIN_PASSWORD.
app.post('/api/login', (req, res) => {
  const { username, password } = req.body || {};
  if (username === ADMIN_USERNAME && password === ADMIN_PASSWORD) {
    return res.json({ token: issueToken(username) });
  }
  res.status(401).json({ error: 'Invalid username or password.' });
});

// ---------------------------------------------------------------------------
// Admin API (bearer token required)
// ---------------------------------------------------------------------------

const admin = express.Router();
admin.use(requireAuth);

// ---- Bookings ----

admin.get('/bookings', (req, res) => {
  const rows = db.prepare('SELECT * FROM bookings ORDER BY createdAt DESC').all();
  res.json({ bookings: rows.map(bookingRowToJson) });
});

admin.post('/bookings', (req, res) => {
  const body = req.body || {};
  if (!str(body.sellerName).trim()) {
    return res.status(400).json({ error: 'Seller name is required.' });
  }

  const now = new Date();
  const assayingFee = num(body.assayingFee, 0);
  const amountPaid = num(body.amountPaid, 0);
  const paymentStatus = sanitizeStatus(body.paymentStatus);
  const row = {
    referenceNumber: generateReferenceNumber(),
    dateOfBooking: str(body.dateOfBooking).trim() || now.toISOString().slice(0, 10),
    testingDate: str(body.testingDate).trim(),
    testingTime: str(body.testingTime).trim(),
    testingType: str(body.testingType).trim(),
    bookingMadeBy: str(body.bookingMadeBy).trim(),
    sellerName: str(body.sellerName).trim(),
    sellerContact: str(body.sellerContact).trim(),
    sellerNRC: str(body.sellerNRC).trim(),
    sellerCompany: str(body.sellerCompany).trim(),
    sellerAddress: str(body.sellerAddress).trim(),
    buyerName: str(body.buyerName).trim(),
    buyerContact: str(body.buyerContact).trim(),
    quantity: num(body.quantity, 0),
    quantityUnit: str(body.quantityUnit).trim() || 'g',
    assayingFee,
    amountPaid,
    balance: computeBalance(assayingFee, amountPaid),
    currency: sanitizeCurrency(body.currency),
    paymentStatus,
    notes: str(body.notes).trim(),
    createdAt: now.toISOString(),
    paidAt: paymentStatus === 'Paid' ? now.toISOString() : null
  };

  db.prepare(`
    INSERT INTO bookings (
      referenceNumber, dateOfBooking, testingDate, testingTime, testingType, bookingMadeBy,
      sellerName, sellerContact, sellerNRC, sellerCompany, sellerAddress,
      buyerName, buyerContact, quantity, quantityUnit,
      assayingFee, amountPaid, balance, currency, paymentStatus, notes, createdAt, paidAt
    ) VALUES (
      @referenceNumber, @dateOfBooking, @testingDate, @testingTime, @testingType, @bookingMadeBy,
      @sellerName, @sellerContact, @sellerNRC, @sellerCompany, @sellerAddress,
      @buyerName, @buyerContact, @quantity, @quantityUnit,
      @assayingFee, @amountPaid, @balance, @currency, @paymentStatus, @notes, @createdAt, @paidAt
    )
  `).run(row);

  res.status(201).json({ booking: bookingRowToJson(row) });
});

admin.put('/bookings/:reference', (req, res) => {
  const reference = str(req.params.reference).trim().toUpperCase();
  const existing = db.prepare('SELECT * FROM bookings WHERE referenceNumber = ?').get(reference);
  if (!existing) return res.status(404).json({ error: 'Booking not found.' });

  const body = req.body || {};
  const has = (key) => Object.prototype.hasOwnProperty.call(body, key);

  const assayingFee = has('assayingFee') ? num(body.assayingFee, existing.assayingFee) : existing.assayingFee;
  const amountPaid = has('amountPaid') ? num(body.amountPaid, existing.amountPaid) : existing.amountPaid;
  const paymentStatus = has('paymentStatus') ? sanitizeStatus(body.paymentStatus) : existing.paymentStatus;
  const balance = computeBalance(assayingFee, amountPaid);

  let paidAt = existing.paidAt;
  if (paymentStatus === 'Paid' && existing.paymentStatus !== 'Paid') {
    paidAt = new Date().toISOString();
  } else if (paymentStatus !== 'Paid') {
    paidAt = null;
  }

  const merged = {
    referenceNumber: reference,
    dateOfBooking: has('dateOfBooking') ? str(body.dateOfBooking).trim() : existing.dateOfBooking,
    testingDate: has('testingDate') ? str(body.testingDate).trim() : existing.testingDate,
    testingTime: has('testingTime') ? str(body.testingTime).trim() : existing.testingTime,
    testingType: has('testingType') ? str(body.testingType).trim() : existing.testingType,
    bookingMadeBy: has('bookingMadeBy') ? str(body.bookingMadeBy).trim() : existing.bookingMadeBy,
    sellerName: has('sellerName') ? str(body.sellerName).trim() : existing.sellerName,
    sellerContact: has('sellerContact') ? str(body.sellerContact).trim() : existing.sellerContact,
    sellerNRC: has('sellerNRC') ? str(body.sellerNRC).trim() : existing.sellerNRC,
    sellerCompany: has('sellerCompany') ? str(body.sellerCompany).trim() : existing.sellerCompany,
    sellerAddress: has('sellerAddress') ? str(body.sellerAddress).trim() : existing.sellerAddress,
    buyerName: has('buyerName') ? str(body.buyerName).trim() : existing.buyerName,
    buyerContact: has('buyerContact') ? str(body.buyerContact).trim() : existing.buyerContact,
    quantity: has('quantity') ? num(body.quantity, existing.quantity) : existing.quantity,
    quantityUnit: has('quantityUnit') ? (str(body.quantityUnit).trim() || 'g') : existing.quantityUnit,
    assayingFee,
    amountPaid,
    balance,
    currency: has('currency') ? sanitizeCurrency(body.currency) : existing.currency,
    paymentStatus,
    notes: has('notes') ? str(body.notes).trim() : existing.notes,
    paidAt
  };

  db.prepare(`
    UPDATE bookings SET
      dateOfBooking=@dateOfBooking, testingDate=@testingDate, testingTime=@testingTime,
      testingType=@testingType, bookingMadeBy=@bookingMadeBy,
      sellerName=@sellerName, sellerContact=@sellerContact, sellerNRC=@sellerNRC,
      sellerCompany=@sellerCompany, sellerAddress=@sellerAddress,
      buyerName=@buyerName, buyerContact=@buyerContact,
      quantity=@quantity, quantityUnit=@quantityUnit,
      assayingFee=@assayingFee, amountPaid=@amountPaid, balance=@balance,
      currency=@currency, paymentStatus=@paymentStatus, notes=@notes, paidAt=@paidAt
    WHERE referenceNumber=@referenceNumber
  `).run(merged);

  const updated = db.prepare('SELECT * FROM bookings WHERE referenceNumber = ?').get(reference);
  res.json({ booking: bookingRowToJson(updated) });
});

admin.delete('/bookings/:reference', (req, res) => {
  const reference = str(req.params.reference).trim().toUpperCase();
  const result = db.prepare('DELETE FROM bookings WHERE referenceNumber = ?').run(reference);
  if (result.changes === 0) return res.status(404).json({ error: 'Booking not found.' });
  res.json({ ok: true });
});

// ---- Services ----

admin.get('/services', (req, res) => {
  const rows = db.prepare('SELECT * FROM services').all();
  res.json({ services: rows.map(serviceRowToJson) });
});

admin.post('/services', (req, res) => {
  const body = req.body || {};
  if (!str(body.name).trim()) {
    return res.status(400).json({ error: 'Service name is required.' });
  }
  const now = new Date().toISOString();
  const row = {
    serviceId: crypto.randomUUID(),
    name: str(body.name).trim(),
    description: str(body.description).trim(),
    price: body.price === '' || body.price == null ? null : num(body.price, null),
    currency: sanitizeCurrency(body.currency),
    unit: str(body.unit).trim(),
    turnaround: str(body.turnaround).trim(),
    active: body.active === false ? 0 : 1,
    createdAt: now,
    updatedAt: now
  };
  db.prepare(`
    INSERT INTO services (serviceId, name, description, price, currency, unit, turnaround, active, createdAt, updatedAt)
    VALUES (@serviceId, @name, @description, @price, @currency, @unit, @turnaround, @active, @createdAt, @updatedAt)
  `).run(row);
  res.status(201).json({ service: serviceRowToJson(row) });
});

admin.put('/services/:serviceId', (req, res) => {
  const { serviceId } = req.params;
  const existing = db.prepare('SELECT * FROM services WHERE serviceId = ?').get(serviceId);
  if (!existing) return res.status(404).json({ error: 'Service not found.' });

  const body = req.body || {};
  const has = (key) => Object.prototype.hasOwnProperty.call(body, key);
  const merged = {
    serviceId,
    name: has('name') && str(body.name).trim() ? str(body.name).trim() : existing.name,
    description: has('description') ? str(body.description).trim() : existing.description,
    price: has('price') ? (body.price === '' || body.price == null ? null : num(body.price, null)) : existing.price,
    currency: has('currency') ? sanitizeCurrency(body.currency) : existing.currency,
    unit: has('unit') ? str(body.unit).trim() : existing.unit,
    turnaround: has('turnaround') ? str(body.turnaround).trim() : existing.turnaround,
    active: has('active') ? (body.active === false || body.active === 'false' ? 0 : 1) : existing.active,
    updatedAt: new Date().toISOString()
  };

  db.prepare(`
    UPDATE services SET name=@name, description=@description, price=@price, currency=@currency,
      unit=@unit, turnaround=@turnaround, active=@active, updatedAt=@updatedAt
    WHERE serviceId=@serviceId
  `).run(merged);

  const updated = db.prepare('SELECT * FROM services WHERE serviceId = ?').get(serviceId);
  res.json({ service: serviceRowToJson(updated) });
});

admin.delete('/services/:serviceId', (req, res) => {
  const result = db.prepare('DELETE FROM services WHERE serviceId = ?').run(req.params.serviceId);
  if (result.changes === 0) return res.status(404).json({ error: 'Service not found.' });
  res.json({ ok: true });
});

app.use('/api/admin', admin);

// ---------------------------------------------------------------------------
// Static site (public marketing site, staff dashboard, and customer
// tracker are all the same single HTML file — the client-side hash router
// in the page decides which one is shown).
// ---------------------------------------------------------------------------

app.use(express.static(path.join(__dirname, 'public')));
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'goldsimba.html'));
});

// JSON 404 for unknown API routes; everything else falls back to the SPA.
app.use('/api', (req, res) => {
  res.status(404).json({ error: 'Not found.' });
});
app.use((req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'goldsimba.html'));
});

// Centralized error handler (e.g. malformed JSON bodies).
app.use((err, req, res, next) => {
  console.error(err);
  res.status(err.status || 500).json({ error: err.message || 'Server error.' });
});

app.listen(PORT, () => {
  console.log('Gold SIMBA Refinery server listening on http://localhost:' + PORT);
  console.log('  Public site:      http://localhost:' + PORT + '/');
  console.log('  Staff dashboard:  http://localhost:' + PORT + '/#admin');
  console.log('  Booking tracker:  http://localhost:' + PORT + '/#track');
});
