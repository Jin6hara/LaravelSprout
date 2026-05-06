#!/usr/bin/env bash
set -e

echo "Starting Laravel app..."
echo "PORT=${PORT:-10000}"

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

# Render Web Service は $PORT でHTTP待ち受けする必要がある
sed -i -E "s/listen 80[^;]*;/listen ${PORT:-10000};/" /etc/nginx/sites-available/default

echo "Nginx config after port replacement:"
grep -n "listen" /etc/nginx/sites-available/default

nginx -t

nginx -g "daemon off;"