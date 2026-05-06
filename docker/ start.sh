#!/usr/bin/env bash
set -e

echo "Starting Laravel app..."

# Render側の環境変数を使ってLaravel最適化
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# APP_KEY が正しく入っていればOK
# migrateはDB接続後に必要なら有効化
# php artisan migrate --force

php-fpm -D

nginx -g "daemon off;"