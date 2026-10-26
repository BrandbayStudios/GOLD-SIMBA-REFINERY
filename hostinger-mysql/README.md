# Gold SIMBA Refinery — booking system server

A small, self-contained Node.js server that runs the whole Gold SIMBA
booking system: the public marketing site, the "Book Now" form, the staff
admin dashboard, and the customer booking tracker. It replaces the earlier
Google Apps Script and Firebase attempts with one always-on process — no
third-party console, no config file to paste values into, no CORS setup.

The page itself (`public/goldsimba.html`) is served by this same server,
so every API call it makes is a same-origin relative path like
`fetch('/api/services')`. There is nothing to configure in the HTML file —
`npm install && npm start` is the whole story.

Data is stored in MySQL. The server talks to it with `mysql2` (the
standard, widely-used MySQL/MariaDB driver for Node), so it works against
any MySQL or MariaDB host equally well — this guide is written for
[Hostinger](https://www.hostinger.com)'s Business/Cloud hosting plans,
since that's where this app is deployed, but nothing in the code is
Hostinger-specific: it would run the same way on any other host that
gives you a MySQL database and a place to run a persistent Node.js
process.

## Run it locally

You need a MySQL/MariaDB database and these environment variables before
the server will start:

| Variable      | Required? | Default     | Notes                                  |
|---------------|-----------|-------------|-----------------------------------------|
| `DB_HOST`     | no        | `localhost` | MySQL server host                       |
| `DB_PORT`     | no        | `3306`      | MySQL server port                       |
| `DB_USER`     | **yes**   | —           | Database username                       |
| `DB_PASSWORD` | **yes**   | —           | Database password                       |
| `DB_NAME`     | **yes**   | —           | Database name                           |

If `DB_USER`, `DB_PASSWORD`, or `DB_NAME` are missing, the server prints
exactly which one(s) and refuses to start, rather than failing later with
a confusing database error.

```
cd server
npm install
export DB_HOST=localhost
export DB_USER=your_db_user
export DB_PASSWORD=your_db_password
export DB_NAME=your_db_name
npm start
```

Then open `http://localhost:3000/` (public site), `http://localhost:3000/#admin`
(staff dashboard), or `http://localhost:3000/#track` (customer tracker).

The first time the server connects to a fresh database it creates its
tables and seeds the 7 default testing services automatically — nothing
to run by hand.

The default staff login is:

- Username: `admin`
- Password: `ADMINGSR2026`

This is intentionally simple, per an explicit request — see **Changing the
admin password** below before this is handling real customer data on the
public internet.

The port defaults to `3000`; set `PORT` to use a different one (see the
Hostinger app-setup notes below about whether you need to set this
yourself).

## Deploying on Hostinger

This covers Hostinger's **Business Web Hosting** or any **Cloud** plan
(Startup and up) — these support both a persistent Node.js app and a
MySQL database on the same account, so the whole system lives on one
host with nothing else to sign up for. (A VPS plan also works, but that's
a manual CLI/CloudPanel setup rather than the hPanel flow below.)

### 1. Database setup

In hPanel, on your website's dashboard:

1. Go to **Databases → Management** and create a new MySQL database and
   database user through the forms there.
2. Hostinger automatically prefixes both the database name and the
   username with your hosting account's ID — so if you type `goldsimba`
   and `goldsimba_user`, what you'll actually get (and what hPanel shows
   you afterwards) looks like `u123456789_goldsimba` and
   `u123456789_goldsimba_user`. Use the *exact* prefixed names hPanel
   shows you, not the names you typed. Passwords need 8+ characters with
   upper/lowercase and a number.
3. Make a note of the database name, username, and password.

Those map directly onto the environment variables the server needs:

- `DB_HOST` — `localhost` (the app and database run on the same account
  — this is different from Hostinger's separate "Remote MySQL" feature,
  which is only for connecting from *outside* Hostinger)
- `DB_PORT` — `3306` (MySQL's standard port — this is what's actually
  listening on `localhost`, so leave it as-is)
- `DB_USER` — the prefixed username, e.g. `u123456789_goldsimba_user`
- `DB_PASSWORD` — the password you set
- `DB_NAME` — the prefixed database name, e.g. `u123456789_goldsimba`

No SSL configuration is needed here — unlike a remote host, the app and
database are on the same Hostinger account/network.

### 2. App setup

In hPanel:

