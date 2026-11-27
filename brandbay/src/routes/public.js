const express = require('express');
const rateLimit = require('express-rate-limit');
const { all, get, run, transaction } = require('../db');
const lib = require('../lib');
const mailer = require('../mailer');
const config = require('../config');
const invoicing = require('../invoicing');
const seo = require('../seo');
const path = require('node:path');
const { flash, documentUpload, verifyCsrf, discardUpload } = require('../middleware');

const router = express.Router();

const formLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  limit: process.env.NODE_ENV === 'test' ? 1000 : 20,
  standardHeaders: 'draft-7',
  legacyHeaders: false,
  handler: (req, res, next) => {
    const err = new Error('Too many submissions from your connection. Please wait a few minutes and try again.');
    err.status = 429;
    next(err);
  },
});

const services = () => all('SELECT * FROM service_categories WHERE published = 1 ORDER BY sort_order, id');
const testimonials = () => all('SELECT * FROM testimonials WHERE published = 1 ORDER BY sort_order, id');
const faqs = () => all('SELECT * FROM faqs WHERE published = 1 ORDER BY sort_order, id');
const faqPageSchema = () => seo.faqPage(faqs());
const album = (name, featuredOnly = false) =>
  all(`SELECT * FROM gallery_items WHERE published = 1 AND album = ? ${featuredOnly ? 'AND featured = 1' : ''} ORDER BY sort_order, id`, name);

// Where to send someone back to after a form posted from any page.
function back(req, fallback = '/') {
  const ref = req.get('referer');
  try {
    const url = new URL(ref);
    if (url.host === req.get('host')) return url.pathname + url.search;
  } catch {}
  return fallback;
}

router.get('/', (req, res) => {
  res.render('home', {
    description: 'Branding, logo design, websites, digital marketing, printing and business registration from BrandBay Innovation Studios in Lusaka, Zambia.',
    services: services(),
    packages: packages(),
    logos: album('logos', true),
    projects: all('SELECT * FROM projects WHERE published = 1 AND featured = 1 ORDER BY sort_order, id LIMIT 4'),
    testimonials: testimonials(),
    stats: {
      projects: get('SELECT COUNT(*) AS n FROM projects WHERE published = 1').n,
      brands: get("SELECT COUNT(*) AS n FROM gallery_items WHERE published = 1 AND album = 'logos'").n,
      services: get('SELECT COUNT(*) AS n FROM service_categories WHERE published = 1').n,
    },
  });
});

router.get('/about', (req, res) => {
  res.render('about', {
    title: 'About Us — Creative & Tech Studio in Lusaka',
    description: 'Meet BrandBay Innovation Studios, a registered Zambian branding and technology studio on Cairo Road, Lusaka, helping entrepreneurs and companies build brands that stand out.',
    schema: [seo.breadcrumbs([['About', '/about']])],
    testimonials: testimonials(),
    certificates: album('compliance'),
  });
});

const packages = () => all('SELECT * FROM packages WHERE published = 1 ORDER BY sort_order, price, id');
const courses = require('../courses');
const packageService = (p) => `Package — ${p.name} (from ${lib.moneyShort(p.price)})`;

// Short, shareable link for business cards, WhatsApp and emails: brandbaystudios.online/review
router.get('/review', (req, res) => {
  const url = res.locals.googleReviews.reviewUrl;
  res.redirect(302, url || '/contact');
});

router.get('/packages', (req, res) => {
  const list = packages();
  res.render('packages', {
    title: 'Branding & Website Packages and Prices',
    description: `Branding, logo and website packages from ${list.length ? lib.moneyShort(list[0].price) : 'K1,500'} at BrandBay Innovation Studios in Lusaka, Zambia. See what is included and get started.`,
    schema: [seo.packages(list), seo.breadcrumbs([['Packages', '/packages']])],
    packages: list,
    websites: all("SELECT * FROM projects WHERE published = 1 AND category = 'Website Design' ORDER BY sort_order, id LIMIT 3"),
  });
});

router.get('/courses', (req, res) => {
  res.render('courses', {
    title: 'Short Courses in Design, Web and Digital Skills',
    description: 'Short courses in Lusaka: graphic design, branding, website building, computer skills, social media, digital marketing and cyber safety. Small classes and a BrandBay certificate.',
    schema: [seo.breadcrumbs([['Courses', '/courses']])],
    groups: courses.categories(),
  });
});

