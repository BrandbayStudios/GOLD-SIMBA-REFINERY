'use strict';

// Minimal stateless bearer-token auth (HMAC-signed, no external JWT
// dependency). A token is base64url(payload json) + '.' + base64url(HMAC).
// This is intentionally small: one signing secret, one expiry, one claim
// (the staff username) — enough to protect the /api/admin/* routes without
// pulling in a session store.

const crypto = require('crypto');

const SECRET = process.env.TOKEN_SECRET || 'dev-only-insecure-secret-change-me';
if (!process.env.TOKEN_SECRET) {
  console.warn('[auth] TOKEN_SECRET is not set — using an insecure default. Set TOKEN_SECRET in your environment for production.');
}

const TOKEN_TTL_MS = 12 * 60 * 60 * 1000; // ~12 hours

function b64url(buf) {
  return Buffer.from(buf).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
function b64urlDecode(str) {
  str = str.replace(/-/g, '+').replace(/_/g, '/');
  while (str.length % 4) str += '=';
  return Buffer.from(str, 'base64');
}
function sign(data) {
  return b64url(crypto.createHmac('sha256', SECRET).update(data).digest());
}

function issueToken(username) {
  const payload = JSON.stringify({ u: username, exp: Date.now() + TOKEN_TTL_MS });
  const encodedPayload = b64url(payload);
  return encodedPayload + '.' + sign(encodedPayload);
}

function verifyToken(token) {
  if (!token || typeof token !== 'string' || token.indexOf('.') === -1) return null;
  const [encodedPayload, sig] = token.split('.');
  if (!encodedPayload || !sig) return null;
  const expected = sign(encodedPayload);
  const a = Buffer.from(sig);
  const b = Buffer.from(expected);
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return null;
  let payload;
  try {
    payload = JSON.parse(b64urlDecode(encodedPayload).toString('utf8'));
  } catch (e) {
    return null;
  }
  if (!payload || typeof payload.exp !== 'number' || Date.now() > payload.exp) return null;
  return payload;
}

function requireAuth(req, res, next) {
  const header = req.headers.authorization || '';
  const match = /^Bearer\s+(.+)$/i.exec(header);
  const token = match ? match[1] : null;
  const payload = verifyToken(token);
  if (!payload) {
    return res.status(401).json({ error: 'Sign in required.' });
  }
  req.staffUser = payload.u;
  next();
}

module.exports = { issueToken, verifyToken, requireAuth };
