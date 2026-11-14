#!/usr/bin/env bash
# Builds a Hostinger-ready archive: extract it into public_html/ (hPanel File Manager or the Hostinger deploy API).
# Usage: deploy/hostinger/build.sh [domain] [--with-env]
#   --with-env  also writes a ready .env (fresh APP_KEY + INSTALL_TOKEN; DB values from DB_DATABASE/DB_USERNAME/DB_PASSWORD
#               and ADMIN_EMAIL/ADMIN_PASSWORD/ADMIN_PHONE/SEED_DEMO_DATA environment variables if set, else placeholders).
set -euo pipefail
cd "$(dirname "$0")/../.."
DOMAIN="${1:-yourdomain.com}"
WITH_ENV="${2:-}"
STAMP=$(date +%Y%m%d_%H%M%S)
OUT="${OUT_DIR:-$PWD/deploy/dist}"
STAGE=$(mktemp -d)
mkdir -p "$OUT"

echo "→ Building front-end assets"
npm ci --no-audit --no-fund >/dev/null 2>&1 || npm install --no-audit --no-fund >/dev/null
npm run build >/dev/null

echo "→ Copying committed application files"
git archive --format=tar HEAD . | tar -x -C "$STAGE"
rm -rf "$STAGE/tests" "$STAGE/deploy" "$STAGE/node_modules" "$STAGE/.github" "$STAGE/phpunit.xml"
cp -r public/build "$STAGE/public/"   # include freshly built assets

echo "→ Installing production PHP dependencies"
(cd "$STAGE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet --no-scripts)
(cd "$STAGE" && php artisan package:discover --ansi >/dev/null)
rm -f "$STAGE"/bootstrap/cache/{config,routes-v7,events}.php

cp deploy/hostinger/root.htaccess "$STAGE/.htaccess"
mkdir -p "$STAGE"/storage/{app/public,app/private,framework/{cache/data,sessions,views},logs}
find "$STAGE/storage" "$STAGE/bootstrap/cache" -type d -exec chmod 755 {} +

if [ "$WITH_ENV" = "--with-env" ]; then
  KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  TOKEN=$(php -r 'echo bin2hex(random_bytes(16));')
  sed -e "s|__APP_KEY__|$KEY|" -e "s|__DOMAIN__|$DOMAIN|g" -e "s|__INSTALL_TOKEN__|$TOKEN|" \
      -e "s|__DB_DATABASE__|${DB_DATABASE:-CHANGE_ME}|" -e "s|__DB_USERNAME__|${DB_USERNAME:-CHANGE_ME}|" -e "s|__DB_PASSWORD__|${DB_PASSWORD:-CHANGE_ME}|" \
      -e "s|__ADMIN_EMAIL__|${ADMIN_EMAIL:-admin@$DOMAIN}|" -e "s|__ADMIN_PASSWORD__|${ADMIN_PASSWORD:-CHANGE_ME}|" -e "s|__ADMIN_PHONE__|${ADMIN_PHONE:-0970000001}|" \
      -e "s|__SEED_DEMO__|${SEED_DEMO_DATA:-true}|" \
      deploy/hostinger/env.production > "$STAGE/.env"
  chmod 600 "$STAGE/.env"
  echo "→ .env written (installer URL: https://$DOMAIN/install?token=$TOKEN)"
else
  sed -e "s|__DOMAIN__|$DOMAIN|g" deploy/hostinger/env.production > "$STAGE/.env.hostinger.example"
fi

ZIP="$OUT/handynow_${STAMP}.zip"
(cd "$STAGE" && zip -qr -X "$ZIP" . )
rm -rf "$STAGE"
echo "✓ $ZIP ($(du -h "$ZIP" | cut -f1))"