const trainingPage = (extra = {}) => ({
  title: 'Team Training for Institutions: 1-Week Workshops',
  description: 'One-week workshops for institutions in Zambia: cyber security awareness, digital skills, communications, design, websites and digital marketing. We train your team and quote you a group price.',
  schema: [seo.breadcrumbs([['Courses', '/courses'], ['Team training', '/team-training']])],
  workshops: courses.WORKSHOPS,
  timelines: lib.TIMELINES,
  picked: [], form: {},
  ...extra,
});

router.get('/team-training', (req, res) => {
  const picked = String(req.query.w || '').split(',').filter((slug) => courses.workshopBySlug(slug));
  res.render('team-training', trainingPage({ picked, form: { name: req.user?.name, email: req.user?.email, phone: req.user?.phone, company: req.user?.company } }));
});

router.post('/team-training', formLimiter, (req, res) => {
  if (req.body.website) return res.redirect('/');
  const form = {
    name: lib.clean(req.body.name, 120),
    role: lib.clean(req.body.role, 120),
    email: lib.clean(req.body.email, 200),
    phone: lib.clean(req.body.phone, 40),
    company: lib.clean(req.body.company, 160),
    people: Math.min(500, Math.max(0, parseInt(req.body.people, 10) || 0)),
    venue: lib.clean(req.body.venue, 200),
    timeline: lib.clean(req.body.timeline, 60),
    notes: lib.clean(req.body.notes, 4000),
  };
  const picked = courses.WORKSHOPS.filter((w) => req.body[`w_${w.slug}`]);
  const errors = [];
  if (!form.company) errors.push('Please enter the name of your institution.');
  if (!form.name) errors.push('Please enter the contact person’s name.');
  if (!lib.isEmail(form.email)) errors.push('Please enter a valid email address.');
  if (!picked.length && !form.notes) errors.push('Choose at least one workshop, or describe what you need.');
  if (errors.length) {
    return res.status(422).render('team-training', trainingPage({ picked: picked.map((w) => w.slug), form, errors }));
  }
  const list = picked.map((w) => `• ${w.name} (5 days)`).join('\n') || '• Custom programme (see notes)';
  createRequest(req, {
    name: form.name, email: form.email, phone: form.phone, company: form.company,
    service: `Team training — ${picked.length || 1} workshop${picked.length === 1 ? '' : 's'}`,
    budget: 'Quote requested',
    timeline: form.timeline,
    description: `Team training quote request\nInstitution: ${form.company}\nContact: ${form.name}${form.role ? ` (${form.role})` : ''}\nParticipants: ${form.people || 'not stated'}\nVenue: ${form.venue || 'not stated'}\n\nWorkshops requested:\n${list}${form.notes ? `\n\nNotes:\n${form.notes}` : ''}`,
  });
  res.redirect('/apply/thank-you');
});

router.get('/services', (req, res) => {
  res.render('services', {
    title: 'Branding, Design, Web & Marketing Services in Zambia',
    description: 'Logo design and brand identity, social media and SEO, website and app design, event branding, PACRA/ZRA/ZPPA business registration and printing — all from one studio in Lusaka, Zambia.',
    schema: [seo.breadcrumbs([['Services', '/services']])],
    services: services(),
  });
});

const SHOWCASES = {
  'technological-solutions': { title: 'Websites we’ve built', categories: ['Website Design'], link: '/projects?category=Website%20Design', linkLabel: 'All websites' },
  'printing-artistry': { title: 'Branding & print jobs', categories: ['T-Shirt Printing', 'Embroidery', 'Branded Merchandise', 'ID Cards & Badges', 'Business Cards', 'Flyers & Brochures', 'Vehicle Branding', 'Signage', 'Shopfront Branding', 'Pull-up Banners', 'Stickers & Labels'], link: '/projects', linkLabel: 'All projects' },
  'service-event-branding': { title: 'Event & branding jobs', categories: ['Exhibitions & Events', 'Office Branding', 'Pull-up Banners', 'Signage', 'Vehicle Branding'], link: '/projects', linkLabel: 'All projects' },
};

