# syntax=docker/dockerfile:1

###############################################################################
# SUMMIT 2026 — production image (Coolify)
#
# One container runs nginx, PHP-FPM, the "imports" queue worker and the
# scheduler under supervisord. The web server listens on 8080.
#
# Coolify: Build Pack = Dockerfile, Dockerfile Location = /Dockerfile,
# Ports Exposes = 8080, Healthcheck Path = /up.
###############################################################################

ARG NODE_VERSION=22
ARG PHP_VERSION=8.4

# ---------------------------------------------------------------------------
# 1. Frontend assets (Tailwind 4 + Vite 8)
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

ARG VITE_APP_NAME="SUMMIT 2026"
ENV VITE_APP_NAME=${VITE_APP_NAME}

RUN npm run build

# ---------------------------------------------------------------------------
# 2. Runtime: nginx + PHP-FPM 8.4
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS runtime

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

# Extensions required by app/Services/Operations.php: pdo_mysql, zip, dom,
# mbstring, fileinfo, openssl (openssl is compiled into the PHP image).
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        fileinfo \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apk add --no-cache \
        curl \
        libzip \
        libxml2 \
        nginx \
        oniguruma \
        supervisor \
    && apk del .build-deps

# Production php.ini plus app-specific overrides.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' \
        'expose_php = Off' \
        'memory_limit = 256M' \
        'upload_max_filesize = 12M' \
        'post_max_size = 12M' \
        'max_execution_time = 120' \
        'max_input_time = 120' \
        'realpath_cache_size = 4096K' \
        'realpath_cache_ttl = 600' \
        'opcache.enable = 1' \
        'opcache.enable_cli = 1' \
        'opcache.memory_consumption = 128' \
        'opcache.interned_strings_buffer = 16' \
        'opcache.max_accelerated_files = 20000' \
        'opcache.validate_timestamps = 0' \
        > "$PHP_INI_DIR/conf.d/zz-summit.ini" \
    # PHP-FPM workers need the container environment (Coolify variables).
    && printf '%s\n' '[www]' 'clear_env = no' > /usr/local/etc/php-fpm.d/zz-summit.conf \
    && mkdir -p /var/log/nginx /var/log/supervisor /var/run/nginx \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Dependencies first so this layer survives application code changes.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
    && rm -rf "$COMPOSER_CACHE_DIR"

COPY . .
COPY --from=assets /app/public/build ./public/build

# Never ship a local .env: Coolify supplies the variables at runtime.
RUN rm -f .env .env.local .env.production \
    && composer dump-autoload --no-dev --optimize --no-interaction --no-scripts \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker-nginx.conf /etc/nginx/http.d/default.conf
COPY docker-supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# The executable bit is not reliable from a Windows checkout.
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ---------------------------------------------------------------------------
# 3. Application environment
#
# Non-secret defaults only. Every value can be overridden from
# Coolify -> Environment Variables, which takes precedence over ENV.
# APP_KEY, DB_PASSWORD and MAIL_PASSWORD are intentionally NOT set here:
# anything baked into an image is readable by anyone who can pull it and
# cannot be rotated without a rebuild.
# ---------------------------------------------------------------------------
ENV APP_NAME="SUMMIT 2026" \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=https://summit2026.summitdata.one \
    APP_LOCALE=en \
    APP_FALLBACK_LOCALE=en \
    APP_MAINTENANCE_DRIVER=file \
    BCRYPT_ROUNDS=12 \
    LOG_CHANNEL=stack \
    LOG_STACK=daily \
    LOG_LEVEL=warning \
    LOG_DEPRECATIONS_CHANNEL=null \
    DB_CONNECTION=mysql \
    DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_DATABASE=u188660189_summit \
    DB_USERNAME=u188660189_summit \
    SESSION_DRIVER=database \
    SESSION_LIFETIME=120 \
    SESSION_ENCRYPT=true \
    SESSION_SECURE_COOKIE=true \
    SESSION_HTTP_ONLY=true \
    SESSION_SAME_SITE=lax \
    SESSION_PATH=/ \
    FILESYSTEM_DISK=local \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    DB_QUEUE_RETRY_AFTER=300 \
    BROADCAST_CONNECTION=log \
    MAIL_MAILER=smtp \
    MAIL_SCHEME=smtps \
    MAIL_HOST=smtp.hostinger.com \
    MAIL_PORT=465 \
    MAIL_USERNAME=noreply@summitdata.one \
    MAIL_FROM_ADDRESS=noreply@summitdata.one \
    MAIL_FROM_NAME="SUMMIT 2026" \
    RUN_MIGRATIONS=true \
    RUN_SEEDERS=false \
    STORAGE_LINK=true

# Optional build-time secrets for hosts that cannot inject runtime variables.
# Build args are stored in the image history — prefer Coolify's environment
# variables and leave these empty.
ARG APP_KEY=
ARG DB_PASSWORD=
ARG MAIL_PASSWORD=
ENV APP_KEY=${APP_KEY} \
    DB_PASSWORD=${DB_PASSWORD} \
    MAIL_PASSWORD=${MAIL_PASSWORD}

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
