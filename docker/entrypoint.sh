#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY environment variable must be set."
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
