# HIMS v2 on Render.
#
# Render has no native PHP runtime — Node, Bun, Python, Ruby, Go, Rust and
# Elixir are the native ones, and PHP is deployed as a Docker image. So the
# dashboard's Build Command / Start Command boxes do not apply to this app:
# everything below replaces them.
#
# nginx and PHP-FPM run in the same container. That is not the shape you would
# choose on a box you controlled, but a Render web service is one image with
# one port, and splitting them would mean a private service plus a network hop
# for every request.

# ── stage 1 · front-end bundle ────────────────────────────────────────────
# The domain UI is served from public/css/hims.css and does not go through
# Vite, but profile/edit still extends Breeze's layout and calls @vite — and
# @vite throws when the manifest is missing, so the bundle must exist.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
# tailwind.config.js also globs Laravel's own pagination views out of vendor/,
# which is not in this stage. Deliberate: the seven paginated screens extend
# layouts/hims.blade.php, which loads public/css/hims.css and never @vite, so
# those utilities are not loaded anywhere. Copying vendor/ in would serialise
# this stage behind the composer one to build CSS no page requests.
RUN npm run build

# ── stage 2 · PHP dependencies ────────────────────────────────────────────
# --no-scripts because composer.json's post-autoload-dump runs
# `artisan package:discover`, which cannot boot until the whole app is in
# place. It runs in the runtime stage instead, where the real extensions are.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ── stage 3 · runtime ─────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# Only two extensions are added, and the short list is deliberate. The base
# image already loads 34, covering all thirteen that composer.lock hard-requires
# (ctype dom fileinfo filter hash iconv json libxml mbstring openssl pcre
# session tokenizer) — verified against php:8.3-fpm-alpine directly.
#
# pdo_mysql: the app is MySQL-bound (two migrations install triggers, two create
#   a view, and the controllers embed CONCAT/DATE_FORMAT/FIELD/whereMonth), so
#   it cannot run on Render's managed Postgres without a rewrite.
# opcache:   compiles the app once per container rather than on every request.
# gettext:   supplies envsubst, which stamps Render's $PORT into the nginx conf.
#
# bcmath is deliberately absent: nothing in app/, database/ or routes/ calls a
# bc* function, and the four lock entries mentioning it only ever *suggest* it.
# docker-php-ext-install adds $PHPIZE_DEPS as a virtual package and runs
# `apk del` on it afterwards, so the compiler toolchain never reaches the image.
RUN apk add --no-cache nginx gettext \
 && docker-php-ext-install pdo_mysql opcache \
 && rm -rf /var/cache/apk/* /etc/nginx/http.d/default.conf

WORKDIR /var/www/html

COPY --from=vendor  /app                ./
COPY --from=assets  /app/public/build   ./public/build

COPY docker/nginx.conf    /etc/nginx/templates/hims.conf.template
COPY docker/php-fpm.conf  /usr/local/etc/php-fpm.d/zz-hims.conf
COPY docker/php.ini       /usr/local/etc/php/conf.d/zz-hims.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

RUN php artisan package:discover --ansi \
 && mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/app/public storage/logs \
             bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# Documentation only — Render injects the real PORT and ignores EXPOSE.
EXPOSE 10000

# CMD, not ENTRYPOINT. Render's "Docker Command" field replaces the image's
# command and cannot override an entrypoint, and the credential-expiry cron job
# is the same image run as `php artisan hims:scan-credential-expiry`. With
# ENTRYPOINT here that override would be appended as arguments the start-up
# script ignores, so the cron job would boot a web server and scan nothing.
CMD ["/usr/local/bin/entrypoint"]
