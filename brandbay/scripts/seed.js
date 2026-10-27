// Loads BrandBay's existing website content (copy, services, portfolio, FAQs, testimonials)
// into an empty database. Safe to re-run: each table is only seeded when it is empty.
const bcrypt = require('bcryptjs');
const { get, run, transaction } = require('../src/db');
const { setSetting, getSettings } = require('../src/lib');
const invoicing = require('../src/invoicing');

const SETTINGS = {
  site_name: 'BrandBay Innovation Studios',
  tagline: 'Brand | Build | Boost',
  hero_title: 'Where Bold Brands Are Engineered to Win.',
  hero_text:
    'At BrandBay Innovation Studios, we merge strategy, design, and digital innovation to build brands that command attention, influence markets, and scale with precision.',
  phone: '+260972015689',
  whatsapp: '260972015689',
  email: 'info@brandbaystudio.online',
  address_1: 'Cairo Road, Lusaka, Zambia',
  address_2: 'Mpongwe, Copperbelt Zambia',
  facebook_url: '',
  instagram_url: '',
  linkedin_url: '',
  payment_instructions:
    'Once we confirm your order we will send Mobile Money and bank details to your email and phone. Please use your order reference as the payment reference.',
  terms:
    'Payment structure\n\nServices are billed on a 70% upfront deposit and 30% upon completion. Printing jobs may require full or higher upfront payment depending on production requirements. Custom quotes are provided based on project scope and complexity.\n\nTimelines\n\nGraphic design projects: 3–7 working days. Branding projects: 5–14 working days. Website development: 1–4 weeks. Printing turnaround varies based on quantity and specifications. Timelines start once the deposit and all required content have been received.\n\nDeliverables and source files\n\nUpon full payment, clients receive final deliverables in appropriate formats. Editable/source files (e.g. AI, PSD, Canva) can be provided upon request, depending on the project agreement.\n\nChanges after completion\n\nPost-delivery changes are treated as a new request or billed separately, depending on the scope. This ensures a structured workflow and protects project timelines.',
};

// Introductions for each service page (unique, location-specific copy for search engines and visitors).
const SERVICE_INTROS = {
  'graphic-design-brand-identity':
    'Your brand identity is the first thing customers judge. BrandBay Innovation Studios designs logos and complete brand identities for startups, SMEs, churches, schools and corporates in Lusaka and across Zambia — from naming and strategy to colour palettes, typography and brand guidelines your team can actually use.\n\nEvery identity starts with your market and your audience, so the result looks professional on a shop sign in Lusaka, a social media profile and a tender document alike.',
  'digital-advertising-marketing':
    'We help Zambian businesses turn online visibility into enquiries and sales. Our team plans and runs social media marketing on Facebook, Instagram, TikTok and LinkedIn, manages paid advertising campaigns, writes content, sets up email marketing and improves search engine optimisation (SEO) so customers in Lusaka and beyond can find you on Google.\n\nCampaigns are measured on reach, leads and revenue — not just likes.',
  'technological-solutions':
    'Modern businesses need modern systems. BrandBay designs and builds websites, web apps and e-commerce stores for Zambian businesses, sets up EPOS (point-of-sale) systems for shops and restaurants, and supplies IT hardware and support.\n\nWhether you need a simple company website, an online store that takes Mobile Money, or advice on the right digital tools for your business, we align the technology with your goals and budget.',
  'service-event-branding':
    'Professional presentation builds trust. We design company profiles, corporate documents, presentations, event posters and flyers, pull-up banners and motion graphics for businesses, NGOs, churches and events in Lusaka and the Copperbelt.\n\nFrom a launch event to a tender submission, every touchpoint is designed to look consistent and on-brand.',
  'compliance-business-registration':
    'Strong businesses are built on strong foundations. BrandBay helps entrepreneurs formalise their businesses in Zambia: company and business name registration with PACRA, supplier registration with ZPPA for government tenders, ZRA tax registration (TPIN), and professional business plans and proposals.\n\nWe handle the paperwork so you can focus on running your business — and we can design your brand at the same time.',
  'printing-artistry':
    'Quality printing in Lusaka, designed and produced in one place. We print business cards, flyers and posters, pull-up banners, brochures and company profiles, stickers and labels, branded stationery and event materials.\n\nSend your own artwork or let our designers create it, and we will advise on paper, finishes and quantities so your print looks as good as it does on screen.',
};

