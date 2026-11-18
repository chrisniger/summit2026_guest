#!/bin/sh
set -eu

cd /var/www/html

log() {
    printf '%s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*"
}

# Writable paths; a mounted volume may not exist on first boot.
for dir in \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache; do
    mkdir -p "$dir"
done

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
    log "FATAL: APP_KEY is empty. Set it in Coolify -> Environment Variables (php artisan key:generate --show)."
    exit 1
fi

# Stale caches from the image or a previous container must not survive.
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "Running database migrations"
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    log "Seeding database"
    php artisan db:seed --force --no-interaction
fi

if [ "${STORAGE_LINK:-true}" = "true" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
fi

log "Caching configuration, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Starting supervisord"
exec "$@"
