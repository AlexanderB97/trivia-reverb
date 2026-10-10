#!/usr/bin/env bash
# Todo va dentro de { ... } con un exit al final: asi bash lee el script completo antes de
# ejecutarlo. El script se actualiza a si mismo con "git reset", y sin esto bash podria
# seguir leyendo la version nueva del archivo a mitad de la ejecucion.
{
# Script de despliegue: lo ejecuta GitHub Actions en la VM despues de cada push a main.
set -e

cd /var/www/trivia

echo "==> Modo mantenimiento"
php artisan down --render="errors::503" --retry=15 || true

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

exit
}