# Deploying EventHub to Hostinger

These steps target Hostinger **shared / cloud web hosting** (hPanel) on a plan with
SSH access (Premium, Business or Cloud). On a Hostinger **VPS**, use a normal
Nginx/Apache + PHP-FPM setup instead, pointing the web root at `eventhub/public`.

## 1. Prepare hPanel

1. **PHP version** — hPanel → *Advanced → PHP Configuration* → choose **PHP 8.4**.
   `composer.lock` pins Symfony 8 packages, which need PHP ≥ 8.4.1; on 8.3
   `composer install` will fail.
2. **Database** — hPanel → *Databases → Management* → create a MySQL database and
   user. Note the full names (they look like `u123456789_eventhub`) and the password.
3. **SSH** — hPanel → *Advanced → SSH Access* → enable it and note the host, port
   (usually `65002`) and username.

## 2. Get the code onto the server

SSH in, then clone the repository **outside** `public_html`:

```bash
ssh -p 65002 u123456789@your-server-ip
cd ~/domains/yourdomain.com
git clone https://github.com/brandbaystudios/gold-simba-refinery.git app
```

(Private repo: add a deploy key from hPanel → *Advanced → Git*, or use a GitHub
personal access token in the clone URL.)

No SSH? Zip the `eventhub` folder, upload it with *File Manager* into
`public_html` and extract it so `public_html/artisan` exists. The included
`eventhub/.htaccess` forwards every request to `public/` so `.env` and the source
are never exposed. You will still need a way to run `composer install` and the
artisan commands (SSH is strongly recommended).

## 3. Configure the app

```bash
cd ~/domains/yourdomain.com/app/eventhub
cp .env.hostinger.example .env
nano .env        # set APP_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```

Leave `APP_KEY` empty — the deploy script generates it. Keep `APP_DEBUG=false`.

## 4. Install, migrate and cache

```bash
bash deploy/hostinger.sh
```

This runs `composer install --no-dev`, generates the app key, runs migrations,
links storage, caches config/routes/views and fixes folder permissions.

First deploy only — load the vendor listings:

```bash
php artisan db:seed --class=VendorSeeder --force
```

Don't run the full `db:seed` in production: `DatabaseSeeder` also creates the demo
logins `demo@eventhub.test` / `vendor@eventhub.test` with password `password`.

## 5. Point the domain at `public/`

Replace `public_html` with a link to Laravel's `public` folder:

```bash
cd ~/domains/yourdomain.com
mv public_html public_html.bak
ln -s ~/domains/yourdomain.com/app/eventhub/public public_html
```

Open `https://yourdomain.com` — the marketplace home page should load.
Enable SSL in hPanel → *Security → SSL* if it isn't on already.

## Updating later

```bash
cd ~/domains/yourdomain.com/app
git pull
cd eventhub && bash deploy/hostinger.sh
```

## Troubleshooting

- **500 error** — check `storage/logs/laravel.log`. Usual causes: wrong DB
  credentials in `.env`, or PHP below 8.4.
- **Changed `.env` but nothing happened** — run `php artisan config:cache` again.
- **Permission denied writing logs/sessions** — `chmod -R 775 storage bootstrap/cache`.
- **Emails** — `MAIL_MAILER=log` only writes mail to the log. To send real mail, set
  `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.hostinger.com`, `MAIL_PORT=465`,
  `MAIL_SCHEME=smtps` and a Hostinger mailbox's address/password.
