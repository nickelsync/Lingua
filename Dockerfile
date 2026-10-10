# syntax=docker/dockerfile:1
# Production image: FrankenPHP (Caddy + PHP 8.3) serving the built app.

FROM docker.io/library/node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM docker.io/library/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM docker.io/dunglas/frankenphp:1-php8.3 AS app
RUN install-php-extensions pdo_pgsql opcache intl zip

ENV APP_ENV=production APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    # Caddy serves plain HTTP; run TLS at your proxy/load balancer.
    SERVER_NAME=:8080 \
    OCTANE_SERVER= \
    PORT=8080

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/lingua-entrypoint
# Normalize perms so the build doesn't depend on the host umask
RUN chmod -R a+rX /app \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    # www-data needs Caddy state dirs when running non-root
    && mkdir -p /data/caddy /config/caddy && chown -R www-data:www-data /data /config

USER www-data
EXPOSE 8080
ENTRYPOINT ["lingua-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
