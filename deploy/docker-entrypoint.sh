#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
    php artisan reconflow:bootstrap-demo
fi

exec "$@"