function serviceShowcase(slug) {
  const s = SHOWCASES[slug];
  if (!s) return null;
  const items = all(`SELECT * FROM projects WHERE published = 1 AND category IN (${s.categories.map(() => '?').join(', ')}) ORDER BY sort_order, id LIMIT 6`, ...s.categories);
  return items.length ? { ...s, items } : null;
}

router.get('/services/:slug', (req, res, next) => {
  const service = get('SELECT * FROM service_categories WHERE slug = ? AND published = 1', req.params.slug);
  if (!service) return next();
  res.render('service', {
    title: `${service.name} in Lusaka, Zambia`,
    description: `${service.name} in Lusaka, Zambia by BrandBay Innovation Studios: ${lib.lines(service.items).slice(0, 4).join(', ')} and more.`,
    schema: [seo.service(service), seo.breadcrumbs([['Services', '/services'], [service.name, `/services/${service.slug}`]])],
    service,
    others: services().filter((s) => s.id !== service.id),
    // Some services show matching work instead of the general "Recent work".
    showcase: serviceShowcase(service.slug),
    projects: all('SELECT * FROM projects WHERE published = 1 ORDER BY featured DESC, sort_order LIMIT 3'),
  });
});

router.get('/projects', (req, res) => {
  const projects = all('SELECT * FROM projects WHERE published = 1 ORDER BY sort_order, id');
  const categories = [...new Set(projects.map((p) => p.category).filter(Boolean))];
  res.render('projects', {
    title: 'Portfolio — Branding & Design Projects in Zambia',
    description: 'Websites, brand identities, company profiles, social media and advertising design by BrandBay Innovation Studios for businesses in Lusaka and across Zambia.',
    schema: [seo.breadcrumbs([['Projects', '/projects']])],
    projects,
    categories,
  });
});

router.get('/projects/:slug', (req, res, next) => {
  const project = get('SELECT * FROM projects WHERE slug = ? AND published = 1', req.params.slug);
  if (!project) return next();
  res.render('project', {
    // Google shows about 60 characters; the layout adds " | BrandBay".
    title: project.category && project.title.length + project.category.length <= 44 ? `${project.title} — ${project.category}` : project.title,
    description: project.summary,
    ogImage: project.image,
    schema: [seo.project(project), seo.breadcrumbs([['Projects', '/projects'], [project.title, `/projects/${project.slug}`]])],
    project,
    related: all('SELECT * FROM projects WHERE published = 1 AND id != ? ORDER BY (category = ?) DESC, sort_order LIMIT 3', project.id, project.category || ''),
  });
});

router.get('/gallery', (req, res) => {
  res.render('gallery', {
    title: 'Logo & Poster Design Portfolio',
    description: 'Browse logos and posters designed by BrandBay Innovation Studios for Zambian businesses — restaurants, fashion, finance, education, beauty and more.',
    schema: [seo.breadcrumbs([['Gallery', '/gallery']])],
    logos: album('logos'),
    posters: album('posters'),
  });
});

router.get('/blog', (req, res) => {
  res.render('blog', {
    title: 'Insights — Branding & Marketing Tips',
    description: 'Practical branding, design and digital marketing advice for Zambian businesses from BrandBay Innovation Studios.',
    posts: all('SELECT * FROM posts WHERE published = 1 ORDER BY published_at DESC, id DESC'),
  });
});

router.get('/blog/:slug', (req, res, next) => {
  const post = get('SELECT * FROM posts WHERE slug = ? AND published = 1', req.params.slug);
  if (!post) return next();
  res.render('post', {
    title: post.title, description: post.excerpt, post, ogType: 'article', ogImage: post.image,
    schema: [seo.article(post), seo.breadcrumbs([['Insights', '/blog'], [post.title, `/blog/${post.slug}`]])],
  });
});

router.get('/contact', (req, res) => {
  res.render('contact', {
    title: 'Contact Us — Branding Agency in Lusaka',
    description: 'Call, WhatsApp or message BrandBay Innovation Studios in Lusaka and Mpongwe, Zambia for branding, design, web, marketing and printing quotes. Answers to common questions on timelines and payment.',
    schema: [faqPageSchema(), seo.breadcrumbs([['Contact', '/contact']])],
    faqs: faqs(),
    form: {},
  });
});

