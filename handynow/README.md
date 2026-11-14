# HANDY NOW – Emergency Services. One Call Away.

An on-demand emergency services marketplace for Zambia. Customers (with or without an account) request help, share their GPS location and get quotations in Kwacha from nearby verified providers. They then accept a quote, track the provider's arrival and pay. Providers get verified, go online, quote, update job status and confirm payments. Administrators verify providers, monitor requests, resolve disputes, manage pricing and see revenue and commission.

**Stack:** Laravel 13 (PHP 8.3) · MySQL (SQLite for local dev) · Blade + Tailwind CSS 4 + Alpine.js · Leaflet/OpenStreetMap · Laravel Sanctum (mobile API).

---

## Quick start (local)

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed       # catalogue + admin (+ demo data when APP_ENV=local)
php artisan storage:link
php artisan serve
```

Open http://localhost:8000.

| Role | Login | Password |
|---|---|---|
| Admin | admin@handynow.co.zm | `Admin@12345` (local only; set `ADMIN_PASSWORD` in production) |
| Customer (demo) | customer@demo.handynow.co.zm | `Password123` |
| Provider (demo) | provider1@demo.handynow.co.zm … provider9@ | `Password123` |
| Provider awaiting verification (demo) | pending@demo.handynow.co.zm | `Password123` |

Run the test suite with `php artisan test`. It covers the full request → quote → job → payment → commission → rating flow, matching, access control, registration and verification, the mobile API, and renders every page for every role.

### Demonstration data
Every demo record has `is_demo = true` and shows a purple **Demo** badge in all three dashboards. **Admin → Settings → Remove all demo data** deletes only those records. Demo data is seeded automatically only when `APP_ENV=local`. Set `SEED_DEMO_DATA=true` to seed it in other environments.

---

## Production deployment (MySQL)

1. Create a MySQL database and set `DB_CONNECTION=mysql` and the `DB_*` values in `.env`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
3. Run:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
4. Point the web root at `public/`. Compiled CSS/JS are committed in `public/build`, so Node is not required on the server.

---

## Features by priority

**Priority 1 – complete**
- Homepage with hero, search and location, emergency CTA, trust stats, categories, how-it-works, featured verified professionals (with live distance from you) and a full footer.
- Service directory: 8 categories and 55 services, each with indicative ZMW price ranges and call-out fees.
- Customer registration and login (email **or** phone), profile and password management.
- Provider registration with NRC / PACRA details and document upload. Documents are stored on the **private** disk and only the provider and admins can view them.
- Emergency request form: category → specific problem, description, up to 5 photos, GPS "use my location" or map pin, address and directions, priority. No account needed.
- Admin dashboard with all requested KPIs, 14-day charts, live request monitor, verification queue and complaints.
- Provider job management: new alerts, quoting, decline, the full status workflow, and withdrawing from a job.

**Priority 2 – complete**
- **Matching** (`app/Services/ProviderMatcher.php`): approved, online, active providers in the right category, within both the provider's own radius and the platform radius, sorted by distance then rating. Requests with no GPS pin are matched by operating-area names. When a provider goes online, they are matched to open requests. Admins can re-run matching or assign a provider directly.
- **GPS & maps:** browser geolocation, draggable pin, reverse-geocoded address, provider markers, an admin live map, and live arrival tracking. While a job is accepted or en route, the provider's phone shares its location every 15 seconds. The customer's map polls and shows distance and ETA.
- **Quotations:** work amount + call-out fee + ETA + message. The customer compares quotes and accepts or declines. The other quotes are auto-rejected.
- **Notifications:** in-app (bell with live toast; providers vibrate on emergencies), SMS via a pluggable gateway, WhatsApp click-to-chat (customer ↔ provider, support), and in-job chat.
- **Job tracking:** Pending → Searching for Provider → Provider Assigned → Accepted → Provider En Route → Arrived → In Progress → Completed / Cancelled. Every change is in an audit log.

**Priority 3 – foundations in place**
- **Payments:** MTN MoMo, Airtel Money, Zamtel Kwacha, bank transfer and cash. In this MVP the customer records the payment and the provider or an admin confirms it manually. `PaymentGateway` (`app/Services/Payments`) is the interface for adding live MoMo/Airtel collection.
- **Automated commission:** calculated when a payment is confirmed, using the rate set in Admin → Settings. It is stored per payment and per job, and shown on provider earnings and admin reports.
- **Analytics:** a reports page with a date range, a breakdown by category / payment method / top providers, and CSV export.
- Not built yet: loyalty and promotions.

---

## Integrations

### SMS (Zambia)
`SMS_DRIVER=log` (the default) records every message in **Admin → SMS log** without sending it. To send real SMS through [Africa's Talking](https://africastalking.com) (supports MTN, Airtel and Zamtel Zambia):
```
SMS_DRIVER=africastalking
AFRICASTALKING_USERNAME=your_username
AFRICASTALKING_API_KEY=your_key
AFRICASTALKING_SENDER_ID=HANDYNOW   # optional, must be registered
```
To use another gateway (e.g. Zamtel bulk SMS, Probase), implement `App\Services\Sms\SmsDriver` and bind it in `AppServiceProvider`.

### WhatsApp
WhatsApp uses `wa.me` click-to-chat links, so no API key is needed. The WhatsApp Business Cloud API can be added later as another notification channel in `App\Services\Notifier`.

### Maps
The app uses Leaflet with OpenStreetMap tiles and Nominatim reverse geocoding, so no API key or billing is needed. To switch to Google Maps, replace the map helpers in `resources/js/app.js`. The data model (lat/lng on requests and providers) stays the same. For high traffic, use a commercial tile provider in line with the OSM tile usage policy.

### Mobile money
Implement `App\Services\Payments\PaymentGateway::initiate()`, for example as an MTN MoMo Collections request-to-pay. Add a webhook route that calls `RequestWorkflow::confirmPayment()`, then bind your gateway in `AppServiceProvider`.

---

## Mobile app API (`/api/v1`)
Token auth through Sanctum. Send `Authorization: Bearer {token}`.

| Method | Endpoint | Who |
|---|---|---|
| POST | `/auth/register`, `/auth/login` (`login` = email or phone) | public |
| GET | `/categories` | public |
| GET/POST | `/auth/me`, `/auth/logout`, `/notifications` | any |
| GET/POST | `/requests`, `/requests/{ref}`, `/requests/{ref}/live` | customer |
| POST | `/requests/{ref}/quotes/{id}/accept\|reject`, `/cancel`, `/pay`, `/review` | customer |
| GET | `/provider/jobs/available`, `/provider/jobs` | provider |
| POST | `/provider/jobs/{ref}/quote`, `/provider/jobs/{ref}/status`, `/provider/availability`, `/provider/location` | provider |

The web UI and the API both call `App\Services\RequestWorkflow`, so business rules, notifications and audit logs behave the same on every client.

## Security
- Passwords are hashed with bcrypt. Login, registration and request forms are rate-limited.
- Role middleware (`role:customer|provider|admin`) protects every dashboard and API group. Ownership is checked on every request, quote and payment.
- Suspended accounts are signed out on their next request, and their API tokens are revoked.
- Guest tracking uses a 32-character secret token from the SMS link, or the reference plus phone-number lookup.
- CSRF protection on all forms. All input goes through Laravel validation. Identity documents are never publicly accessible.

## Key files
- `app/Services/RequestWorkflow.php` – every state change (create, match, quote, accept, status, cancel, pay, confirm, review)
- `app/Services/ProviderMatcher.php`, `app/Services/Geo.php` – proximity matching and ETA
- `app/Services/Notifier.php`, `app/Services/Sms/*` – in-app and SMS notifications
- `database/seeders/CategorySeeder.php` – the service catalogue and default pricing
- `resources/views/` – Blade views (`pages`, `services`, `requests`, `customer`, `provider`, `admin`)
- `resources/js/app.js` – maps, geolocation, live tracking, chat, notification polling
