#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
command -v composer >/dev/null || { echo 'BLOCKED: Composer not found'; exit 2; }
command -v php >/dev/null || { echo 'BLOCKED: PHP not found'; exit 2; }
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php scripts/deploy.php --apply --create-database
php artisan aadi:doctor