router.post('/contact', formLimiter, async (req, res) => {
  const form = {
    name: lib.clean(req.body.name, 120),
    email: lib.clean(req.body.email, 200),
    phone: lib.clean(req.body.phone, 40),
    subject: lib.clean(req.body.subject, 200),
    message: lib.clean(req.body.message, 5000),
  };
  // Honeypot: real visitors never see or fill this field.
  if (req.body.website) return res.redirect('/contact?sent=1');
  const errors = [];
  if (!lib.isEmail(form.email)) errors.push('Please enter a valid email address.');
  if (form.message.length < 5) errors.push('Please tell us a little about your inquiry.');
  if (errors.length) {
    return res.status(422).render('contact', { title: 'Contact', faqs: faqs(), form, errors });
  }
  run('INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)',
    form.name, form.email, form.phone, form.subject, form.message);
  mailer.notifyStudio(`New website message from ${form.name || form.email}`,
    `${form.name}\n${form.email}\n${form.phone}\n\n${form.subject}\n\n${form.message}`);
  flash(req, 'success', 'Thank you — your message has been sent. We will get back to you shortly.');
  res.redirect('/contact');
});

router.post('/subscribe', formLimiter, (req, res) => {
  const email = lib.clean(req.body.email, 200);
  if (!lib.isEmail(email)) {
    flash(req, 'error', 'Please enter a valid email address to subscribe.');
  } else {
    run('INSERT INTO subscribers (email) VALUES (?) ON CONFLICT(email) DO NOTHING', email);
    flash(req, 'success', 'You are subscribed to the BrandBay newsletter.');
  }
  res.redirect(back(req));
});

router.get('/apply', (req, res) => {
  const all_ = services();
  const preset = all_.find((s) => s.slug === req.query.service);
  const list = packages();
  const pkg = list.find((p) => p.slug === req.query.package);
  const course = courses.bySlug(req.query.course);
  res.render('apply', {
    courseOptions: courses.COURSES.map(courses.courseService),
    title: 'Get a Quote — Apply for a Service',
    description: 'Tell us about your branding, design, website, marketing or printing project and get a tailored quote from BrandBay Innovation Studios in Lusaka.',
    services: all_,
    packageOptions: list.map(packageService),
    form: {
      service: pkg ? packageService(pkg) : course ? courses.courseService(course) : preset ? preset.name : lib.clean(req.query.service, 120),
      budget: pkg ? lib.BUDGETS.find((b) => (pkg.price <= 5000 ? b.startsWith('K1,000') : b.startsWith('K5,000'))) : undefined,
      name: req.user?.name, email: req.user?.email, phone: req.user?.phone, company: req.user?.company,
    },
  });
});

