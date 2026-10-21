const path = require('node:path');
const crypto = require('node:crypto');

const root = path.resolve(__dirname, '..');
const env = process.env;
const isProd = env.NODE_ENV === 'production';

if (isProd && !env.SESSION_SECRET) {
  throw new Error('SESSION_SECRET must be set in production.');
}

module.exports = {
  root,
  isProd,
  port: Number(env.PORT) || 3000,
  baseUrl: (env.BASE_URL || `http://localhost:${Number(env.PORT) || 3000}`).replace(/\/$/, ''),
  dbPath: env.DATABASE_PATH || path.join(root, 'data', 'brandbay.db'),
  uploadDir: env.UPLOAD_DIR || path.join(root, 'data', 'uploads'),
  privateDir: env.PRIVATE_UPLOAD_DIR || path.join(root, 'data', 'private'),
  sessionSecret: env.SESSION_SECRET || crypto.randomBytes(32).toString('hex'),
  trustProxy: env.TRUST_PROXY === '1' || isProd,
  maxUploadMb: Number(env.MAX_UPLOAD_MB) || 10,
  currency: env.CURRENCY || 'K',
  depositPercent: Number(env.DEPOSIT_PERCENT) || 70,
  mail: {
    host: env.SMTP_HOST,
    port: Number(env.SMTP_PORT) || 587,
    secure: env.SMTP_SECURE === '1',
    user: env.SMTP_USER,
    pass: env.SMTP_PASS,
    from: env.MAIL_FROM || 'BrandBay Innovation Studios <info@brandbaystudio.online>',
    notify: env.NOTIFY_EMAIL || 'info@brandbaystudio.online',
  },
};