const SERVICES = [
  {
    slug: 'graphic-design-brand-identity',
    name: 'Graphic Design & Brand Identity',
    tagline: 'We craft brands that communicate clarity and dominance.',
    items: ['Logo Design', 'Brand Strategy', 'Corporate Branding', 'Product Branding', 'Visual Identity Systems', 'Brand Naming'],
    closing: 'Your brand must speak before you do.',
    icon: 'pen',
  },
  {
    slug: 'digital-advertising-marketing',
    name: 'Digital Advertising & Marketing',
    tagline: 'We turn visibility into revenue.',
    items: ['Search Engine Optimization (SEO)', 'Social Media Marketing', 'Email Marketing', 'Content Strategy', 'Paid Campaign Management'],
    closing: 'Performance is measured. Growth is intentional.',
    icon: 'megaphone',
  },
  {
    slug: 'technological-solutions',
    name: 'Technological Solutions',
    tagline: 'Modern businesses require modern systems.',
    items: ['Website & App Design', 'E-commerce Platforms', 'EPOS Setup', 'IT Hardware & System Support', 'Digital Infrastructure Consulting'],
    closing: 'We align technology with business objectives.',
    icon: 'code',
  },
  {
    slug: 'service-event-branding',
    name: 'Service & Event Branding',
    tagline: 'Professional presentation builds trust and authority.',
    items: ['Company Profile Design', 'Corporate Documents', 'Event Posters & Flyers', 'Pull-Up Banners', 'Motion Graphics'],
    closing: 'Every touchpoint matters.',
    icon: 'calendar',
  },
  {
    slug: 'compliance-business-registration',
    name: 'Compliance & Business Registration',
    tagline: 'We help you formalize and structure your business properly.',
    items: ['Business Registration (PACRA)', 'ZPPA Registration', 'ZRA Tax Registration', 'Business Plans & Proposals'],
    closing: 'Strong businesses are built on strong foundations.',
    icon: 'shield',
  },
  {
    slug: 'printing-artistry',
    name: 'Printing & Artistry Solutions',
    tagline: 'Our printing services include:',
    items: [
      'Business Cards', 'Flyers & Posters', 'Pull-Up Banners', 'Brochures & Company Profiles',
      'Stickers & Labels', 'Event Branding Materials', 'Branded Documents & Stationery',
    ],
    closing: 'Quality you can hold in your hands.',
    icon: 'printer',
  },
];

const LOGOS = [
  ['Zambia Innovation Hub', 'zambia-innovation-hub'], ['Faiheart Fashion Zone', 'faiheart-fashion-zone'],
  ['Crafty Hands Furniture', 'crafty-hands'], ['The Fashion Fortress', 'the-fashion-fortress'],
  ['My Melanine ZM', 'my-melanine'], ['Drizzly Bites', 'drizzly-bites'], ["Samakayi's BouChic", 'samakayis-bouchic'],
  ['Mauya Loans', 'mauya-loans'], ['Electric Fusion Bar & Lounge', 'electric-fusion'], ['Esther Chungu', 'esther-chungu'],
  ['Glam Beauty Xperience', 'glam-beauty-xperience'], ['Lemavko Tailoring & Fashion', 'lemavko'],
  ["Darlo's Nurture", 'darlos-nurture'], ["Tutee's Cakery", 'tutees-cakery'], ['Trinity University', 'trinity-university'],
  ['Yellow Bird Tailoring & Design', 'yellow-bird'], ['FreshQ Chickens', 'freshq-chickens'], ['Ruburma', 'ruburma'],
  ['Berth MediCare', 'berth-medicare'], ['Cheves Enterprises', 'cheves-enterprises'],
  ['Raine Exquisite Cakes', 'raine-exquisite-cakes'], ['Nails Galore', 'nails-galore'], ['Money Mark Loans', 'money-mark-loans'],
];

const POSTERS = [
  ['Digital Literacy Campaign', 'digital-literacy-campaign'], ['Ruburma Agro Solutions', 'ruburma-agro'],
  ['Mauya Soft Loans', 'mauya-soft-loans'], ['Upskilling Training Opportunity', 'upskilling-training'],
  ["Women's Day Buffet", 'womens-day-buffet'], ['Al Faris Gold Leaching & Refinery', 'al-faris-gold-refinery'],
  ['Mobile Massage', 'mobile-massage'], ['Goldspeed × Yango Partner', 'goldspeed-yango'],
  ['Pasachi i-Store Trade & Save', 'pasachi-istore'], ["Kei'lah's Spa & Hair Lounge", 'keilahs-spa'],
  ['Valentines Platters', 'valentines-platters'], ['DNC Designs', 'dnc-designs'],
];

