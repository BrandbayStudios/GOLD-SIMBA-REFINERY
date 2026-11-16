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

### SMS (Zambia) – Africa's Talking
In **Admin → Settings → SMS – Africa's Talking**, tick *Send real SMS*, enter your username and API key (stored encrypted) and optionally an approved sender ID, then use **Send test SMS**. Use the username `sandbox` with your sandbox key to test in the Africa's Talking simulator. The settings page shows your balance, and **Admin → SMS log** records every message with its delivery status. `.env` values (`SMS_DRIVER=africastalking`, `AFRICASTALKING_*`) still work as a fallback. Another gateway can be added by implementing `App\Services\Sms\SmsDriver`.

### WhatsApp
WhatsApp uses `wa.me` click-to-chat links, so no API key is needed. The WhatsApp Business Cloud API can be added later as another notification channel in `App\Services\Notifier`.

### Maps
The app uses Leaflet with OpenStreetMap tiles and Nominatim reverse geocoding, so no API key or billing is needed. To switch to Google Maps, replace the map helpers in `resources/js/app.js`. The data model (lat/lng on requests and providers) stays the same. For high traffic, use a commercial tile provider in line with the OSM tile usage policy.

### Mobile money (MTN MoMo & Airtel Money – built in)
Enter the API keys in **Admin → Settings → Mobile money payments** (stored encrypted) and tick *Enable*.
- **MTN MoMo:** Collections product at momodeveloper.mtn.com, giving a subscription key, API user and API key. Use the sandbox (currency EUR) for testing, then production (target environment `mtnzambia`, ZMW).
- **Airtel Money:** Collections at developers.airtel.africa, giving a client ID and secret. Use UAT for testing, then production.
- Register the callback URLs shown on the settings page with each operator.
- Customers then get a PIN prompt on their phone, and the payment confirms automatically with commission. Every callback is re-verified with the operator's API before anything is marked paid. Unanswered prompts expire after 30 minutes (`php artisan payments:sync`, scheduled every minute).
- Zamtel Kwacha has no public merchant API, so it stays on manual confirmation, as do bank transfer and cash.
- After each deployment that adds database changes, click **Admin → Settings → Apply database updates**.

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

## Accounts and verification
- **Clients:** name, phone, NRC number and an address pinned on the OpenStreetMap map (email optional). The saved address can be reused on a request with one tap; emergency requests still auto-detect the current location.
- **Providers:** NRC number plus photos of the NRC front and back and a selfie holding it; PACRA certificate for businesses; a licence or certificate for each service that needs one (set per service in Admin → Services & pricing); and a payment method (MTN, Airtel, Zamtel or bank).
- **Admin review:** each document is approved or rejected with a reason (the provider is told and re-uploads). A provider can only be approved once every required document is approved, any required licences are valid, and payment details are set. Expired licences stop job matching until renewed. All files sit on the private disk and are streamed only to the owner and admins.

## Payouts ledger
Every provider has one running balance. A confirmed payment is booked once: if the customer paid **through the app** (MTN / Airtel prompt) HANDY NOW owes the provider the amount less commission (+); if the customer paid the **provider directly**, the provider owes the commission (−). **Admin → Payouts** lists the balances: *Create payout* reserves an amount (never more than is owed), you send the money to the provider's chosen account, then *Mark paid* with the transaction reference (the provider gets an SMS). Cancelling a pending payout puts the money back. *Record commission received* settles what a provider owes, and *Adjustment* handles refunds and corrections. Providers see their balance, payout history and, if they owe commission, your instructions (set in Admin → Settings) on their Earnings page. Payouts are recorded here but sent manually; sending through the MTN / Airtel disbursement APIs is a possible next step.

## Pricing rules and the booking fee
Each service (Admin → Services & pricing) is **open** (providers quote freely), **capped** (quotes may not exceed a ceiling, work plus call-out) or **fixed** (one price set by HANDY NOW; no quotes, the first eligible provider to accept gets the job). A fixed price can add a per-km rate for services that need a drop-off point (towing: road distance is the straight line × 1.3). A **booking fee** (platform default plus per-service override, 0 = off) is paid by the customer in the app once a provider is confirmed, by MTN / Airtel prompt or manually confirmed by an admin; until it is paid, the provider cannot start the job and neither side sees the other's phone number, chat is closed, and the API hides the details. It is refunded if the provider or an admin cancels (admin marks it refunded) and kept if the customer cancels; admins can waive it. Booking fees are HANDY NOW revenue: they never create a provider balance or commission. Extra costs found on site go through a customer approval (limited to a % of the price and never above a ceiling), and phone numbers and emails typed in chat are hidden.

## Forgotten passwords
- **By SMS:** *Forgot password?* on the login page texts a 6-digit code to the phone on the account (phone or email accepted). The code is stored hashed, lasts 15 minutes, works once, allows 5 wrong tries, and is limited to 1 per minute and 3 per hour per account. The answer is identical whether or not the account exists, and the code is never written to the SMS log. A successful reset signs out every other browser and revokes mobile-app tokens, and a security text is sent. While SMS is not switched on in production, the page sends users to support instead.
- **By an admin:** *Reset password* on Admin → Users (or a provider page) issues a one-time temporary password, shown once (optionally also texted). The user must choose their own password before using anything else.
- **Mobile API:** `POST /api/v1/auth/forgot-password` and `/auth/reset-password`.

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
