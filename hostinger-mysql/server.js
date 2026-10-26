/**
 * Gold SIMBA Refinery — booking system backend.
 * ------------------------------------------------------------
 * Plain Node.js + Express + MySQL (via `mysql2`, using its promise API —
 * works against Hostinger's hPanel-provisioned MySQL, or any other
 * MySQL/MariaDB host; no proprietary driver). Serves the site itself
 * (public/goldsimba.html) AND the JSON API from ONE process on ONE port,
 * so every fetch() call in goldsimba.html is a same-origin relative path
 * like `/api/services` — no config file to edit, no CORS, no third-party
 * console to set up beyond providing the database credentials.
 *
 * This reproduces, on a different storage layer, the exact business rules
 * that used to live in Code.gs (Google Apps Script), then in the
 * Firestore version of goldsimba.html, then in the better-sqlite3 version,
 * then the Postgres/Neon version, of this same server:
 *   - sequential GSR-000001 booking references / SVC-001 service ids
 *   - balance = assayingFee - amountPaid, recomputed server-side always
 *   - the public "lookup" action strips notes/sellerNRC/sellerAddress
 *   - the public "request" action can only ever create a Pending,
 *     zero-fee booking — it can never set its own price
 *   - paidAt is stamped the moment paymentStatus becomes 'Paid', and
 *     cleared whenever it's set to anything else
 *
 * ==== HOW TO CHANGE THE ADMIN PASSWORD ====
 * Set the ADMIN_USERNAME / ADMIN_PASSWORD environment variables on
 * whatever host you deploy this to, then restart the process. If they
 * are not set, this file falls back to the client's requested defaults
 * (username "admin", password "ADMINGSR2026") — intentionally simple
 * per an explicit request, but change it before this is handling real
 * customer money on the public internet. See README.md for exact
 * per-host steps (Hostinger hPanel environment variables, etc).
 */

'use strict';

const path = require('path');
const crypto = require('crypto');
const express = require('express');
const mysql = require('mysql2/promise');

// ---------------------------------------------------------------
// Config
// ---------------------------------------------------------------
const PORT = process.env.PORT || 3000;
const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'ADMINGSR2026';
const TOKEN_TTL_MS = 12 * 60 * 60 * 1000; // 12 hours

// Hostinger's hPanel MySQL setup hands you discrete credentials (host,
// database name, username, password) rather than a single connection-
// string URL the way Neon did for Postgres — so read those separately.
// Host/port get sane shared-hosting defaults (a MySQL database and a
// Node.js app on the same Hostinger account normally talk to each other
// over "localhost" on the standard MySQL port); user/password/database
// name have no safe default and must be set explicitly.
const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_USER = process.env.DB_USER;
const DB_PASSWORD = process.env.DB_PASSWORD;
const DB_NAME = process.env.DB_NAME;

const REQUIRED_DB_VARS = { DB_USER, DB_PASSWORD, DB_NAME };
const missingDbVars = Object.keys(REQUIRED_DB_VARS).filter((k) => !REQUIRED_DB_VARS[k]);
if (missingDbVars.length > 0) {
  console.error(
    '[goldsimba] Missing required database environment variable(s): ' +
    missingDbVars.join(', ') + '.\n' +
    'Set DB_HOST (default "localhost"), DB_PORT (default 3306), DB_USER, ' +
    'DB_PASSWORD, and DB_NAME to point at your MySQL database — on ' +
    'Hostinger these come from hPanel -> Databases -> MySQL Databases.\n' +
    'See README.md for exact steps.'
  );
  process.exit(1);
}

let SESSION_SECRET = process.env.SESSION_SECRET;
if (!SESSION_SECRET) {
  SESSION_SECRET = crypto.randomBytes(32).toString('hex');
  console.log(
    '[goldsimba] No SESSION_SECRET set — generated a random one for this ' +
    'process. Staff will be signed out any time the server restarts. Set ' +
    'SESSION_SECRET to a fixed random string in your environment to keep ' +
    'sessions valid across restarts.'
  );
}

// ---------------------------------------------------------------
// Database setup
// ---------------------------------------------------------------
// Hostinger's shared/Business/Cloud MySQL and the Node.js app it talks to
// live on the same account/network, so — unlike Neon — no SSL/TLS setup
// is needed here.
const pool = mysql.createPool({
  host: DB_HOST,
  port: Number(DB_PORT),
  user: DB_USER,
  password: DB_PASSWORD,
  database: DB_NAME,
  waitForConnections: true,
  connectionLimit: 10
});

