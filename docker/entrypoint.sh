#!/bin/sh
set -e

# Cache config/routes/views at boot, when the environment is available. Safe to
# run for every role (web, worker, scheduler): it just warms bootstrap/cache.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Only the web container migrates, so workers starting in parallel don't race.
# Idempotent and safe to repeat on restart.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
	php artisan migrate --force
fi

exec "$@"