// Saves a service request (from /apply or /quote), emails the studio and the client, and returns its reference.
function createRequest(req, form, attachment = null) {
  const userId = req.user?.id ?? get('SELECT id FROM users WHERE email = ?', form.email)?.id ?? null;
  const request = transaction(() => {
    const { lastInsertRowid: id } = run(
      `INSERT INTO service_requests (user_id, name, email, phone, company, service, budget, timeline, description, attachment, estimate_amount)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      userId, form.name, form.email, form.phone, form.company, form.service, form.budget, form.timeline,
      form.description, attachment, form.estimate ?? null,
    );
    const reference = lib.makeReference('BB', id);
    run('UPDATE service_requests SET reference = ? WHERE id = ?', reference, id);
    return { id, reference };
  });

  mailer.notifyStudio(`New service request ${request.reference}: ${form.service}`,
    `${form.name} <${form.email}> ${form.phone}\nCompany: ${form.company}\nService: ${form.service}\nBudget: ${form.budget}\nTimeline: ${form.timeline}\n\n${form.description}\n\n${config.baseUrl}/admin/requests/${request.id}`);
  mailer.send({
    to: form.email,
    subject: `We received your request (${request.reference})`,
    text: `Hi ${form.name},\n\nThank you for applying for ${form.service} with BrandBay Innovation Studios. Your reference is ${request.reference}.${form.estimate ? `\n\nYour instant estimate: from ${lib.moneyShort(form.estimate)}. We will confirm your final quote after reviewing your request.` : ''}\n\nOur team will review your request and get back to you with a quote. You can follow progress by creating an account with this email address at ${config.baseUrl}/register.\n\nBrandBay Innovation Studios`,
  });

  req.session.lastRequest = request.reference;
  req.session.lastEstimate = form.estimate ?? null;
  return request;
}

const estimateItems = () => all('SELECT * FROM estimate_items WHERE published = 1 ORDER BY sort_order, id');

// Reads ticked items and quantities from the form and prices them from the database (never from the browser).
function readEstimate(body) {
  const lines = [];
  for (const item of estimateItems()) {
    if (!body[`item_${item.id}`]) continue;
    const qty = item.has_qty ? Math.min(50, Math.max(1, parseInt(body[`qty_${item.id}`], 10) || 1)) : 1;
    lines.push({ item, qty, amount: item.price * qty });
  }
  return { lines, total: lines.reduce((sum, l) => sum + l.amount, 0) };
}

router.get('/quote', (req, res) => {
  res.render('quote', {
    title: 'Instant Quote Estimator — Branding, Web & Design Prices',
    description: 'Tick the branding, website, social media and print design services you need and see an instant price estimate from BrandBay Innovation Studios in Lusaka, Zambia.',
    schema: [seo.breadcrumbs([['Instant quote', '/quote']])],
    items: estimateItems(),
    packages: packages(),
    form: { name: req.user?.name, email: req.user?.email, phone: req.user?.phone, company: req.user?.company },
    picked: {},
  });
});

router.post('/quote', formLimiter, (req, res) => {
  if (req.body.website) return res.redirect('/');
  const form = {
    name: lib.clean(req.body.name, 120),
    email: lib.clean(req.body.email, 200),
    phone: lib.clean(req.body.phone, 40),
    company: lib.clean(req.body.company, 160),
    timeline: lib.clean(req.body.timeline, 60),
    notes: lib.clean(req.body.notes, 4000),
  };
  const { lines, total } = readEstimate(req.body);
  const errors = [];
  if (!lines.length) errors.push('Tick at least one service to get an estimate.');
  if (!form.name) errors.push('Please enter your full name.');
  if (!lib.isEmail(form.email)) errors.push('Please enter a valid email address.');
  if (errors.length) {
    const picked = Object.fromEntries(lines.map((l) => [l.item.id, l.qty]));
    return res.status(422).render('quote', {
      title: 'Instant Quote Estimator', items: estimateItems(), packages: packages(), form, picked, errors,
    });
  }
  const list = lines.map((l) => `• ${l.item.name}${l.qty > 1 ? ` × ${l.qty}` : ''} — ${lib.moneyShort(l.amount)}`).join('\n');
  createRequest(req, {
    ...form,
    service: `Instant estimate — ${lines.length} service${lines.length === 1 ? '' : 's'}`,
    budget: `Estimate from ${lib.moneyShort(total)}`,
    description: `Services selected in the instant quote estimator:\n${list}\n\nEstimated total: from ${lib.moneyShort(total)}${form.notes ? `\n\nNotes from the client:\n${form.notes}` : ''}`,
    estimate: total,
  });
  res.redirect('/apply/thank-you');
});

router.post('/apply', formLimiter, documentUpload.single('attachment'), verifyCsrf, async (req, res) => {
  const form = {
    name: lib.clean(req.body.name, 120),
    email: lib.clean(req.body.email, 200),
    phone: lib.clean(req.body.phone, 40),
    company: lib.clean(req.body.company, 160),
    service: lib.clean(req.body.service, 160),
    budget: lib.clean(req.body.budget, 60),
    timeline: lib.clean(req.body.timeline, 60),
    description: lib.clean(req.body.description, 8000),
  };
  if (req.body.website) {
    discardUpload(req.file);
    return res.redirect('/');
  }
  const errors = [];
  if (!form.name) errors.push('Please enter your full name.');
  if (!lib.isEmail(form.email)) errors.push('Please enter a valid email address.');
  if (!form.service) errors.push('Please choose the service you are applying for.');
  if (errors.length) {
    discardUpload(req.file);
    return res.status(422).render('apply', { title: 'Apply for a Service', services: services(), packageOptions: packages().map(packageService), courseOptions: courses.COURSES.map(courses.courseService), form, errors });
  }

  createRequest(req, form, req.file ? path.basename(req.file.path) : null);
  res.redirect('/apply/thank-you');
});

router.get('/apply/thank-you', (req, res) => {
  const reference = req.session.lastRequest;
  if (!reference) return res.redirect('/apply');
  res.render('thank-you', { title: 'Request received', reference, estimate: req.session.lastEstimate, noindex: true });
});

// Shareable, unguessable link to a document, for clients without an account.
router.get('/invoice/:token', (req, res, next) => {
  if (!/^[a-f0-9]{48}$/.test(req.params.token)) return next();
  const doc = invoicing.load('public_token = ?', req.params.token);
  if (!doc) return next();
  res.set('X-Robots-Tag', 'noindex');
  res.render('invoices/print', { doc, invoicing, is: invoicing.invoiceSettings(), autoPrint: req.query.print === '1' });
});

router.get('/terms', (req, res) => {
  res.render('terms', { title: 'Terms & Conditions', description: 'Payment terms, timelines, deliverables and change policy for BrandBay Innovation Studios services.' });
});

router.get('/robots.txt', (req, res) => {
  const privatePaths = ['/admin', '/account', '/cart', '/checkout', '/invoice/', '/login', '/register', '/forgot-password', '/reset-password', '/apply/thank-you'];
  res.type('text/plain').send(`User-agent: *\n${privatePaths.map((p) => `Disallow: ${p}`).join('\n')}\nAllow: /\n\nSitemap: ${config.baseUrl}/sitemap.xml\n`);
});

router.get('/sitemap.xml', (req, res) => {
  const xml = (v) => String(v ?? '').replace(/[<>&'"]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' }[c]));
  const day = (v) => (v ? String(v).slice(0, 10) : null);
  const entries = [
    { loc: '/', priority: '1.0', images: album('logos', true).map((g) => [g.image, `${g.title} logo design`]) },
    { loc: '/services', priority: '0.9' },
    { loc: '/packages', priority: '0.9' },
    { loc: '/quote', priority: '0.8' },
    { loc: '/courses', priority: '0.8' },
    { loc: '/team-training', priority: '0.8' },
    { loc: '/projects', priority: '0.8' },
    { loc: '/gallery', priority: '0.8', images: [...album('logos'), ...album('posters')].map((g) => [g.image, g.title]) },
    { loc: '/about', priority: '0.7', images: album('compliance').map((g) => [g.image, g.title]) },
    { loc: '/contact', priority: '0.7' },
    { loc: '/apply', priority: '0.7' },
    { loc: '/terms', priority: '0.3' },
  ];
  for (const s of services()) entries.push({ loc: `/services/${s.slug}`, priority: '0.9' });
  for (const p of all('SELECT slug, title, image, image_2, created_at FROM projects WHERE published = 1')) {
    entries.push({ loc: `/projects/${p.slug}`, priority: '0.6', lastmod: day(p.created_at), images: [p.image, p.image_2].filter(Boolean).map((i) => [i, p.title]) });
  }
  const posts = all('SELECT slug, title, image, published_at FROM posts WHERE published = 1');
  if (posts.length) entries.push({ loc: '/blog', priority: '0.6', lastmod: day(posts.map((p) => p.published_at).sort().pop()) });
  for (const p of posts) entries.push({ loc: `/blog/${p.slug}`, priority: '0.6', lastmod: day(p.published_at), images: p.image ? [[p.image, p.title]] : [] });
  const products = all('SELECT slug, name, image, created_at FROM products WHERE published = 1');
  if (products.length) entries.push({ loc: '/store', priority: '0.6' });
  for (const p of products) {
    entries.push({ loc: `/store/${p.slug}`, priority: '0.5', images: p.image ? [[p.image, p.name]] : [] });
  }
  const body = entries.map((e) => [
    '  <url>',
    `    <loc>${xml(config.baseUrl + e.loc)}</loc>`,
    e.lastmod ? `    <lastmod>${e.lastmod}</lastmod>` : null,
    `    <priority>${e.priority}</priority>`,
    ...(e.images || []).map(([src, title]) => `    <image:image><image:loc>${xml(seo.abs(src))}</image:loc><image:title>${xml(title)}</image:title></image:image>`),
    '  </url>',
  ].filter(Boolean).join('\n')).join('\n');
  res.type('application/xml').send(`<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">\n${body}\n</urlset>\n`);
});

router.get('/healthz', (req, res) => res.json({ ok: true }));

module.exports = router;