pool.on('error', (err) => {
  // Fired for errors on idle/pooled connections (e.g. the connection was
  // dropped by the host). Log it — don't let it crash the whole process.
  console.error('[goldsimba] Unexpected MySQL pool error:', err);
});

// Wrap an async Express handler so a rejected promise becomes a normal
// Express error (-> the JSON 500 handler at the bottom) instead of an
// unhandled rejection that could crash the process or hang the request.
function ah(fn) {
  return function (req, res, next) {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
}

const BOOKING_COLUMNS = [
  'referenceNumber', 'dateOfBooking', 'testingDate', 'testingTime', 'testingType',
  'quantity', 'quantityUnit', 'assayingFee', 'amountPaid', 'balance', 'currency',
  'paymentStatus', 'bookingMadeBy', 'sellerName', 'sellerContact', 'sellerNRC',
  'sellerCompany', 'sellerAddress', 'buyerName', 'buyerContact', 'notes',
  'documentReceivedDate', 'createdAt', 'updatedAt', 'paidAt'
];
const SERVICE_COLUMNS = [
  'serviceId', 'name', 'description', 'price', 'currency', 'unit', 'turnaround',
  'active', 'createdAt', 'updatedAt'
];
// One assay report per booking (same referenceNumber, 1:1) — the lab
// results (weights, purity, per-metal composition, method, who tested it,
// and when) that used to have nowhere to live. `status` is 'draft' while
// staff are still filling it in, and only flips to 'issued' when they're
// ready to hand it to the client — the public lookup route below only ever
// returns 'issued' reports, so a customer never sees a half-finished one.
const ASSAY_COLUMNS = [
  'referenceNumber', 'reportNumber', 'sampleDescription', 'grossWeight', 'netWeight',
  'weightUnit', 'purity', 'resultGrade', 'goldPct', 'silverPct', 'copperPct', 'platinumPct',
  'testingMethod', 'equipment', 'testedBy', 'dateTested', 'remarks', 'status',
  'createdAt', 'updatedAt'
];

// Backtick-quote identifiers. MySQL doesn't fold unquoted identifiers to
// lowercase the way Postgres does, so this isn't load-bearing for the
// camelCase column names here (verified empirically to round-trip
// correctly either way) — but it's cheap insurance against a column name
// ever colliding with a MySQL reserved word.
function q(col) { return '`' + col + '`'; }

async function insertRow(table, columns, record) {
  const cols = columns.map(q).join(', ');
  const placeholders = columns.map(() => '?').join(', ');
  const values = columns.map((c) => record[c]);
  await pool.query(`INSERT INTO ${table} (${cols}) VALUES (${placeholders})`, values);
}

async function updateRow(table, columns, keyColumn, record) {
  const updateCols = columns.filter((c) => c !== keyColumn);
  const setClause = updateCols.map((c) => `${q(c)} = ?`).join(', ');
  const values = updateCols.map((c) => record[c]);
  values.push(record[keyColumn]);
  await pool.query(
    `UPDATE ${table} SET ${setClause} WHERE ${q(keyColumn)} = ?`,
    values
  );
}

async function ensureColumn(table, column, ddlType) {
  const [rows] = await pool.query(
    `SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?`,
    [table, column]
  );
  if (rows[0].n > 0) return;
  await pool.query(`ALTER TABLE ${table} ADD COLUMN ${q(column)} ${ddlType}`);
  console.log(`[goldsimba] Added missing column ${table}.${column} to bring an older database up to date.`);
}

async function initSchema() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS counters (
      \`name\`  VARCHAR(64) PRIMARY KEY,
      \`count\` INT NOT NULL
    );
  `);

  // referenceNumber/serviceId are indexed (PRIMARY KEY) TEXT-like columns,
  // so MySQL requires a length — VARCHAR(64) is comfortably larger than
  // "GSR-000001"/"SVC-001" ever need to be.
  await pool.query(`
    CREATE TABLE IF NOT EXISTS bookings (
      \`referenceNumber\` VARCHAR(64) PRIMARY KEY,
      \`dateOfBooking\`   TEXT,
      \`testingDate\`     TEXT,
      \`testingTime\`     TEXT,
      \`testingType\`     TEXT,
      \`quantity\`        TEXT,
      \`quantityUnit\`    TEXT,
      \`assayingFee\`     DOUBLE,
      \`amountPaid\`      DOUBLE,
      \`balance\`         DOUBLE,
      \`currency\`        TEXT,
      \`paymentStatus\`   TEXT,
      \`bookingMadeBy\`   TEXT,
      \`sellerName\`      TEXT,
      \`sellerContact\`   TEXT,
      \`sellerNRC\`       TEXT,
      \`sellerCompany\`   TEXT,
      \`sellerAddress\`   TEXT,
      \`buyerName\`       TEXT,
      \`buyerContact\`    TEXT,
      \`notes\`           TEXT,
      \`documentReceivedDate\` TEXT,
      \`createdAt\`       VARCHAR(40),
      \`updatedAt\`       VARCHAR(40),
      \`paidAt\`          VARCHAR(40)
    );
  `);

  // documentReceivedDate was added after this table may already have been
  // created on an existing deployment — CREATE TABLE IF NOT EXISTS above is
  // a no-op there, so bring an older table up to date explicitly. Checked
  // against INFORMATION_SCHEMA rather than a bare
  // "ALTER TABLE ... ADD COLUMN IF NOT EXISTS" because that clause isn't
  // supported on MySQL versions before 8.0.29 (only MariaDB has long
  // supported it) and Hostinger-style hosts can still be on an older MySQL.
  await ensureColumn('bookings', 'documentReceivedDate', 'TEXT');

  await pool.query(`
    CREATE TABLE IF NOT EXISTS services (
      \`serviceId\`   VARCHAR(64) PRIMARY KEY,
      \`name\`        TEXT,
      \`description\` TEXT,
      \`price\`       TEXT,
      \`currency\`    TEXT,
      \`unit\`        TEXT,
      \`turnaround\`  TEXT,
      \`active\`      BOOLEAN,
      \`createdAt\`   VARCHAR(40),
      \`updatedAt\`   VARCHAR(40)
    );
  `);

  // One row per booking (same referenceNumber, used as the primary key here
  // too — a booking can have at most one assay report). Kept as its own
  // table rather than more columns on `bookings` because it's a genuinely
  // separate concern (lab results vs. booking/payment logistics) that's
  // usually filled in well after the booking itself is created.
  await pool.query(`
    CREATE TABLE IF NOT EXISTS assay_reports (
      \`referenceNumber\`    VARCHAR(64) PRIMARY KEY,
      \`reportNumber\`       VARCHAR(64),
      \`sampleDescription\`  TEXT,
      \`grossWeight\`        TEXT,
      \`netWeight\`          TEXT,
      \`weightUnit\`         TEXT,
      \`purity\`             TEXT,
      \`resultGrade\`        TEXT,
      \`goldPct\`            TEXT,
      \`silverPct\`          TEXT,
      \`copperPct\`          TEXT,
      \`platinumPct\`        TEXT,
      \`testingMethod\`      TEXT,
      \`equipment\`          TEXT,
      \`testedBy\`           TEXT,
      \`dateTested\`         TEXT,
      \`remarks\`            TEXT,
      \`status\`             VARCHAR(20),
      \`createdAt\`          VARCHAR(40),
      \`updatedAt\`          VARCHAR(40)
    );
  `);

  // The atomic-counter idiom below (see allocateCount) only ever takes its
  // "ON DUPLICATE KEY UPDATE ... LAST_INSERT_ID(...)" branch — and so only
  // ever reliably sets LAST_INSERT_ID() — once a row for that counter name
  // already exists. Pre-seed all known counters at 0 so the very first
  // real allocation (count 1) already goes through that branch instead of
  // the plain INSERT branch, which does NOT set LAST_INSERT_ID() (there's
  // no AUTO_INCREMENT column here). Safe to run every startup: a counter
  // that already exists (real data present) is left untouched.
  await pool.query(
    `INSERT IGNORE INTO counters (\`name\`, \`count\`) VALUES (?, 0), (?, 0), (?, 0)`,
    ['booking', 'service', 'assayReport']
  );
}

