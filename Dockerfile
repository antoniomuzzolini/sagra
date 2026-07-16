# syntax=docker/dockerfile:1

# One image for web, queue worker and scheduler (D11: same image for the SaaS
# and for self-hosting). FrankenPHP serves the app with built-in, automatic
# HTTPS — no nginx/php-fpm/supervisor to wire up.

# ── Stage 1: PHP dependencies ───────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Platform reqs (ext-gmp, ext-pdo_pgsql…) are satisfied by the runtime image,
# not the composer image, so skip the check here.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
# The composer image's own PHP (8.4) differs from the runtime image (8.3), and
# Composer otherwise bakes its own PHP version into vendor/composer/platform_check.php,
# which then fails at runtime. The app supports PHP ^8.2 and the runtime PHP is
# fixed by this image, so drop that check.
RUN composer config platform-check false \
    && composer dump-autoload --no-dev --optimize --no-scripts

# ── Stage 2: frontend assets ────────────────────────────────────────────────
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# app.ts imports Ziggy from vendor, so the JS build needs the PHP deps present.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ── Stage 3: runtime ────────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.3 AS app

# pdo_pgsql: PostgreSQL · gmp/bcmath: web-push VAPID signing · opcache: speed.
RUN install-php-extensions pdo_pgsql gmp bcmath intl opcache zip

WORKDIR /app

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# Build Laravel's package manifest (no env/DB needed) and let the runtime user
# write caches and logs.
RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run"]