const COMPLIANCE = [
  ['Certificate of Registration (PACRA)', 'certificate-registration'],
  ['ZRA Taxpayer Registration', 'certificate-zra-tpin'],
  ['ZRA Tax Clearance Certificate', 'certificate-zra-tax-clearance'],
  ['ZPPA Supplier Registration', 'certificate-zppa'],
  ['ZICTA Dealer Certificate', 'certificate-zicta'],
];

const PROJECTS = [
  {
    title: 'Altura', category: 'Brand Identity', image: 'altura',
    summary: 'A complete visual identity for Altura, applied across stationery, apparel, packaging and devices.',
    featured: 1,
  },
  {
    title: 'Daily Devotion', category: 'Brand Identity', image: 'daily-devotion',
    summary: 'Logo, colour palette and typography system for Daily Devotion — "a daily dose of faith".',
    featured: 1,
  },
  {
    title: 'Mauya Loans', category: 'Brand Identity', image: 'mauya-loans-identity',
    summary: 'Brand identity and rollout for Mauya Loans, from mobile app screens to signage, ID cards and print.',
    featured: 1,
  },
  {
    title: 'Femilux', category: 'Social Media Design', image: 'femilux', image_2: 'femilux-2',
    summary: 'Instagram-ready post designs for a beauty brand, built to stay consistent across the feed.',
  },
  {
    title: 'Corporate Social Media Campaign', category: 'Social Media Design', image: 'social-campaign-orange', image_2: 'social-campaign-orange-2',
    summary: 'A series of on-brand social posts covering announcements, recruitment and weekly engagement content.',
    featured: 1,
  },
  {
    title: 'Black Friday Product Campaign', category: 'Digital Advertising', image: 'black-friday-campaign',
    summary: 'Product-led Black Friday creatives designed for paid and organic social placements.',
  },
  {
    title: 'Phone Trade-In Campaign', category: 'Digital Advertising', image: 'phone-trade-in-campaign',
    summary: '"Out with the old, in with the new" — trade-in promotion creatives for a mobile retailer.',
  },
  {
    title: 'Creative Social Media Post Designs', category: 'Social Media Design', image: 'social-posts-blue', image_2: 'social-posts-orange',
    summary: 'Professional social page designs that help businesses boost interaction with potential customers.',
  },
  {
    title: 'Retail Social Media Designs', category: 'Social Media Design', image: 'social-posts-red',
    summary: 'Product showcase posts for a gadget retailer, designed to drive enquiries.',
  },
];

const TESTIMONIALS = [
  {
    name: 'Eugine Mavunga',
    quote: 'BrandBay Innovation Studios transformed our brand identity completely. Their creativity and attention to detail gave our business a professional image that truly stands out.',
  },
  {
    name: 'Wilkins Sikazwe',
    quote: 'Working with BrandBay was a great experience. They don’t just design, they understand business and create solutions that actually help you grow.',
  },
];

const FAQS = [
  [
    'What services does BrandBay Studios offer?',
    'BrandBay Studios provides integrated solutions across technology, branding, graphic design, and printing. Our services include website development, branding and identity design, marketing materials, social media creatives, and high-quality print production. We deliver end-to-end solutions from concept to final output.',
  ],
  [
    'How long do projects typically take?',
    'Timelines depend on the scope of work:\n\nGraphic design projects: 3–7 working days\nBranding projects: 5–14 working days\nWebsite development: 1–4 weeks\nPrinting: turnaround varies based on quantity and specifications',
  ],
  [
    'What is your pricing and payment structure?',
    'We operate on a 70% upfront deposit and 30% upon completion. Printing jobs may require full or higher upfront payment depending on production requirements. Custom quotes are provided based on project scope and complexity.',
  ],
  [
    'Will I get source files and editable formats?',
    'Yes. Upon full payment, clients receive final deliverables in appropriate formats. Editable/source files (e.g., AI, PSD, Canva) can be provided upon request, depending on the project agreement.',
  ],
  [
    'What happens if I need changes after project completion?',
    'Post-delivery changes are treated as a new request or billed separately, depending on the scope. This ensures structured workflow and protects project timelines.',
  ],
  [
    'How do I track my project?',
    'Every request gets a reference number. Create a free account with the same email address you used on the form and you can follow progress, view quotes and message our team from your dashboard.',
  ],
];