// Seed the 7 default services on first run only (fresh services table).
const DEFAULT_SERVICES = [
  { name: 'Gold Assay', description: 'Determines the purity and fineness of a gold sample.' },
  { name: 'Silver Assay', description: 'Determines the purity and fineness of a silver sample.' },
  { name: 'Copper Assay', description: 'Determines the purity and composition of a copper sample.' },
  { name: 'Mineral Composition Analysis', description: 'Full breakdown of the mineral composition of a sample.' },
  { name: 'Gold Concentration Testing', description: 'Measures the concentration of gold within a sample or ore.' },
  { name: 'Fire Assay', description: 'Classical fire-assay method for precious metal content.' },
  { name: 'Other Mineral Testing', description: 'Testing for minerals not covered by the services above.' }
];

async function seedServicesIfEmpty() {
  const [rows] = await pool.query('SELECT COUNT(*) AS n FROM services');
  if (rows[0].n > 0) return;
  const now = new Date().toISOString();
  for (let i = 0; i < DEFAULT_SERVICES.length; i++) {
    const svc = DEFAULT_SERVICES[i];
    await insertRow('services', SERVICE_COLUMNS, {
      serviceId: 'SVC-' + String(i + 1).padStart(3, '0'),
      name: svc.name,
      description: svc.description,
      price: '',
      currency: 'ZMW',
      unit: '',
      turnaround: '',
      active: true,
      createdAt: now,
      updatedAt: now
    });
  }
  console.log('[goldsimba] Seeded ' + DEFAULT_SERVICES.length + ' default services into a fresh database.');
}

