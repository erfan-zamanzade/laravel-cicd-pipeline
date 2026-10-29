# syntax=docker/dockerfile:1

# Base images are pinned to exact versions; Dependabot proposes updates.

# ---------------------------------------------------------------------------
# Stage 1: resolve production dependencies. Composer, its cache and the dev
# packages never reach the runtime image.
# ---------------------------------------------------------------------------
FROM composer:2.10.3 AS vendor

WORKDIR /app

# Dependency manifests first, so this layer is reused until they change.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------------
# Stage 2: runtime. Nginx + PHP-FPM, running as the unprivileged www-data user.
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx-alpine3.24-v5.0.5 AS runtime

ARG APP_VERSION=dev
ARG APP_COMMIT=unknown

LABEL org.opencontainers.image.title="laravel-cicd-pipeline" \
      org.opencontainers.image.description="Minimal Laravel service used to demonstrate a production-style CI/CD pipeline." \
      org.opencontainers.image.source="https://github.com/erfan-zamanzade/laravel-cicd-pipeline" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${APP_COMMIT}"

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_VERSION=${APP_VERSION} \
    APP_COMMIT=${APP_COMMIT} \
    LOG_CHANNEL=stderr \
    LOG_STDERR_FORMATTER="Monolog\\Formatter\\JsonFormatter" \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/health \
    # Cache config/routes/events on start-up; migrations run as a separate
    # one-off step so several replicas never race to migrate the schema.
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=false \
    # A JSON-only service has no Blade views to compile.
    AUTORUN_LARAVEL_VIEW_CACHE=false

# Pull OS security fixes published after the base image was built (the
# vulnerability scan in CI fails on fixable HIGH/CRITICAL findings).
# Numeric IDs (82 = www-data on Alpine) let Kubernetes verify runAsNonRoot.
USER 0
# hadolint ignore=DL3017
RUN apk upgrade --no-cache
USER 82:82

COPY --chown=82:82 --from=vendor /app /var/www/html

RUN php artisan package:discover --ansi

EXPOSE 8080
