#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# The DAR brand images live on the public disk, so the symlink is part of
# boot rather than a one-off setup step.
php artisan storage:link --force

# A fresh database volume is migrated before the first request is served.
php artisan migrate --force

# Config, routes and views are immutable inside a container; cache them once
# the environment from .env.docker is in place.
php artisan optimize

chown -R www-data:www-data storage bootstrap/cache

php-fpm -D

exec nginx -g 'daemon off;'