// Belt-and-suspenders: if the counters table doesn't yet know about ids
// that already exist in the data (e.g. data imported by hand rather than
// through this app), bring the relevant counter up to at least the
// highest existing number so newly-generated ids can never collide with
// existing ones. On a normal fresh install this is a no-op (0 bookings,
// and the service counter is set explicitly by the seed step above).
async function ensureCounterAtLeastExisting(counterName, table, idColumn, prefix) {
  const [rows] = await pool.query(
    `SELECT ${q(idColumn)} AS id FROM ${table} WHERE ${q(idColumn)} LIKE ?`,
    [prefix + '%']
  );
  let maxNum = 0;
  const re = new RegExp('^' + prefix + '(\\d+)$');
  for (const r of rows) {
    const m = re.exec(r.id || '');
    if (m) {
      const n = parseInt(m[1], 10);
      if (n > maxNum) maxNum = n;
    }
  }
  if (maxNum > 0) {
    await pool.query(
      `INSERT INTO counters (\`name\`, \`count\`) VALUES (?, ?)
       ON DUPLICATE KEY UPDATE \`count\` = GREATEST(\`count\`, VALUES(\`count\`))`,
      [counterName, maxNum]
    );
  }
}

// ---------------------------------------------------------------
// Sequential id generation — atomic under MySQL, race-free even under
// concurrent requests.
//
// Unlike better-sqlite3 (synchronous, single-threaded — "read max ref,
// compute next, insert row" could never be interleaved with another
// request), and unlike Postgres (which supports UPSERT ... RETURNING in
// one round trip), plain MySQL/MariaDB has neither a synchronous engine
// to lean on nor a reliable RETURNING clause. The standard MySQL-safe
// idiom instead is:
//
//   INSERT INTO counters (name, count) VALUES (?, 1)
//     ON DUPLICATE KEY UPDATE count = LAST_INSERT_ID(count + 1);
//   SELECT LAST_INSERT_ID() AS count;
//
// LAST_INSERT_ID() is per-CONNECTION session state (not per-pool, not
// per-table) — so both statements MUST run on the exact same physical
// connection, back to back, or the pool could legitimately hand the
// SELECT to a different connection and return a stale/wrong value (or,
// under real concurrency, the same value to two different callers —
// silently reintroducing the exact race this pattern exists to avoid).
// `pool.query()` does not guarantee that; `pool.getConnection()` does.
// ---------------------------------------------------------------
async function allocateCount(counterName) {
  const conn = await pool.getConnection();
  try {
    await conn.query(
      `INSERT INTO counters (\`name\`, \`count\`) VALUES (?, 1)
       ON DUPLICATE KEY UPDATE \`count\` = LAST_INSERT_ID(\`count\` + 1)`,
      [counterName]
    );
    const [rows] = await conn.query('SELECT LAST_INSERT_ID() AS count');
    return rows[0].count;
  } finally {
    conn.release();
  }
}

