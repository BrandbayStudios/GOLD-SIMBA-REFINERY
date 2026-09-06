# Gold SIMBA Refinery — server

A small Node.js + Express + SQLite server that serves the Gold SIMBA
Refinery site (`public/goldsimba.html`) and answers the same-origin
`/api/...` routes that page's JavaScript calls.

This one file at `public/goldsimba.html` contains all three apps —
the public marketing site (`/`), the staff dashboard (`/#admin`), and
the customer booking tracker (`/#track`) — switched by a client-side hash
router. There is nothing else to configure: the page's `fetch()` calls are
all same-origin relative paths.

## Run it

```
cd server
npm install
npm start
```

- Public site:      http://localhost:4000/
- Staff dashboard:  http://localhost:4000/#admin  (default `admin` / `ADMINGSR2026`)
- Booking tracker:  http://localhost:4000/#track

Data is stored in a SQLite database file at `server/data/goldsimba.sqlite`,
created automatically on first run. The default service catalogue (the
twelve services listed on the public site) is seeded the first time the
database is created; edit, deactivate, or delete them from the Services tab
of the staff dashboard.

## Configuration

Copy `.env.example` to `.env` and set real values, then run with:

```
node --env-file=.env server.js
```

| Variable         | Purpose                                              | Default                |
|------------------|-------------------------------------------------------|-------------------------|
| `PORT`           | HTTP port                                              | `4000`                  |
| `ADMIN_USERNAME` | Staff sign-in username                                 | `admin`                 |
| `ADMIN_PASSWORD` | Staff sign-in password                                 | `ADMINGSR2026`          |
| `TOKEN_SECRET`   | Secret used to sign staff bearer tokens (use a long random string in production) | an insecure dev default |

**Change `ADMIN_PASSWORD` and `TOKEN_SECRET` before deploying** — the
defaults are for local development only.

## API

All admin routes require `Authorization: Bearer <token>`, obtained from
`POST /api/login` and stored client-side in `sessionStorage`.

| Method | Path                              | Auth  | Purpose |
|--------|-----------------------------------|-------|---------|
| GET    | `/api/services`                   | none  | Active services, for the public booking form |
| POST   | `/api/request`                    | none  | Public visitor submits a booking request |
| GET    | `/api/lookup?ref=REFERENCE`       | none  | Customer tracker looks up a booking (restricted fields) |
| POST   | `/api/login`                      | none  | Staff sign-in, returns a bearer token |
| GET    | `/api/admin/bookings`             | staff | All bookings, every field |
| POST   | `/api/admin/bookings`             | staff | Create a booking |
| PUT    | `/api/admin/bookings/:reference`  | staff | Update a booking (partial — e.g. just `paymentStatus`) |
| DELETE | `/api/admin/bookings/:reference`  | staff | Delete a booking |
| GET    | `/api/admin/services`             | staff | All services, active and inactive |
| POST   | `/api/admin/services`             | staff | Create a service |
| PUT    | `/api/admin/services/:serviceId`  | staff | Update a service |
| DELETE | `/api/admin/services/:serviceId`  | staff | Delete a service |

The server always recomputes `balance` (`assayingFee - amountPaid`) and
`paidAt` itself — it never trusts a client-sent value for either, or for
`paymentStatus` on a public request. `GET /api/lookup` strips `notes`,
`sellerNRC`, `sellerAddress`, `bookingMadeBy`, and buyer/seller contact
details before the response is ever sent, so the customer tracker can never
see them.

## Hosting

Any small always-on Node host works: a small VPS, a PaaS (Render, Railway,
Fly.io), or a spare always-on computer behind a Cloudflare Tunnel. Point it
at `server/server.js` with `npm install && npm start`, set the environment
variables above, and keep `server/data/` on persistent storage so bookings
survive a restart/redeploy.
