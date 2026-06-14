#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./scripts/deploy.sh <user@host> <remote_app_dir>

Example:
  ./scripts/deploy.sh root@137.184.55.36 /var/www/kopafasta

Optional environment variables:
  SSH_PORT=22
  PHP_BIN=php
  COMPOSER_BIN=composer
  NPM_BIN=npm
  RUN_NPM_BUILD=1
  WEB_GROUP=www-data:www-data
EOF
}

if [[ ${1:-} == "-h" || ${1:-} == "--help" ]]; then
  usage
  exit 0
fi

if [[ $# -lt 2 ]]; then
  usage
  exit 1
fi

SERVER="$1"
APP_DIR="$2"

SSH_PORT="${SSH_PORT:-22}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
RUN_NPM_BUILD="${RUN_NPM_BUILD:-1}"
WEB_GROUP="${WEB_GROUP:-www-data:www-data}"

if ! command -v rsync >/dev/null 2>&1; then
  echo "Error: rsync is required on local machine."
  exit 1
fi

echo "==> Syncing project to ${SERVER}:${APP_DIR}"
rsync -az --delete \
  -e "ssh -p ${SSH_PORT}" \
  --exclude ".git/" \
  --exclude "vendor/" \
  --exclude "node_modules/" \
  --exclude ".env" \
  --exclude "storage/*.key" \
  --exclude "storage/app/public/" \
  --exclude "storage/logs/" \
  ./ "${SERVER}:${APP_DIR}/"

echo "==> Running remote deploy steps"
ssh -p "${SSH_PORT}" "${SERVER}" bash -s -- \
  "${APP_DIR}" \
  "${PHP_BIN}" \
  "${COMPOSER_BIN}" \
  "${NPM_BIN}" \
  "${RUN_NPM_BUILD}" \
  "${WEB_GROUP}" <<'REMOTE'
set -euo pipefail

APP_DIR="$1"
PHP_BIN="$2"
COMPOSER_BIN="$3"
NPM_BIN="$4"
RUN_NPM_BUILD="$5"
WEB_GROUP="$6"

cd "$APP_DIR"

if [[ ! -f .env ]]; then
  echo "Error: .env is missing at $APP_DIR/.env"
  echo "Create .env on server first, then re-run deploy."
  exit 1
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [[ "$RUN_NPM_BUILD" == "1" ]]; then
  # Vite and Tailwind live in devDependencies, so we need dev deps installed
  # to run `npm run build`. Install with dev deps, build, then prune.
  if "$NPM_BIN" ci --no-audit --no-fund; then
    :
  else
    "$NPM_BIN" install --no-audit --no-fund
  fi
  "$NPM_BIN" run build
  "$NPM_BIN" prune --omit=dev --no-audit --no-fund || true
fi

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --class=FinanceDefaultsSeeder --force || true
"$PHP_BIN" artisan gl:backfill-disbursements || true
"$PHP_BIN" artisan db:seed --class=LoanPolicyDefaultsSeeder --force || true
"$PHP_BIN" artisan db:seed --class=DepartmentSeeder --force || true
"$PHP_BIN" artisan db:seed --class=DefaultChartOfAccountsSeeder --force || true
"$PHP_BIN" artisan db:seed --class=DefaultWriteOffRulesSeeder --force || true
"$PHP_BIN" artisan db:seed --class=BranchSeeder --force || true
"$PHP_BIN" artisan db:seed --class=PublicLoanProductsSeeder --force || true
"$PHP_BIN" artisan db:seed --class=LoanProductRateTierSeeder --force || true
"$PHP_BIN" artisan db:seed --class=LoanProductPenaltyDefaultsSeeder --force || true
"$PHP_BIN" artisan db:seed --class=ChargesFeeSeeder --force || true
"$PHP_BIN" artisan db:seed --class=LoanProductPostApprovalFeeCatalogSeeder --force || true
"$PHP_BIN" artisan db:seed --class=NotificationTemplateSeeder --force || true
"$PHP_BIN" artisan db:seed --class=KycDocumentTypeSeeder --force || true
"$PHP_BIN" artisan db:seed --class=MarketplaceAssetSeeder --force || true
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan queue:restart || true

chgrp -R "${WEB_GROUP#*:}" storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache

echo "Deploy completed."
REMOTE

echo "==> Done"