async function nextSequentialRef() {
  const n = await allocateCount('booking');
  return 'GSR-' + String(n).padStart(6, '0');
}

async function nextSequentialServiceId() {
  const n = await allocateCount('service');
  return 'SVC-' + String(n).padStart(3, '0');
}

async function nextSequentialReportNumber() {
  const n = await allocateCount('assayReport');
  return 'AR-' + String(n).padStart(6, '0');
}

function computeBalance(assayingFee, amountPaid) {
  const due = Number(assayingFee) || 0;
  const paid = Number(amountPaid) || 0;
  return Math.round((due - paid) * 100) / 100;
}

// ---------------------------------------------------------------
// Auth — HMAC-signed bearer tokens, no server-side session storage.
// Token format: base64url(payload).base64url(signature)
// payload = "<username>:<expiryEpochMs>"
// signature = HMAC-SHA256(payload, SESSION_SECRET)
// (Unchanged by the storage migration — auth never touches the database.)
// ---------------------------------------------------------------
function b64url(buf) {
  return Buffer.from(buf).toString('base64url');
}

function sign(payload) {
  return crypto.createHmac('sha256', SESSION_SECRET).update(payload).digest();
}

function issueToken(username) {
  const expiry = Date.now() + TOKEN_TTL_MS;
  const payload = username + ':' + expiry;
  const sig = sign(payload);
  return b64url(payload) + '.' + b64url(sig);
}

function timingSafeEqualStr(a, b) {
  const bufA = Buffer.from(String(a));
  const bufB = Buffer.from(String(b));
  if (bufA.length !== bufB.length) {
    // Still do a comparison of equal-length buffers to avoid a fast
    // short-circuit leaking length info via timing on top of the length
    // itself (length is not really secret here, but keep it uniform).
    crypto.timingSafeEqual(bufA, bufA);
    return false;
  }
  return crypto.timingSafeEqual(bufA, bufB);
}

function verifyToken(token) {
  if (!token || typeof token !== 'string') return null;
  const parts = token.split('.');
  if (parts.length !== 2) return null;
  let payload, sig;
  try {
    payload = Buffer.from(parts[0], 'base64url').toString('utf8');
    sig = Buffer.from(parts[1], 'base64url');
  } catch (e) {
    return null;
  }
  const expectedSig = sign(payload);
  if (sig.length !== expectedSig.length || !crypto.timingSafeEqual(sig, expectedSig)) return null;
  const idx = payload.lastIndexOf(':');
  if (idx === -1) return null;
  const username = payload.slice(0, idx);
  const expiry = parseInt(payload.slice(idx + 1), 10);
  if (!Number.isFinite(expiry) || Date.now() > expiry) return null;
  return { username };
}

function requireAuth(req, res, next) {
  const header = req.headers['authorization'] || '';
  const match = /^Bearer (.+)$/.exec(header);
  const token = match ? match[1] : null;
  const session = verifyToken(token);
  if (!session) {
    return res.status(401).json({ error: 'Not signed in, or your session has expired. Please sign in again.' });
  }
  req.staffUser = session.username;
  next();
}

// ---------------------------------------------------------------
// Row <-> object helpers
// ---------------------------------------------------------------
const PUBLIC_LOOKUP_STRIP_FIELDS = ['notes', 'sellerNRC', 'sellerAddress', 'documentReceivedDate'];

function stripConfidential(booking) {
  const out = Object.assign({}, booking);
  PUBLIC_LOOKUP_STRIP_FIELDS.forEach((f) => { delete out[f]; });
  return out;
}

// MySQL has no native boolean type — BOOLEAN is an alias for TINYINT(1),
// and mysql2 returns that as the JS number 0/1, not true/false. Convert
// explicitly (same pattern the original SQLite version used).
function serviceRowToObj(row) {
  return Object.assign({}, row, { active: !!row.active });
}

// ---------------------------------------------------------------
// Express app
// ---------------------------------------------------------------
const app = express();
app.use(express.json());

const api = express.Router();

// ---- PUBLIC: active services list ----
api.get('/services', ah(async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM services WHERE `active` = true ORDER BY LOWER(`name`)');
  res.json({ ok: true, services: rows.map(serviceRowToObj) });
}));

