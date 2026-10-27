# BrandBay Innovation Studios — web app

A full web app version of [brandbaystudios.online](https://brandbaystudios.online), replacing the Hostinger Website Builder site. It keeps all of the existing content, services, logofolio, poster designs, projects, testimonials, FAQs and compliance certificates, and adds the business tools behind them.

## What's included

**Public website**: Home, About, Services (with a page per service), Projects (filterable case studies), Gallery (logofolio and posters with a lightbox), Store, Contact (FAQ and map), Terms & Conditions, Insights/blog, sitemap.xml and robots.txt. A WhatsApp button, newsletter signup and SEO metadata appear on every page.

**Service applications**: the "Apply for Service" form takes budget, timeline, a brief and an optional file upload. Each request gets a tracking reference (e.g. `BB-2026-0001`) and triggers email notifications to the studio and the client.

**Client portal** (`/account`): clients register with the email they applied with, and their earlier requests and orders are linked automatically. From the portal they can:
- see each request's status
- view a quote with its 70% deposit / 30% balance split
- accept a quote with one click
- message the team in a thread on each request
- track store orders
- manage their profile and password, including a forgot-password flow

**Store**: products with categories, a cart and checkout (collection or delivery). Payment is offline, by Mobile Money, bank transfer or cash; you mark orders as paid in the admin. Stock is tracked, and cancelling an order returns its items to stock.

**Admin dashboard** (`/admin`):
- KPIs, the request pipeline and the most requested services
- Service requests: search, filter, quote, status, internal notes, client messaging, brief download and CSV export
- Orders: status and payment tracking
- Contact messages and newsletter subscribers (with CSV export)
- A content editor for services, projects, gallery, testimonials, FAQs, blog posts and products, with image uploads
- Users and roles (client / staff / admin) and site settings (contact details, socials, hero text, payment instructions, T&Cs)

**Invoicing** (*Admin → Invoices & quotes*):
- Invoices, quotations, proforma invoices and receipts, numbered automatically (`INV-2026-0001`, `QUO-…`, `PRO-…`, `REC-…`).
- **All BrandBay services and sub-services** in a price list (*Admin → Service prices*) that fills in prices when you add a service. Custom lines are allowed too.
- **Tax types:** VAT 16%, zero-rated, exempt, Turnover Tax, No tax and Withholding Tax (deducted from the amount payable). Taxes are chosen per line and are editable in *Admin → Taxes, currency & banks*. Rates are copied onto each document, so later changes never alter issued invoices. Confirm current rates with ZRA.
- **Currencies:** ZMW, USD, ZAR, GBP and EUR, and you can add more. An optional exchange rate shows the Kwacha equivalent.
- **Banking details:** bank accounts and MTN, Airtel and Zamtel Mobile Money numbers, chosen per document. Accounts without a number are never printed.
- **Workflow:** create a quotation or invoice straight from a service request (client and price pre-filled), convert a quotation into an invoice or proforma, record part or full payments (status becomes Partially paid, Paid or Overdue), issue a receipt, duplicate, cancel (VOID) and export CSV.
- **Delivery:** an A4 print/PDF layout, emailing to the client with a secure link, and a client view. Documents appear in the client's dashboard once sent. Drafts are never shown to clients.

**Security**: hashed passwords (bcrypt), CSRF protection on every form, rate limiting on forms and login, security headers including a CSP, private storage for client files, honeypot spam traps, and SQLite-backed sessions.

## Running it

Requires **Node.js 22.13 or newer** (it uses the built-in `node:sqlite`, so there is no database server to install).

```bash
cd brandbay
npm install
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD=a-strong-password npm start
# open http://localhost:3000, then sign in at /login
```

On first start the database is created in `data/` and filled with BrandBay's current website content. You can also create or reset an admin at any time:

```bash
npm run create-admin -- you@example.com "a-strong-password" "Your Name"
```

Run the tests with `npm test`.

## Before going live

1. **Set up invoicing.** In *Admin → Taxes, currency & banks*, enter your bank and Mobile Money numbers and your TPIN. In *Admin → Service prices*, set your standard prices (they start at 0).
1. **Set prices for the store.** Store items are seeded as unpublished drafts without prices because the current site doesn't list any. Open *Admin → Products*, set a price and tick *Published*.
2. **Fill in the settings.** In *Admin → Settings*, add your Facebook, Instagram and LinkedIn links and your actual payment details (Mobile Money number, bank account) in *Payment instructions*.
3. **Configure email.** Set the `SMTP_*` variables (see `.env.example`); Hostinger email works. Until then, notifications are only written to the server log.

## Deploying

Any host that runs Node 22 works: Hostinger (Business or VPS plans with Node.js), Render, Railway, Fly.io or a VPS. Set the variables from `.env.example`, and make sure `data/` is on **persistent storage** and backed up, because it holds the database and uploads.

With Docker:

```bash
docker build -t brandbay .
docker run -d -p 3000:3000 -v brandbay-data:/app/data --env-file .env brandbay
```

Then point `brandbaystudios.online` at the server (DNS A record, or the host's custom-domain setting) and put HTTPS in front. Most hosts do HTTPS automatically. With HTTPS in place, cookies are marked secure when `NODE_ENV=production`.

## Project layout

```
server.js               entry point (seeds an empty database, starts the server)
src/app.js              Express setup, security headers, sessions
src/db.js               SQLite schema and helpers
src/routes/             public, auth, account (client portal), shop, admin
views/                  EJS templates (public pages, store, account, admin)
public/                 CSS, JS and the studio's portfolio images
public/brand/           logo files (gold and dark; stacked, horizontal and icon), favicons and the social-share image
scripts/seed.js         BrandBay's existing website content
tests/                  end-to-end tests (node:test)
```
