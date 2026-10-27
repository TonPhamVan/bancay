#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/setup-local.py
docker compose build app
docker compose up -d db
docker compose run --rm app composer install --no-interaction
docker compose run --rm app php artisan vendor:publish --provider='Exceedone\Exment\ExmentServiceProvider' --no-interaction
docker compose run --rm app php artisan store:install-local --no-interaction
docker compose run --rm --env-from-file legacy-private/phase1/admin.env app php artisan store:initialize-local-admin --no-interaction
docker compose run --rm app php artisan storage:link