// ---- PUBLIC: reference-number lookup ----
// Also returns the assay report for this booking, but ONLY once staff have
// marked it 'issued' — a report still in 'draft' never reaches this route,
// so a customer can never see half-finished results.
api.get('/lookup', ah(async (req, res) => {
  const ref = String(req.query.ref || '').trim().toUpperCase();
  if (!ref) return res.status(400).json({ ok: false, error: 'Enter a reference number.' });
  const [rows] = await pool.query('SELECT * FROM bookings WHERE UPPER(`referenceNumber`) = ?', [ref]);
  const row = rows[0];
  if (!row) return res.status(404).json({ ok: false, error: 'No booking found for that reference number.' });
  const [assayRows] = await pool.query(
    'SELECT * FROM assay_reports WHERE UPPER(`referenceNumber`) = ? AND `status` = ?',
    [ref, 'issued']
  );
  res.json({ ok: true, booking: stripConfidential(row), assayReport: assayRows[0] || null });
}));

// ---- PUBLIC: website "Book Now" self-service request ----
// Only customer-facing fields are ever accepted. Fee, amount paid, and
// payment status are always forced to their safe defaults here — a public
// visitor can never set their own price or mark themselves as Paid.
api.post('/request', ah(async (req, res) => {
  const payload = req.body || {};
  const now = new Date().toISOString();
  const ref = await nextSequentialRef();
  const record = {
    referenceNumber: ref,
    dateOfBooking: now.slice(0, 10),
    testingDate: payload.testingDate || '',
    testingTime: payload.testingTime || '',
    testingType: payload.testingType || '',
    quantity: payload.quantity || '',
    quantityUnit: payload.quantityUnit || 'g',
    assayingFee: 0,
    amountPaid: 0,
    balance: 0,
    currency: 'ZMW',
    paymentStatus: 'Pending',
    bookingMadeBy: 'Website',
    sellerName: payload.sellerName || '',
    sellerContact: payload.sellerContact || '',
    sellerNRC: payload.sellerNRC || '',
    sellerCompany: payload.sellerCompany || '',
    sellerAddress: payload.sellerAddress || '',
    buyerName: payload.buyerName || '',
    buyerContact: payload.buyerContact || '',
    notes: payload.notes || '',
    documentReceivedDate: '',
    createdAt: now,
    updatedAt: now,
    paidAt: ''
  };
  await insertRow('bookings', BOOKING_COLUMNS, record);
  res.json({ ok: true, referenceNumber: ref, booking: stripConfidential(record) });
}));

// ---- Login ----
api.post('/login', (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) {
    return res.status(400).json({ error: 'Enter your staff username and password.' });
  }
  const userOk = timingSafeEqualStr(username, ADMIN_USERNAME);
  const passOk = timingSafeEqualStr(password, ADMIN_PASSWORD);
  if (!userOk || !passOk) {
    return res.status(401).json({ error: 'Incorrect username or password.' });
  }
  const token = issueToken(ADMIN_USERNAME);
  res.json({ ok: true, token, expiresInMs: TOKEN_TTL_MS });
});

// ================= Admin: bookings =================
const admin = express.Router();
admin.use(requireAuth);

admin.get('/bookings', ah(async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM bookings ORDER BY `createdAt` DESC');
  res.json({ ok: true, bookings: rows });
}));

admin.post('/bookings', ah(async (req, res) => {
  const payload = req.body || {};
  const now = new Date().toISOString();
  const ref = await nextSequentialRef();
  const assayingFee = payload.assayingFee || 0;
  const amountPaid = payload.amountPaid || 0;
  const paymentStatus = payload.paymentStatus || 'Pending';
  const record = {
    referenceNumber: ref,
    dateOfBooking: payload.dateOfBooking || '',
    testingDate: payload.testingDate || '',
    testingTime: payload.testingTime || '',
    testingType: payload.testingType || '',
    quantity: payload.quantity || '',
    quantityUnit: payload.quantityUnit || 'g',
    assayingFee: assayingFee,
    amountPaid: amountPaid,
    balance: computeBalance(assayingFee, amountPaid),
    currency: payload.currency || 'ZMW',
    paymentStatus: paymentStatus,
    bookingMadeBy: payload.bookingMadeBy || '',
    sellerName: payload.sellerName || '',
    sellerContact: payload.sellerContact || '',
    sellerNRC: payload.sellerNRC || '',
    sellerCompany: payload.sellerCompany || '',
    sellerAddress: payload.sellerAddress || '',
    buyerName: payload.buyerName || '',
    buyerContact: payload.buyerContact || '',
    notes: payload.notes || '',
    documentReceivedDate: payload.documentReceivedDate || '',
    createdAt: now,
    updatedAt: now,
    paidAt: paymentStatus === 'Paid' ? now : ''
  };
  await insertRow('bookings', BOOKING_COLUMNS, record);
  res.json({ ok: true, booking: record });
}));

