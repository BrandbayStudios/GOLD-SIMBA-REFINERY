const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const multer = require('multer');
const config = require('./config');
const { get } = require('./db');

function flash(req, type, message) {
  req.session.flash = req.session.flash || [];
  req.session.flash.push({ type, message });
}

// CSRF: one random token per session, required on every state-changing request.
function csrf(req, res, next) {
  if (!req.session.csrf) req.session.csrf = crypto.randomBytes(24).toString('hex');
  res.locals.csrfToken = req.session.csrf;
  next();
}

function verifyCsrf(req, res, next) {
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) return next();
  const token = req.body?._csrf || req.get('x-csrf-token');
  const expected = req.session.csrf;
  if (
    token && expected && token.length === expected.length &&
    crypto.timingSafeEqual(Buffer.from(token), Buffer.from(expected))
  ) {
    return next();
  }
  const err = new Error('Your session expired or the form was tampered with. Please try again.');
  err.status = 403;
  next(err);
}

// Multipart bodies are only parsed by multer, so those routes verify CSRF after the upload middleware.
function verifyCsrfUnlessMultipart(req, res, next) {
  if (req.is('multipart/form-data')) return next();
  verifyCsrf(req, res, next);
}

function loadUser(req, res, next) {
  req.user = null;
  if (req.session.userId) {
    req.user = get('SELECT id, name, email, phone, company, role, created_at FROM users WHERE id = ?', req.session.userId) || null;
    if (!req.user) delete req.session.userId;
  }
  res.locals.currentUser = req.user;
  next();
}

function requireAuth(req, res, next) {
  if (req.user) return next();
  flash(req, 'info', 'Please sign in to continue.');
  res.redirect(`/login?next=${encodeURIComponent(req.originalUrl)}`);
}

function requireStaff(req, res, next) {
  if (!req.user) return requireAuth(req, res, next);
  if (req.user.role === 'admin' || req.user.role === 'staff') return next();
  const err = new Error('You do not have access to that page.');
  err.status = 403;
  next(err);
}

function requireAdmin(req, res, next) {
  if (req.user?.role === 'admin') return next();
  const err = new Error('Only administrators can do that.');
  err.status = 403;
  next(err);
}

fs.mkdirSync(config.uploadDir, { recursive: true });
fs.mkdirSync(config.privateDir, { recursive: true });

const IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
const DOC_TYPES = new Set([
  ...IMAGE_TYPES,
  'application/pdf',
  'application/zip',
  'application/x-zip-compressed',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
]);
const EXT = {
  'image/jpeg': '.jpg', 'image/png': '.png', 'image/webp': '.webp', 'image/gif': '.gif',
  'application/pdf': '.pdf', 'application/zip': '.zip', 'application/x-zip-compressed': '.zip',
  'application/msword': '.doc',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '.docx',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': '.pptx',
};

function uploader(allowed, destination) {
  return multer({
    storage: multer.diskStorage({
      destination,
      // The stored name never derives from the client's filename.
      filename: (req, file, cb) => cb(null, `${Date.now()}-${crypto.randomBytes(8).toString('hex')}${EXT[file.mimetype]}`),
    }),
    limits: { fileSize: config.maxUploadMb * 1024 * 1024, files: 4 },
    fileFilter: (req, file, cb) => {
      if (allowed.has(file.mimetype)) return cb(null, true);
      const err = new Error('That file type is not allowed.');
      err.status = 400;
      cb(err);
    },
  });
}

// Images are public site content; client documents are kept private and only served to their owner and staff.
const imageUpload = uploader(IMAGE_TYPES, config.uploadDir);
const documentUpload = uploader(DOC_TYPES, config.privateDir);

function uploadedPath(file) {
  return file ? `/uploads/${path.basename(file.path)}` : null;
}

function discardUpload(file) {
  if (file) fs.rm(file.path, { force: true }, () => {});
}

module.exports = {
  flash,
  csrf,
  verifyCsrf,
  verifyCsrfUnlessMultipart,
  loadUser,
  requireAuth,
  requireStaff,
  requireAdmin,
  imageUpload,
  documentUpload,
  uploadedPath,
  discardUpload,
};
