#!/bin/sh
# HIMS container start-up. Runs on every deploy and every restart.
#
# Everything here is deliberately at runtime rather than at image build time:
# `docker build` on Render does not see the service's environment variables, so
# a config cache baked into the image would hold empty strings for every
# credential. Caching here bakes in what Render actually injected.
set -e

: "${PORT:=10000}"
export PORT

echo "==> nginx will listen on ${PORT}"
envsubst '${PORT}' < /etc/nginx/templates/hims.conf.template > /etc/nginx/http.d/hims.conf

echo "==> preparing writable paths"
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/app/public storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
    echo "!!  APP_KEY is empty. Sessions and the two encrypted columns"          >&2
    echo "!!  (employees.phone, employee_credentials.credential_number) will"   >&2
    echo "!!  fail. Generate one locally with 'php artisan key:generate --show'" >&2
    echo "!!  and paste it into the Render dashboard."                          >&2
fi

# Fails the deploy loudly rather than serving a half-migrated schema. Two
# migrations install MySQL triggers and one installs a view, so the database
# user needs TRIGGER and CREATE VIEW — a permission error surfaces here.
echo "==> running migrations"
php artisan migrate --force --no-interaction

echo "==> caching config, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# php-fpm backgrounds itself; nginx stays in the foreground so the container's
# lifetime is nginx's lifetime and Render sees the process it is supervising.
echo "==> starting php-fpm and nginx"
php-fpm -D
exec nginx -g 'daemon off;'
