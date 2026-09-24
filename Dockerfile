FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
COPY Modules/Users/composer.json Modules/Users/composer.json
COPY Modules/Rbac/composer.json Modules/Rbac/composer.json
COPY Modules/Audit/composer.json Modules/Audit/composer.json
COPY Modules/DataProtection/composer.json Modules/DataProtection/composer.json
COPY Modules/Ingestion/composer.json Modules/Ingestion/composer.json
COPY Modules/Reconciliation/composer.json Modules/Reconciliation/composer.json
COPY Modules/ExceptionManagement/composer.json Modules/ExceptionManagement/composer.json
COPY Modules/Adjustments/composer.json Modules/Adjustments/composer.json
COPY Modules/Notifications/composer.json Modules/Notifications/composer.json
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . ./
COPY --from=vendor /app/vendor/tightenco/ziggy vendor/tightenco/ziggy
RUN npm run build

FROM dunglas/frankenphp:1-php8.3-alpine AS runtime
RUN install-php-extensions pdo_pgsql pgsql bcmath intl zip gd pcntl opcache
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=json \
    SERVER_NAME=:8000 \
    AUDIT_ARCHIVE_PATH=/data/audit-archive
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY deploy/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN rm -rf node_modules tests Modules/*/tests deploy Dockerfile Makefile .env \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p /data/audit-archive storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && adduser -D -u 10001 reconflow \
    && chown -R reconflow:reconflow /app/storage /app/bootstrap/cache /data /config /data \
    && chmod +x /usr/local/bin/docker-entrypoint
USER reconflow
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=3s --start-period=30s --retries=5 CMD wget -qO- http://127.0.0.1:8000/health || exit 1
ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "php-server", "--root", "public", "--listen", ":8000"]
