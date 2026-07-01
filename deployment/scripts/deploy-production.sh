#!/usr/bin/env sh
set -eu

php artisan release:backup-before-update
php artisan release:maintenance on --message="Production deployment in progress."

php artisan down --render="errors::503" || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan up

php artisan release:maintenance off --message="Production deployment complete."
