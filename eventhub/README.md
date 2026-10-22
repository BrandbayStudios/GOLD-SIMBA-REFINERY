# EventHub Zambia

A Laravel port of the EventHub Zambia event-vendor marketplace (originally a single-page
HTML/Firebase prototype). The original design — the gold/turquoise editorial system, hero
slider, marketplace, vendor profiles and modals — is preserved exactly; the Firebase layer
has been replaced with a Laravel backend (Eloquent + SQLite + session auth).

## Requirements

- PHP 8.2+ (developed on 8.4)
- Composer
- SQLite (bundled with PHP) — swap to MySQL by editing `.env`

## Setup

```bash
composer install
php artisan migrate:fresh --seed
php artisan serve
```

Then open http://127.0.0.1:8000.

Seeded demo accounts (password `password` for both):

- **Customer:** `demo@eventhub.test` — customer dashboard
- **Vendor:** `vendor@eventhub.test` — vendor dashboard (owns the Royal Gardens listing)

## What was ported

| Original (HTML / Firebase)            | Laravel equivalent                                   |
|---------------------------------------|------------------------------------------------------|
| `const vendors = {...}` static object | `vendors` + `vendor_packages` / `vendor_images` / `vendor_reviews` tables, loaded by `VendorSeeder` |
| Firebase Auth                         | Native session auth (`AuthController`, `users.role`) |
| Firestore `eventRequests`             | `quote_requests` table (`QuoteController`)           |
| Firestore `events`                    | `event_plans` table (`PlannerController`)            |
| Firestore `vendors` (applications)    | `vendors` table w/ `status = pending` (`VendorApplicationController`) |
| Inline `<style>` block                | `public/css/app.css`                                 |
| Inline `<script>` app logic           | `public/js/app.js` (UI preserved; data calls → JSON routes) |

## Ratings & reviews

- Any signed-in user can rate a vendor (1–5 stars + text) from the "Write a review" form
  in the vendor profile modal. It posts to `POST /vendors/{vendor}/reviews`
  (`ReviewController`).
- The vendor's `rating` is a denormalised **running average** over its baseline
  `reviews_count` (see `Vendor::registerRating()`), so a new rating nudges the score
  realistically instead of discarding the seeded history. The new review, the modal's
  average, and the marketplace card update live without a reload.

## Dashboards

`GET /dashboard` (auth required) routes by role:

- **Customer** (`dashboard/customer`) — their quote requests and event plans, with status
  badges and summary stats.
- **Vendor** (`dashboard/vendor`) — listing summary (status / verified), incoming quote
  requests, customer reviews, and rating stats. Shown when the account has `role = vendor`
  and an attached vendor listing.

The header "Dashboard" button links here once signed in.

## Architecture

- **Routes** — `routes/web.php`. The marketplace is one server-rendered page
  (`HomeController@index`); modal forms submit JSON to `/quotes`, `/event-plans`,
  `/vendor-application`, and auth posts to `/register`, `/login`, `/logout`.
- **Vendor profile modal** is driven by a `window.vendors` payload the controller builds
  from the database, mirroring the original front-end structure.
- **Auth gating** — quote / planner / vendor-application endpoints require login; the
  front-end opens the auth modal when a guest triggers them.
- Submitting a vendor application promotes the account to `role = vendor` and creates a
  `pending` vendor record (hidden from the marketplace until `status = approved`).

## Notes

- The original "Ask EventHub" natural-language search called a non-existent PHP endpoint;
  it is now resolved client-side against the loaded catalogue (keyword → category/location).
- WhatsApp and Facebook discovery buttons open external links in a new tab, as before.
- To use MySQL instead of SQLite, set `DB_CONNECTION=mysql` (plus host/db/credentials) in
  `.env` and re-run `php artisan migrate:fresh --seed`.
