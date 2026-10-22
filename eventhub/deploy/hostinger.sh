#!/usr/bin/env bash
# Build/refresh EventHub on Hostinger. Run over SSH from the app folder:
#   bash deploy/hostinger.sh
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "No .env found. Copy .env.hostinger.example to .env and fill in the database details first." >&2
    exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan storage:link 2>/dev/null || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R 775 storage bootstrap/cache
echo "Done."
