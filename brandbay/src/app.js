const path = require('node:path');
const express = require('express');
const session = require('express-session');
const helmet = require('helmet');
const config = require('./config');
const SqliteStore = require('./session-store');
const lib = require('./lib');
const { csrf, verifyCsrfUnlessMultipart, loadUser } = require('./middleware');

const app = express();

app.set('view engine', 'ejs');
app.set('views', path.join(config.root, 'views'));
if (config.trustProxy) app.set('trust proxy', 1);
app.disable('x-powered-by');

app.use(
  helmet({
    contentSecurityPolicy: {
      directives: {
        defaultSrc: ["'self'"],
        imgSrc: ["'self'", 'data:'],
        styleSrc: ["'self'", 'https://fonts.googleapis.com'],
        fontSrc: ["'self'", 'https://fonts.gstatic.com'],
        scriptSrc: ["'self'"],
        frameSrc: ['https://www.google.com'],
        formAction: ["'self'"],
        upgradeInsecureRequests: config.isProd ? [] : null,
      },
    },
    crossOriginEmbedderPolicy: false,
  }),
);

app.use(express.static(path.join(config.root, 'public'), { maxAge: config.isProd ? '7d' : 0 }));
app.use('/uploads', express.static(config.uploadDir, { maxAge: config.isProd ? '30d' : 0 }));
app.use(express.urlencoded({ extended: false, limit: '1mb' }));
app.use(express.json({ limit: '1mb' }));

app.use(
  session({
    store: new SqliteStore(),
    secret: config.sessionSecret,
    name: 'bb.sid',
    resave: false,
    saveUninitialized: false,
    rolling: true,
    cookie: { httpOnly: true, sameSite: 'lax', secure: config.isProd, maxAge: 14 * 24 * 60 * 60 * 1000 },
  }),
);

app.use(csrf);
app.use(verifyCsrfUnlessMultipart);
app.use(loadUser);

app.use((req, res, next) => {
  res.locals.flash = req.session.flash || [];
  delete req.session.flash;
  res.locals.settings = lib.getSettings();
  res.locals.path = req.path;
  res.locals.cartCount = (req.session.cart || []).reduce((n, i) => n + i.qty, 0);
  res.locals.lib = lib;
  res.locals.config = config;
  res.locals.title = null;
  res.locals.description = null;
  next();
});

app.use(require('./routes/public'));
app.use(require('./routes/auth'));
app.use('/account', require('./routes/account'));
app.use(require('./routes/shop'));
app.use('/admin', require('./routes/admin'));

app.use((req, res) => {
  res.status(404).render('error', { title: 'Page not found', status: 404, message: 'The page you are looking for does not exist or has moved.' });
});

app.use((err, req, res, next) => {
  let status = err.status || err.statusCode || 500;
  let message = err.message;
  if (err.code === 'LIMIT_FILE_SIZE') {
    status = 400;
    message = `Files must be ${config.maxUploadMb}MB or smaller.`;
  }
  if (status >= 500) {
    console.error(err);
    message = 'Something went wrong on our side. Please try again shortly.';
  }
  if (res.headersSent) return next(err);
  res.status(status).render('error', { title: status === 403 ? 'Not allowed' : 'Error', status, message });
});

module.exports = app;
