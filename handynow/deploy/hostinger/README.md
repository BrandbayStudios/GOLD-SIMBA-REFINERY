# Deploying HANDY NOW to Hostinger

This works on any Hostinger **web hosting** plan that offers PHP 8.3 and MySQL (Premium, Business, Cloud). You don't need SSH or Composer: the upload package already contains everything, and a one-time web installer sets up the database.

## 1. Build the package (or use the one you were given)

```bash
cd handynow
deploy/hostinger/build.sh yourdomain.co.zm
```
This produces `deploy/dist/handynow_YYYYMMDD_HHMMSS.zip` (about 18 MB). It contains the app, production `vendor/`, compiled CSS/JS, a root `.htaccess`, and `.env.hostinger.example`.

## 2. In hPanel

1. **PHP version:** Websites → Manage → Advanced → **PHP Configuration** → choose **PHP 8.3** (Laravel 13 requires it).
2. **Database:** Databases → **MySQL Databases** → create a database and user. Hostinger adds a prefix such as `u123456789_`. Note the full database name, the user and the password.
3. **SSL:** Security → SSL → make sure the free SSL certificate is active. The site forces HTTPS.

## 3. Upload

1. Files → **File Manager** → open `public_html`. Delete Hostinger's default `default.php` / `index.php` placeholder if one is there.
2. Upload the zip into `public_html`, right-click → **Extract** into `public_html` itself, not a sub-folder. Then delete the zip.
3. In File Manager, enable **Show hidden files** if needed. Rename `.env.hostinger.example` to **`.env`** and edit it:
   - `APP_KEY`: paste a key generated with `php -r "echo 'base64:'.base64_encode(random_bytes(32));"`. Alternatively, `build.sh yourdomain --with-env` writes a complete `.env` with the key for you.
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: from step 2. Keep `DB_HOST=localhost`.
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_PHONE`: your first administrator login.
   - `INSTALL_TOKEN`: any random string of 16+ characters.
   - `SEED_DEMO_DATA`: `true` to show the purple-badged demo records, `false` for a clean site.

## 4. Install

Open **`https://yourdomain.co.zm/install?token=YOUR_INSTALL_TOKEN`**. The installer checks PHP, the database connection and folder permissions. Then click **Install HANDY NOW**. It creates all tables, loads the service catalogue and pricing, creates your admin account, and links uploaded-photo storage.

Then **delete the `INSTALL_TOKEN` line from `.env`**. The installer also locks itself (`storage/app/installed.lock`).

Log in at `/login` with your admin email or phone.

## 5. After going live
- Admin → Settings: set your real support phone, WhatsApp and email, and the commission rate. Remove demo data when you're ready.
- Real SMS: set `SMS_DRIVER=africastalking` and your `AFRICASTALKING_*` keys in `.env`.
- Updating: build a new zip and extract it over `public_html`. Your `.env` and `storage/` uploads are kept, because the zip contains neither a `.env` nor any uploads. If an update adds database changes, run `php artisan migrate --force` over SSH. Without SSH, delete `storage/app/installed.lock`, set a new `INSTALL_TOKEN`, and run the installer again. It only applies new migrations and never duplicates data (set `SEED_DEMO_DATA=false` first if you have removed the demo records).

## How it's laid out
The whole Laravel app sits in `public_html/`, and the root `.htaccess` routes every request into `public/`. Because of this, `.env`, `vendor/`, `storage/` and the source code can never be downloaded. This was tested on Apache with MySQL: `/.env` returns 403 and `/vendor/...` returns 404.

If you have SSH (Business/Cloud), you can use the more conventional layout instead. Upload the app one level up, for example to `~/domains/yourdomain/handynow`, and replace `public_html` with a symlink to `handynow/public`.

## Troubleshooting
| Symptom | Fix |
|---|---|
| "The site is being set up" | The installer hasn't run yet. Go to `/install?token=…`. |
| 500 error | Check that PHP is 8.3 and `.env` values are correct. See `storage/logs/laravel-*.log`. Set `APP_DEBUG=true` briefly to see the error. |
| `/install` shows 404 | `INSTALL_TOKEN` is missing or shorter than 16 characters, the token in the URL doesn't match, or the site is already installed. |
| Uploaded photos don't show | `public/storage` symlink missing. Over SSH, run `php artisan storage:link`. |
| Styles missing | Make sure `public/build/` was extracted. |
