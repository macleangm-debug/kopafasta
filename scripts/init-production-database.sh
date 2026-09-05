#!/usr/bin/env bash
# Initialize a FRESH production database on the designated production Droplet.
# Never clones staging or triptz. Does not change DNS.
set -euo pipefail

if [[ "${CONFIRM_PRODUCTION:-}" != "1" ]]; then
  echo "Refusing. Set CONFIRM_PRODUCTION=1 after the pricing matrix is approved."
  exit 1
fi
if [[ "${INIT_PRODUCTION_DB:-}" != "1" ]]; then
  echo "Refusing. Set INIT_PRODUCTION_DB=1 for a one-time empty production database."
  exit 1
fi

HOST="${PRODUCTION_HOST:-}"
APP_DIR="${PRODUCTION_APP_DIR:-/var/www/kopafasta}"
if [[ -z "$HOST" ]]; then
  echo "Set PRODUCTION_HOST=root@<PRODUCTION_DROPLET_IP>"
  exit 1
fi

SSH_PORT="${SSH_PORT:-22}"

ssh -p "$SSH_PORT" "$HOST" bash -s -- "$APP_DIR" <<'REMOTE'
set -euo pipefail
APP_DIR="$1"
cd "$APP_DIR"
APP_ENV_VALUE="$(awk -F= '/^APP_ENV=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
DB_DATABASE="$(awk -F= '/^DB_DATABASE=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
if [[ "$APP_ENV_VALUE" != "production" ]]; then
  echo "Refusing: remote APP_ENV is '$APP_ENV_VALUE'"
  exit 1
fi
if [[ "$DB_DATABASE" == *staging* || "$DB_DATABASE" != *production* ]]; then
  echo "Refusing: database '$DB_DATABASE' is not the production database."
  exit 1
fi
php artisan migrate --force
php artisan db:seed --class=SafeConfigurationSeeder --force
php artisan db:seed --class=ProductionOwnerBootstrapSeeder --force
php artisan production:assert-clean
php artisan optimize:clear
php artisan config:cache
echo "Production database initialized without staging/triptz data."
REMOTE
