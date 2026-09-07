'use strict';

const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const { DatabaseSync } = require('node:sqlite');

const DATA_DIR = path.join(__dirname, 'data');
if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });

const db = new DatabaseSync(path.join(DATA_DIR, 'goldsimba.sqlite'));

// Migrate a bookings table created before the test-report columns existed
// (safe to run every startup — ALTER TABLE ADD COLUMN fails harmlessly if
// the column is already there).
const REPORT_COLUMNS = [
  ['labNumber', 'TEXT'],
  ['numberOfBars', 'INTEGER'],
  ['reportTotalWeight', 'REAL'],
  ['reportWeightUnit', 'TEXT'],
  ['sampleDetails', 'TEXT'],
  ['testMethod', 'TEXT'],
  ['goldPercent', 'REAL'],
  ['goldCarats', 'REAL'],
  ['reportDate', 'TEXT'],
  ['analysedBy', 'TEXT'],
  ['checkedBy', 'TEXT']
];
function migrateReportColumns() {
  const tableExists = db.prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name='bookings'").get();
  if (!tableExists) return;
  const existingCols = new Set(db.prepare('PRAGMA table_info(bookings)').all().map((c) => c.name));
  for (const [col, type] of REPORT_COLUMNS) {
    if (!existingCols.has(col)) {
      db.exec(`ALTER TABLE bookings ADD COLUMN ${col} ${type}`);
    }
  }
}
migrateReportColumns();

db.exec(`
  CREATE TABLE IF NOT EXISTS bookings (
    referenceNumber TEXT PRIMARY KEY,
    dateOfBooking TEXT NOT NULL,
    testingDate TEXT,
    testingTime TEXT,
    testingType TEXT,
    bookingMadeBy TEXT,
    sellerName TEXT,
    sellerContact TEXT,
    sellerNRC TEXT,
    sellerCompany TEXT,
    sellerAddress TEXT,
    buyerName TEXT,
    buyerContact TEXT,
    quantity REAL DEFAULT 0,
    quantityUnit TEXT,
    assayingFee REAL DEFAULT 0,
    amountPaid REAL DEFAULT 0,
    balance REAL DEFAULT 0,
    currency TEXT DEFAULT 'ZMW',
    paymentStatus TEXT DEFAULT 'Pending',
    notes TEXT,
    createdAt TEXT NOT NULL,
    paidAt TEXT,
    labNumber TEXT,
    numberOfBars INTEGER,
    reportTotalWeight REAL,
    reportWeightUnit TEXT,
    sampleDetails TEXT,
    testMethod TEXT,
    goldPercent REAL,
    goldCarats REAL,
    reportDate TEXT,
    analysedBy TEXT,
    checkedBy TEXT
  );

  CREATE TABLE IF NOT EXISTS services (
    serviceId TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    price REAL,
    currency TEXT DEFAULT 'ZMW',
    unit TEXT,
    turnaround TEXT,
    active INTEGER DEFAULT 1,
    createdAt TEXT NOT NULL,
    updatedAt TEXT NOT NULL
  );
`);

// Seed the default service catalogue (matching the twelve services listed on
// the public marketing site) the first time the database is created, so the
// booking form's Testing Type selector isn't empty out of the box. Staff can
// edit/delete/deactivate any of these afterward from the admin dashboard.
const DEFAULT_SERVICES = [
  ['Gold Refining', 'Melting and refining gold to a certified fineness.', 'g', '3-5 business days'],
  ['Mineral Assaying & Testing', 'Laboratory assaying of mineral samples for composition and grade.', 'sample', '2-3 business days'],
  ['Gold Purity & Fineness Testing', 'Precise determination of gold purity and fineness.', 'sample', '1-2 business days'],
  ['Precious & Base Metal Analysis', 'Analysis of precious and base metal content in ore or bullion.', 'sample', '2-4 business days'],
  ['Mineral Sample Preparation', 'Preparation of mineral samples ahead of laboratory testing.', 'sample', '1 business day'],
  ['Independent Laboratory Verification', 'Independent third-party verification of laboratory results.', 'report', '3-5 business days'],
  ['Refinery & Laboratory Consultation', 'Technical consultation on refinery and laboratory operations.', 'session', 'By appointment'],
  ['Mineral Valuation Support', 'Support in valuing mineral holdings ahead of a transaction.', 'report', '2-3 business days'],
  ['Quality Control & Compliance Testing', 'Quality control testing to meet regulatory compliance standards.', 'sample', '2-3 business days'],
  ['Buying on Behalf of Client', 'Sourcing and purchasing minerals on behalf of a client.', 'transaction', 'By arrangement'],
  ['Gold Biometric Security System', 'Biometric-secured handling and custody of gold holdings.', 'engagement', 'By arrangement'],
  ['Client Consultation', 'General consultation for clients on our services.', 'session', 'By appointment']
];

const countRow = db.prepare('SELECT COUNT(*) AS n FROM services').get();
if (countRow.n === 0) {
  const insert = db.prepare(`
    INSERT INTO services (serviceId, name, description, price, currency, unit, turnaround, active, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
  `);
  const now = new Date().toISOString();
  for (const [name, description, unit, turnaround] of DEFAULT_SERVICES) {
    insert.run(crypto.randomUUID(), name, description, null, 'ZMW', unit, turnaround, now, now);
  }
}

module.exports = db;
