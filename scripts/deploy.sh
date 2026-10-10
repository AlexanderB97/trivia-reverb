#!/usr/bin/env bash
# Script de despliegue: lo ejecuta GitHub Actions en la VM despues de cada push a main.
set -e

cd /var/www/trivia

echo "==> Modo mantenimiento"
php artisan down --retry=15 || true

echo "==> Bajando la ultima version de main"
git fetch origin main
git reset --hard origin/main

echo "==> Dependencias de PHP"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Compilando el frontend"
npm ci
npm run build

echo "==> Migraciones"
php artisan migrate --force

echo "==> Cache de Laravel"
php artisan optimize

echo "==> Reiniciando PHP y Reverb"
sudo systemctl reload php8.3-fpm
sudo supervisorctl restart reverb

echo "==> Saliendo de mantenimiento"
php artisan up

echo "==> Deploy terminado"