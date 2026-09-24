#!/bin/sh
set -e

SECRETS_FILE=/data/secrets/generated.env

random_token() {
    head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 40
}

if [ ! -f "$SECRETS_FILE" ] && [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    umask 077
    {
        echo "GENERATED_APP_KEY=base64:$(head -c 32 /dev/urandom | base64)"
        echo "GENERATED_PII_HASH_SALT=$(random_token)"
        echo "GENERATED_SOURCE_SYSTEMS_TOKEN=$(random_token)"
    } > "$SECRETS_FILE.tmp"
    mv "$SECRETS_FILE.tmp" "$SECRETS_FILE"
    echo "Generated application secrets in $SECRETS_FILE"
fi

if [ -f "$SECRETS_FILE" ]; then
    . "$SECRETS_FILE"
    : "${APP_KEY:=$GENERATED_APP_KEY}"
    : "${PII_HASH_SALT:=$GENERATED_PII_HASH_SALT}"
    : "${SOURCE_SYSTEMS_TOKEN:=$GENERATED_SOURCE_SYSTEMS_TOKEN}"
    export APP_KEY PII_HASH_SALT SOURCE_SYSTEMS_TOKEN
fi

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set and no generated secrets are available yet." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
    php artisan reconflow:bootstrap-demo
fi

exec "$@"
