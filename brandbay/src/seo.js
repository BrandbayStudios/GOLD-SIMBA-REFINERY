// Search engine metadata: schema.org JSON-LD for the business and each page type.
const config = require('./config');

const abs = (p) => (p && /^https?:\/\//.test(p) ? p : `${config.baseUrl}${p || ''}`);

// The studio as a local business. Used on every page so search engines can tie pages to one entity.
function organization(settings, services = []) {
  const sameAs = ['facebook_url', 'instagram_url', 'linkedin_url'].map((k) => settings[k]).filter(Boolean);
  const org = {
    '@type': 'ProfessionalService',
    '@id': `${config.baseUrl}/#organization`,
    name: settings.site_name || 'BrandBay Innovation Studios',
    alternateName: 'BrandBay Studios',
    slogan: settings.tagline || undefined,
    description: settings.hero_text || undefined,
    url: `${config.baseUrl}/`,
    logo: abs('/brand/logo-stacked-dark.png'),
    image: abs('/brand/og-image.jpg'),
    telephone: settings.phone || undefined,
    email: settings.email || undefined,
    address: {
      '@type': 'PostalAddress',
      streetAddress: 'Cairo Road',
      addressLocality: 'Lusaka',
      addressRegion: 'Lusaka Province',
      addressCountry: 'ZM',
    },
    areaServed: { '@type': 'Country', name: 'Zambia' },
    currenciesAccepted: 'ZMW',
    paymentAccepted: 'Mobile Money, Bank transfer, Cash',
    knowsAbout: ['Branding', 'Logo design', 'Graphic design', 'Website design', 'Digital marketing', 'SEO', 'Printing', 'Business registration in Zambia'],
    sameAs: sameAs.length ? sameAs : undefined,
  };
  if (services.length) {
    org.hasOfferCatalog = {
      '@type': 'OfferCatalog',
      name: 'Services',
      itemListElement: services.map((s) => ({
        '@type': 'OfferCatalog',
        name: s.name,
        url: abs(`/services/${s.slug}`),
        itemListElement: String(s.items || '').split('\n').map((i) => i.trim()).filter(Boolean)
          .map((name) => ({ '@type': 'Offer', itemOffered: { '@type': 'Service', name } })),
      })),
    };
  }
  return org;
}

function website(settings) {
  return {
    '@type': 'WebSite',
    '@id': `${config.baseUrl}/#website`,
    url: `${config.baseUrl}/`,
    name: settings.site_name || 'BrandBay Innovation Studios',
    publisher: { '@id': `${config.baseUrl}/#organization` },
    inLanguage: 'en',
  };
}

function breadcrumbs(items) {
  return {
    '@type': 'BreadcrumbList',
    itemListElement: [['Home', '/'], ...items].map(([name, url], i) => ({
      '@type': 'ListItem', position: i + 1, name, item: abs(url),
    })),
  };
}

function service(s) {
  return {
    '@type': 'Service',
    name: s.name,
    serviceType: s.name,
    description: s.intro || s.tagline || undefined,
    url: abs(`/services/${s.slug}`),
    provider: { '@id': `${config.baseUrl}/#organization` },
    areaServed: { '@type': 'Country', name: 'Zambia' },
    hasOfferCatalog: {
      '@type': 'OfferCatalog',
      name: s.name,
      itemListElement: String(s.items || '').split('\n').map((i) => i.trim()).filter(Boolean)
        .map((name) => ({ '@type': 'Offer', itemOffered: { '@type': 'Service', name } })),
    },
  };
}

function faqPage(faqs) {
  return {
    '@type': 'FAQPage',
    mainEntity: faqs.map((f) => ({
      '@type': 'Question', name: f.question, acceptedAnswer: { '@type': 'Answer', text: f.answer },
    })),
  };
}

function project(p) {
  return {
    '@type': 'CreativeWork',
    name: p.title,
    description: p.summary || undefined,
    genre: p.category || undefined,
    image: [p.image, p.image_2].filter(Boolean).map(abs),
    url: abs(`/projects/${p.slug}`),
    creator: { '@id': `${config.baseUrl}/#organization` },
  };
}

function article(post) {
  return {
    '@type': 'BlogPosting',
    headline: post.title,
    description: post.excerpt || undefined,
    image: post.image ? abs(post.image) : abs('/brand/og-image.jpg'),
    datePublished: post.published_at ? new Date(`${post.published_at.replace(' ', 'T')}Z`).toISOString() : undefined,
    url: abs(`/blog/${post.slug}`),
    author: { '@id': `${config.baseUrl}/#organization` },
    publisher: { '@id': `${config.baseUrl}/#organization` },
  };
}

function product(p) {
  return {
    '@type': 'Product',
    name: p.name,
    description: p.description || undefined,
    category: p.category || undefined,
    image: p.image ? abs(p.image) : abs('/brand/og-image.jpg'),
    url: abs(`/store/${p.slug}`),
    brand: { '@id': `${config.baseUrl}/#organization` },
    offers: {
      '@type': 'Offer',
      price: Number(p.price).toFixed(2),
      priceCurrency: 'ZMW',
      availability: p.stock === 0 ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
      url: abs(`/store/${p.slug}`),
      seller: { '@id': `${config.baseUrl}/#organization` },
    },
  };
}

// One <script type="application/ld+json"> payload; "<" is escaped so content can never close the tag.
function graph(nodes) {
  const clean = JSON.stringify({ '@context': 'https://schema.org', '@graph': nodes.filter(Boolean) });
  return clean.replace(/</g, '\\u003c');
}

module.exports = { abs, organization, website, breadcrumbs, service, faqPage, project, article, product, graph };
