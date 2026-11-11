#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
command -v php >/dev/null || { echo 'BLOCKED: PHP not found'; exit 2; }
if [[ ! -f vendor/autoload.php ]]; then
  command -v composer >/dev/null || { echo 'BLOCKED: Composer required when release vendor/ is absent'; exit 2; }
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi
php scripts/deploy.php --apply --create-database
php artisan aadi:doctor
