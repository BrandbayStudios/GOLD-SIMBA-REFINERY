# EventHub API — Backend

Laravel REST API for **EventHub Zambia**, the event services marketplace whose
static HTML/CSS/JS frontend lives in the repository root. Built to the MVP
brief's technology choice (Laravel/PHP, MySQL, REST API-ready for a future
mobile app) using Laravel Sanctum for token auth.

## Quick start

```bash
cd backend
composer install
cp .env.example .env      # already defaults to sqlite — see "Database" below
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

The frontend (`../index.html` etc.) expects the API at `http://localhost:8000/api/v1`
by default. Open the frontend with any static file server (e.g. `python3 -m
http.server 8811` from the repo root) — CORS is wide open on `api/*` so the
two can run on different ports. If your API runs somewhere else, set
`window.EH_API_BASE` before `assets/js/api.js` loads on any page, e.g.:

```html
<script>window.EH_API_BASE = 'https://api.eventhub.zm/api/v1';</script>
<script src="assets/js/api.js"></script>
```

## Database

Defaults to **SQLite** (`database/database.sqlite`) for a zero-config local
setup. For production, per the brief, switch to MySQL by editing `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eventhub
DB_USERNAME=root
DB_PASSWORD=
```

All queries (including the haversine "near me" search) were written to work
on both drivers — no SQLite-only SQL.

## Seeded demo accounts

`php artisan migrate --seed` creates 18 categories, 7 Zambian cities, 14
vendors (12 approved + 2 pending, matching the names/prices already in the
static frontend demo), and:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@eventhub.zm` | `password` |
| Vendor (Silverleaf Gardens) | `hello@silverleafgardens.zm` | `password` |
| Customer | `chileshe@example.test` | `password` |

(Every other seeded vendor/customer also uses the password `password`.)

## Auth

Sanctum personal-access tokens (bearer tokens), not cookie/SPA auth — the
frontend is a separate static origin. Register/login return a `token`; send
it as `Authorization: Bearer <token>` on subsequent requests. Roles are
`customer`, `vendor`, `admin`, enforced by the `role:` route middleware.

## API reference

Base path: `/api/v1`. JSON in, JSON out.

### Public

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/categories` | 18 categories with live vendor counts |
| GET | `/locations` | Seeded Zambian cities |
| GET | `/vendors` | Filters: `q`, `category` (slug), `city`, `min_price`, `max_price`, `min_rating`, `source` (`platform`\|`google_places`), `featured_only`, `lat`+`lng`+`radius_km` (near-me), `sort` (`rating`\|`price_low`\|`price_high`\|`name`), `page`, `per_page` |
| GET | `/vendors/{slugOrId}` | Full profile incl. portfolio, packages, published reviews |
| GET | `/vendors/{vendor}/reviews` | Paginated |
| POST | `/vendors/{vendor}/enquiries` | Guest-allowed (`guest_name`+`guest_phone` required if not logged in) |
| POST | `/auth/register` | Customer signup |
| POST | `/auth/register-vendor` | Creates a `vendor`-role user + a `pending` `VendorProfile` in one call |
| POST | `/auth/login` | Any role |

### Authenticated (any logged-in user)

| Method | Endpoint |
|---|---|
| POST | `/auth/logout` |
| GET | `/auth/me` |
| POST | `/vendors/{vendor}/reviews` |
| POST | `/vendors/{vendor}/favourite` (toggle) |
| GET | `/favourites` |
| GET/POST | `/events`, `POST /events/{event}/vendors/{vendor}` ("save vendor to my event") |

### Vendor dashboard (`role:vendor`, prefix `/vendor`)

`stats`, `profile` (GET/PUT), `portfolio` (GET/POST/DELETE), `packages`
(GET/POST/PUT/DELETE), `enquiries` (GET, `PATCH {id}` status), `bookings`
(GET, `PATCH {id}` status).

### Admin panel (`role:admin`, prefix `/admin`)

`stats`, `vendors` (GET, `GET {id}`, `PATCH {id}/status` — the
approve/reject step from the brief's Phase 1 flow, `DELETE {id}`),
`customers` (GET, `DELETE {id}`), `categories` (full CRUD), `locations`
(GET/POST/DELETE), `claims` (GET, `PATCH {id}` — approving a claim reassigns
the `VendorProfile.user_id` to the claimant and flips `source` to
`platform`), `reviews` (GET, `PATCH {id}` moderate, `DELETE {id}`),
`enquiries` (GET), `bookings` (GET), `featured` (GET, `POST {vendor}`,
`DELETE {vendor}`), `advertisements` (full CRUD), `reports` (monthly new
vendors, enquiry→booking conversion, top categories by bookings).

## Frontend integration

`assets/js/api.js` (repo root) is a thin fetch wrapper (`window.EH_API`)
consumed by `login.html`, `register.html`, `vendor-register.html`,
`vendors.html`, `vendor-profile.html`, `vendor-dashboard.html` and
`admin-dashboard.html`. Every one of those falls back to its bundled static
demo content if the API is unreachable — open any page without running the
backend at all and it still works as a demo, exactly as before this backend
existed.

`vendor-dashboard.html` and `admin-dashboard.html` specifically: they're
browsable without logging in (sample data + a "Demo mode" banner). Log in as
the seeded vendor or admin account above and they swap to live data —
approving/rejecting a pending vendor in the admin Approvals tab is a real
`PATCH /admin/vendors/{id}/status` call.

## What's intentionally out of scope for this pass

- File uploads: portfolio/logo/cover fields accept URLs (matching the
  frontend's "paste a link" style forms); wiring actual multipart uploads to
  cloud storage (S3-compatible, per the brief) is straightforward to add via
  `Illuminate\Http\UploadedFile` + `Storage::disk('s3')` but wasn't built out.
- Google Maps/Places integration for external business discovery — the data
  model supports it (`VendorProfile.source = 'google_places'`,
  `business_claims` table) but no live Places API calls are made.
- Vendor calendar/availability persistence (the dashboard's calendar UI is
  still demo-only client state).
- WhatsApp/SMS delivery of enquiries — enquiries are stored and shown in the
  vendor dashboard; no outbound notification is sent.

## Tests

None yet beyond the Laravel skeleton's default example tests. The
`storage/logs/laravel.log` is the first place to look if an endpoint 500s.