// Store items start as unpublished drafts with no price: set real prices in Admin → Products, then publish.
const PRODUCTS = [
  ['Business Cards', 'Printing', 'Full-colour business cards printed on premium card stock. Design available on request.', 'per 100'],
  ['Flyers & Posters', 'Printing', 'A5, A4 and A3 flyers and posters for promotions and events.', 'per 50'],
  ['Pull-Up Banner', 'Printing', 'Free-standing pull-up banner with carry bag — ideal for events and reception areas.', 'each'],
  ['Brochures & Company Profiles', 'Printing', 'Folded brochures and bound company profiles that present your business professionally.', 'per 25'],
  ['Stickers & Labels', 'Printing', 'Custom-cut stickers and product labels in any shape.', 'per sheet'],
  ['Logo Design Package', 'Design', 'Professional logo design with colour variations and final files for print and web.', 'package'],
];

const empty = (table) => !get(`SELECT 1 AS x FROM ${table} LIMIT 1`);

function seed() {
  transaction(() => {
    const existing = getSettings();
    for (const [key, value] of Object.entries(SETTINGS)) {
      if (!(key in existing)) setSetting(key, value);
    }

    if (empty('service_categories')) {
      SERVICES.forEach((s, i) =>
        run(
          'INSERT INTO service_categories (slug, name, tagline, intro, items, closing, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          s.slug, s.name, s.tagline, SERVICE_INTROS[s.slug] || null, s.items.join('\n'), s.closing, s.icon, i,
        ));
    }

    if (empty('gallery_items')) {
      LOGOS.forEach(([title, file], i) =>
        run('INSERT INTO gallery_items (album, title, image, featured, sort_order) VALUES (?, ?, ?, ?, ?)',
          'logos', title, `/media/logos/${file}.webp`, i < 17 ? 1 : 0, i));
      POSTERS.forEach(([title, file], i) =>
        run('INSERT INTO gallery_items (album, title, image, sort_order) VALUES (?, ?, ?, ?)',
          'posters', title, `/media/posters/${file}.webp`, i));
      COMPLIANCE.forEach(([title, file], i) =>
        run('INSERT INTO gallery_items (album, title, image, sort_order) VALUES (?, ?, ?, ?)',
          'compliance', title, `/media/about/${file}.webp`, i));
    }

    if (empty('projects')) {
      PROJECTS.forEach((p, i) =>
        run(
          'INSERT INTO projects (slug, title, category, summary, image, image_2, featured, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          p.image, p.title, p.category, p.summary, `/media/projects/${p.image}.webp`,
          p.image_2 ? `/media/projects/${p.image_2}.webp` : null, p.featured || 0, i,
        ));
    }

    if (empty('testimonials')) {
      TESTIMONIALS.forEach((t, i) =>
        run('INSERT INTO testimonials (name, quote, rating, sort_order) VALUES (?, ?, 5, ?)', t.name, t.quote, i));
    }

    if (empty('faqs')) {
      FAQS.forEach(([q, a], i) => run('INSERT INTO faqs (question, answer, sort_order) VALUES (?, ?, ?)', q, a, i));
    }

    if (empty('products')) {
      PRODUCTS.forEach(([name, category, description, unit], i) =>
        run(
          'INSERT INTO products (slug, name, category, description, unit, price, sort_order, published) VALUES (?, ?, ?, ?, ?, 0, ?, 0)',
          name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-$/, ''), name, category, description, unit, i,
        ));
    }

    const { ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_NAME } = process.env;
    if (ADMIN_EMAIL && ADMIN_PASSWORD && !get("SELECT id FROM users WHERE role = 'admin' LIMIT 1")) {
      run(
        "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin') ON CONFLICT(email) DO UPDATE SET role = 'admin'",
        ADMIN_NAME || 'BrandBay Admin', ADMIN_EMAIL, bcrypt.hashSync(ADMIN_PASSWORD, 12),
      );
    }
  });
  invoicing.ensureDefaults();
}

// Fills in service introductions on databases created before they existed; never overwrites edited text.
function backfillContent() {
  for (const [slug, intro] of Object.entries(SERVICE_INTROS)) {
    run("UPDATE service_categories SET intro = ? WHERE slug = ? AND (intro IS NULL OR intro = '')", intro, slug);
  }
}

module.exports = { seed, backfillContent };

if (require.main === module) {
  seed();
  console.log('Seed complete.');
}