admin.put('/bookings/:ref', ah(async (req, res) => {
  const ref = req.params.ref;
  const [rows] = await pool.query('SELECT * FROM bookings WHERE `referenceNumber` = ?', [ref]);
  const existing = rows[0];
  if (!existing) return res.status(404).json({ ok: false, error: 'Booking not found.' });

  const patch = req.body || {};
  const now = new Date().toISOString();
  const merged = Object.assign({}, existing, patch, { referenceNumber: ref, updatedAt: now });
  merged.balance = computeBalance(merged.assayingFee, merged.amountPaid);
  if (patch.paymentStatus === 'Paid' && existing.paymentStatus !== 'Paid') {
    merged.paidAt = now;
  } else if (patch.paymentStatus && patch.paymentStatus !== 'Paid') {
    merged.paidAt = '';
  }

  await updateRow('bookings', BOOKING_COLUMNS, 'referenceNumber', merged);
  res.json({ ok: true, booking: merged });
}));

admin.delete('/bookings/:ref', ah(async (req, res) => {
  const ref = req.params.ref;
  const [result] = await pool.query('DELETE FROM bookings WHERE `referenceNumber` = ?', [ref]);
  if (result.affectedRows === 0) return res.status(404).json({ ok: false, error: 'Booking not found.' });
  // A booking's assay report (if any) has no reason to outlive the booking.
  await pool.query('DELETE FROM assay_reports WHERE `referenceNumber` = ?', [ref]);
  res.json({ ok: true });
}));

// ================= Admin: assay reports =================
// One report per booking. GET always returns the full row (including a
// still-draft one) so staff can keep editing it; PUT creates it on first
// save (allocating its AR-000001-style report number then) or updates the
// existing one otherwise. Nothing here is reachable without a valid staff
// bearer token (`admin.use(requireAuth)` above applies to this whole router).
admin.get('/assay/:ref', ah(async (req, res) => {
  const ref = req.params.ref;
  const [rows] = await pool.query('SELECT * FROM assay_reports WHERE `referenceNumber` = ?', [ref]);
  res.json({ ok: true, assayReport: rows[0] || null });
}));

admin.put('/assay/:ref', ah(async (req, res) => {
  const ref = req.params.ref;
  const [bookingRows] = await pool.query('SELECT `referenceNumber` FROM bookings WHERE `referenceNumber` = ?', [ref]);
  if (!bookingRows[0]) return res.status(404).json({ ok: false, error: 'Booking not found.' });

  const patch = req.body || {};
  const now = new Date().toISOString();
  const status = patch.status === 'issued' ? 'issued' : 'draft';

  const [existingRows] = await pool.query('SELECT * FROM assay_reports WHERE `referenceNumber` = ?', [ref]);
  const existing = existingRows[0];

  if (!existing) {
    const reportNumber = await nextSequentialReportNumber();
    const record = {
      referenceNumber: ref,
      reportNumber: reportNumber,
      sampleDescription: patch.sampleDescription || '',
      grossWeight: patch.grossWeight || '',
      netWeight: patch.netWeight || '',
      weightUnit: patch.weightUnit || 'g',
      purity: patch.purity || '',
      resultGrade: patch.resultGrade || '',
      goldPct: patch.goldPct || '',
      silverPct: patch.silverPct || '',
      copperPct: patch.copperPct || '',
      platinumPct: patch.platinumPct || '',
      testingMethod: patch.testingMethod || '',
      equipment: patch.equipment || '',
      testedBy: patch.testedBy || '',
      dateTested: patch.dateTested || '',
      remarks: patch.remarks || '',
      status: status,
      createdAt: now,
      updatedAt: now
    };
    await insertRow('assay_reports', ASSAY_COLUMNS, record);
    return res.json({ ok: true, assayReport: record });
  }

  const merged = Object.assign({}, existing, patch, {
    referenceNumber: ref,
    reportNumber: existing.reportNumber,
    status: status,
    updatedAt: now
  });
  await updateRow('assay_reports', ASSAY_COLUMNS, 'referenceNumber', merged);
  res.json({ ok: true, assayReport: merged });
}));

