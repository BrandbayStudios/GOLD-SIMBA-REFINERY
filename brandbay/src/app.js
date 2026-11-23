const path = require('node:path');
const express = require('express');
const session = require('express-session');
const helmet = require('helmet');
const compression = require('compression');
const config = require('./config');
const SqliteStore = require('./session-store');
const lib = require('./lib');
const seo = require('./seo');
const { all, get } = require('./db');
const reviews = require('./reviews');
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
        imgSrc: ["'self'", 'data:', 'https://tracker.metricool.com'],
        styleSrc: ["'self'", 'https://fonts.googleapis.com'],
        fontSrc: ["'self'", 'https://fonts.gstatic.com'],
        scriptSrc: ["'self'", 'https://tracker.metricool.com'],
        frameSrc: ['https://www.google.com'],
        formAction: ["'self'"],
        upgradeInsecureRequests: config.isProd ? [] : null,
      },
    },
    crossOriginEmbedderPolicy: false,
  }),
);

// One canonical host: www.<domain> permanently redirects to <domain> so search engines see one site.
const canonicalHost = new URL(config.baseUrl).host;
app.use((req, res, next) => {
  if (config.isProd && req.get('host') === `www.${canonicalHost}`) return res.redirect(301, config.baseUrl + req.originalUrl);
  next();
});

app.use(compression());
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
  if (req.session.flash) delete req.session.flash;
  res.locals.settings = lib.getSettings();
  res.locals.googleReviews = reviews.summary(res.locals.settings);
  res.locals.path = req.path;
  res.locals.cartCount = (req.session.cart || []).reduce((n, i) => n + i.qty, 0);
  res.locals.lib = lib;
  res.locals.seo = seo;
  // Store and Insights links show only once there is something to see.
  res.locals.storeOpen = Boolean(get('SELECT 1 AS x FROM products WHERE published = 1 LIMIT 1'));
  res.locals.blogOpen = Boolean(get('SELECT 1 AS x FROM posts WHERE published = 1 LIMIT 1'));
  res.locals.orgServices = all('SELECT name, slug, items FROM service_categories WHERE published = 1 ORDER BY sort_order, id');
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
  res.status(404).render('error', { noindex: true, title: 'Page not found', status: 404, message: 'The page you are looking for does not exist or has moved.' });
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
  res.status(status).render('error', { noindex: true, title: status === 403 ? 'Not allowed' : 'Error', status, message });
});

module.exports = app;