1. Go to **Websites → Add Website → Deploy Web App**.
2. Choose a deploy method: upload this `server/` folder as a **.zip**
   (simplest for a one-off deploy — zip up `server.js`, `package.json`,
   `package-lock.json`, and the `public/` folder; you can leave
   `node_modules` out, since Hostinger installs dependencies from
   `package.json` itself), connect a **GitHub** repo (auto-redeploys on
   every push — worth switching to later, once this is live), or the
   **Hostinger Connector** if you're deploying straight from VS Code/Cursor.
3. hPanel tries to auto-detect the framework; if it doesn't recognize
   this as a plain Express app, choose "Other" and set the **entry
   file** to `server.js` explicitly.
4. Pick a Node.js version — **18, 20, 22, or 24** are supported (22 is
   the current default; anything older than 18 isn't).
5. On the **environment variables** step, either upload a `.env` file
   with the values below or add them one at a time:
   - `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` (from step 1 — leave
     `DB_PORT` unset, it defaults to `3306`)
   - `ADMIN_PASSWORD` — your chosen staff password (see below)
   - `SESSION_SECRET` — a fixed random string (see below)
6. Deploy. hPanel runs the install/build step for you (capped at 15
   minutes, which is more than enough for this app's two dependencies).
   A server app like this one gets a **Restart** control on its app
   dashboard in hPanel — use that any time you change an environment
   variable, since they're only read at startup.

**About `PORT`:** Hostinger's own docs don't spell out exactly how they
wire up the port for your app, but a **Restart** control existing at all
implies they're managing the running process for you (most platforms
like this auto-inject a `PORT` variable and expect the app to bind to
it) — either way, this server already does the right thing
(`process.env.PORT || 3000`), so there's nothing to configure here.
What's genuinely undocumented by Hostinger (and worth confirming with
their support if you hit an issue, rather than assuming): whether a
crashed app restarts itself automatically, and whether there's any
request/idle timeout.

Once deployed, hPanel shows a working URL for the app immediately (an
auto-generated one on Hostinger's own domain) — the same site serves the
public pages, `#admin`, and `#track`, exactly as when run locally.

### 3. Connect your domain

From the app's dashboard in hPanel, click **Connect domain**, enter your
domain or subdomain, and follow whatever DNS instructions it gives you
(pointing the domain's DNS at Hostinger, if it isn't already). This can
take up to 24 hours to propagate. SSL is issued and installed
automatically once the domain resolves — no separate certificate step.

## Changing the admin password

Set environment variables before starting the server, then restart it:

```
ADMIN_USERNAME=admin ADMIN_PASSWORD=your-new-password npm start
```

On Hostinger, set `ADMIN_USERNAME`/`ADMIN_PASSWORD` in the Node.js app's
environment-variables panel in hPanel, then use hPanel's **Restart**
control for the app — environment variables are only read once, at
startup.

If `ADMIN_USERNAME`/`ADMIN_PASSWORD` aren't set, the server falls back to
`admin` / `ADMINGSR2026`. There's no database table or file to edit —
changing the login is purely an environment variable + restart.

It's also worth setting `SESSION_SECRET` to a fixed random string (any
long random text) once you deploy somewhere permanent. If it's not set,
the server generates a random one every time it starts, which means every
staff member gets signed out whenever the server restarts (e.g. after a
redeploy). Setting it keeps sign-ins valid across restarts:

```
export SESSION_SECRET=$(openssl rand -hex 32)
```

## Backing up your data

The database lives in Hostinger's MySQL, not on disk next to this server
— there's no `data.db` file to copy. hPanel's **Databases** section
normally offers a phpMyAdmin link for your database, from which you can
export a SQL dump at any time; check hPanel for whether your plan also
includes automatic backups, and how far back they go.

## Troubleshooting

- **"Missing required database environment variable(s)" on startup** —
  set `DB_USER`, `DB_PASSWORD`, and `DB_NAME` (and `DB_HOST`/`DB_PORT` if
  they differ from the defaults) to match the database you created in
  hPanel; see "Database setup" above.
- **Connection errors talking to MySQL** — double-check you copied the
  *prefixed* database name and username exactly as hPanel shows them
  (e.g. `u123456789_goldsimba`, not just `goldsimba`), and that `DB_HOST`
  is set correctly (usually `localhost` on Hostinger).
- **Staff keep getting signed out** — set `SESSION_SECRET` to a fixed
  value (see above); without it, every restart invalidates all sessions.
- **App doesn't come up on the port you expect** — see the note on
  `PORT` in the Hostinger app-setup section above; check hPanel's own
  Node.js documentation for how it wires up the port on your plan.
- **Wrong admin password after setting `ADMIN_PASSWORD`** — make sure the
  app was actually restarted (hPanel's **Restart** control) after the
  environment variable was set; it's only read once, at startup.