// ================= Admin: services =================
admin.get('/services', ah(async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM services ORDER BY LOWER(`name`)');
  res.json({ ok: true, services: rows.map(serviceRowToObj) });
}));

admin.post('/services', ah(async (req, res) => {
  const payload = req.body || {};
  if (!payload.name) return res.status(400).json({ ok: false, error: 'Service name is required.' });
  const now = new Date().toISOString();
  const id = await nextSequentialServiceId();
  const record = {
    serviceId: id,
    name: payload.name || '',
    description: payload.description || '',
    price: payload.price || '',
    currency: payload.currency || 'ZMW',
    unit: payload.unit || '',
    turnaround: payload.turnaround || '',
    active: payload.active !== false,
    createdAt: now,
    updatedAt: now
  };
  await insertRow('services', SERVICE_COLUMNS, record);
  res.json({ ok: true, service: serviceRowToObj(record) });
}));

admin.put('/services/:id', ah(async (req, res) => {
  const id = req.params.id;
  const [rows] = await pool.query('SELECT * FROM services WHERE `serviceId` = ?', [id]);
  const existing = rows[0];
  if (!existing) return res.status(404).json({ ok: false, error: 'Service not found.' });
  const patch = req.body || {};
  const now = new Date().toISOString();
  const merged = Object.assign({}, existing, patch, { serviceId: id, updatedAt: now });
  merged.active = patch.active !== undefined ? !!patch.active : !!existing.active;
  await updateRow('services', SERVICE_COLUMNS, 'serviceId', merged);
  res.json({ ok: true, service: serviceRowToObj(merged) });
}));

admin.delete('/services/:id', ah(async (req, res) => {
  const id = req.params.id;
  const [result] = await pool.query('DELETE FROM services WHERE `serviceId` = ?', [id]);
  if (result.affectedRows === 0) return res.status(404).json({ ok: false, error: 'Service not found.' });
  res.json({ ok: true });
}));

api.use('/admin', admin);
app.use('/api', api);

// ---------------------------------------------------------------
// Static site + fallback error handling
// ---------------------------------------------------------------
app.use(express.static(path.join(__dirname, 'public')));

// The whole site (public site, admin dashboard, tracker) is one file,
// hash-routed client-side (#admin, #track) — so every non-API GET just
// serves that same file and the in-page router takes it from there.
app.get('*', (req, res, next) => {
  if (req.path.startsWith('/api/')) return next();
  res.sendFile(path.join(__dirname, 'public', 'goldsimba.html'));
});

// JSON 404 for unknown API routes (keep static/file 404s as-is).
app.use('/api', (req, res) => {
  res.status(404).json({ ok: false, error: 'Unknown API route.' });
});

// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ ok: false, error: 'Server error.' });
});

// ---------------------------------------------------------------
// Startup: verify the database, create tables if needed, seed default
// data if needed, THEN start accepting requests.
// ---------------------------------------------------------------
async function start() {
  await initSchema();
  await seedServicesIfEmpty();
  await ensureCounterAtLeastExisting('booking', 'bookings', 'referenceNumber', 'GSR-');
  await ensureCounterAtLeastExisting('service', 'services', 'serviceId', 'SVC-');
  await ensureCounterAtLeastExisting('assayReport', 'assay_reports', 'reportNumber', 'AR-');

  app.listen(PORT, () => {
    console.log('Gold SIMBA Refinery server listening on port ' + PORT);
    console.log('  Public site:      http://localhost:' + PORT + '/');
    console.log('  Staff dashboard:  http://localhost:' + PORT + '/#admin');
    console.log('  Booking tracker:  http://localhost:' + PORT + '/#track');
    console.log('  Admin username:   ' + ADMIN_USERNAME + (process.env.ADMIN_USERNAME ? '' : ' (default — set ADMIN_USERNAME to change)'));
    console.log('  Admin password:   ' + (process.env.ADMIN_PASSWORD ? '(custom, set via env)' : 'ADMINGSR2026 (default — set ADMIN_PASSWORD to change)'));
  });
}

start().catch((err) => {
  console.error('[goldsimba] Fatal error during startup (could not reach/initialize the database):', err);
  process.exit(1);
});
