#!/usr/bin/env bash
# Deploy a new release. Run from the release directory on each application server.
#   ./deploy.sh              normal deploy
#   SKIP_MIGRATE=1 ./deploy.sh   deploy code only
set -euo pipefail

echo "==> Installing dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm ci --omit=dev && npm run build

echo "==> Database"
if [ "${SKIP_MIGRATE:-0}" != "1" ]; then
  php artisan migrate --force
fi

echo "==> Caching configuration for production"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Restarting workers and the application"
php artisan queue:restart          # workers pick up the new code after finishing their current job
php artisan octane:reload 2>/dev/null || true   # no-op until Octane is installed

echo "==> Checking the release"
php artisan about --only=environment
curl -fsS "${HEALTH_URL:-http://127.0.0.1/health}" > /dev/null && echo "Health check passed"

echo "Deployed."
