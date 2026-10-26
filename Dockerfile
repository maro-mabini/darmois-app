# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# PHP dependencies.
#
# composer.lock is copied on its own so this layer is only rebuilt when the
# lock file changes, not on every application edit.
# ---------------------------------------------------------------------------
FROM php:8.5-cli-alpine AS vendor

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring \
    && apk del .build-deps \
    && apk add --no-cache oniguruma unzip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist


# ---------------------------------------------------------------------------
# Front-end assets, built with the same toolchain used locally (`vp`).
#
# Debian rather than Alpine here: Rollup, Tailwind Oxide and Lightning CSS
# all ship glibc-native builds (see package.json optionalDependencies).
# ---------------------------------------------------------------------------
FROM node:24-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# resources/css/app.css scans the framework's pagination views, and every
# index page calls ->links(). Give the build the real views so those utility
# classes survive Tailwind's purge.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views

RUN npm run build


# ---------------------------------------------------------------------------
# Runtime: nginx + php-fpm in a single container, as requested.
# ---------------------------------------------------------------------------
FROM php:8.5-fpm-alpine

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring \
    && apk del .build-deps \
    && apk add --no-cache nginx libpq oniguruma

# nginx and php-fpm write their pid files here; the packages do not create
# these directories when neither is started by an init script.
RUN mkdir -p /run/nginx /usr/local/var/run

COPY docker/php.ini /usr/local/etc/php/conf.d/99-darmois.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# storage/app/public holds the DAR brand images and is exposed through the
# public/storage symlink the entrypoint creates at boot.
RUN mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
