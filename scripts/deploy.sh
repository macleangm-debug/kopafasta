#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./scripts/deploy.sh <user@host> <remote_app_dir>

Deploys the current Git commit (or DEPLOY_COMMIT) by rsync.

Required for production:
  CONFIRM_PRODUCTION=1
  APPROVED_COMMIT=<full-or-short-sha that already ran on staging>

Environment variables:
  DEPLOY_ENV=staging|production   (default: staging)
  DEPLOY_COMMIT=<sha>             (default: HEAD)
  RELEASE_VERSION=v1.0.0
  SSH_PORT=22
  PHP_BIN=php
  COMPOSER_BIN=composer
  NPM_BIN=npm
  RUN_NPM_BUILD=1
  WEB_GROUP=www-data:www-data
  DEPLOY_DIRTY=1                  allow uncommitted files (staging only)

Example:
  DEPLOY_ENV=staging ./scripts/deploy.sh root@STAGING_IP /var/www/kopafasta
  CONFIRM_PRODUCTION=1 APPROVED_COMMIT=abc1234 DEPLOY_ENV=production \
    ./scripts/deploy.sh root@PRODUCTION_IP /var/www/kopafasta
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
DEPLOY_ENV="${DEPLOY_ENV:-staging}"
RELEASE_VERSION="${RELEASE_VERSION:-dev}"
DEPLOY_DIRTY="${DEPLOY_DIRTY:-0}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! command -v rsync >/dev/null 2>&1; then
  echo "Error: rsync is required on local machine."
  exit 1
fi

if [[ "$DEPLOY_ENV" != "staging" && "$DEPLOY_ENV" != "production" ]]; then
  echo "Error: DEPLOY_ENV must be staging or production (got: $DEPLOY_ENV)"
  exit 1
fi

DEPLOY_COMMIT="${DEPLOY_COMMIT:-$(git rev-parse HEAD)}"
DEPLOY_COMMIT="$(git rev-parse "$DEPLOY_COMMIT")"
SHORT_COMMIT="$(git rev-parse --short "$DEPLOY_COMMIT")"

if [[ "$DEPLOY_ENV" == "production" ]]; then
  if [[ "${CONFIRM_PRODUCTION:-}" != "1" ]]; then
    echo "Refusing production deploy. Set CONFIRM_PRODUCTION=1 after owner UAT."
    exit 1
  fi
  if [[ -z "${APPROVED_COMMIT:-}" ]]; then
    echo "Refusing production deploy without APPROVED_COMMIT (the exact staging SHA)."
    exit 1
  fi
  APPROVED_FULL="$(git rev-parse "$APPROVED_COMMIT")"
  if [[ "$APPROVED_FULL" != "$DEPLOY_COMMIT" ]]; then
    echo "Refusing production deploy: DEPLOY_COMMIT $DEPLOY_COMMIT does not match APPROVED_COMMIT $APPROVED_FULL"
    exit 1
  fi
fi

if [[ -n "$(git status --porcelain)" && "$DEPLOY_DIRTY" != "1" ]]; then
  echo "Working tree is dirty. Commit first, or set DEPLOY_DIRTY=1 (staging only)."
  if [[ "$DEPLOY_ENV" == "production" ]]; then
    exit 1
  fi
  exit 1
fi

if [[ "$DEPLOY_ENV" == "production" && "$DEPLOY_DIRTY" == "1" ]]; then
  echo "DEPLOY_DIRTY is not allowed for production."
  exit 1
fi

STAGEDIR="$(mktemp -d /tmp/kopafasta-release.XXXXXX)"
cleanup() { rm -rf "$STAGEDIR"; }
trap cleanup EXIT

echo "==> Exporting Git commit ${SHORT_COMMIT} (${DEPLOY_COMMIT}) for ${DEPLOY_ENV}"
git archive --format=tar "$DEPLOY_COMMIT" | tar -x -C "$STAGEDIR"

echo "==> Syncing ${SHORT_COMMIT} to ${SERVER}:${APP_DIR}"
rsync -az --delete \
  -e "ssh -p ${SSH_PORT}" \
  --exclude "/.git/" \
  --exclude "/vendor/" \
  --exclude "/node_modules/" \
  --exclude "/.env" \
  --exclude "/storage/*.key" \
  --exclude "/storage/app/public/" \
  --exclude "/storage/logs/" \
  --exclude "/public/hot" \
  --exclude "/public/build/" \
  "${STAGEDIR}/" "${SERVER}:${APP_DIR}/"

echo "==> Running remote deploy steps"
ssh -p "${SSH_PORT}" "${SERVER}" bash -s -- \
  "${APP_DIR}" \
  "${PHP_BIN}" \
  "${COMPOSER_BIN}" \
  "${NPM_BIN}" \
  "${RUN_NPM_BUILD}" \
  "${WEB_GROUP}" \
  "${DEPLOY_ENV}" \
  "${DEPLOY_COMMIT}" \
  "${RELEASE_VERSION}" <<'REMOTE'
set -euo pipefail

APP_DIR="$1"
PHP_BIN="$2"
COMPOSER_BIN="$3"
NPM_BIN="$4"
RUN_NPM_BUILD="$5"
WEB_GROUP="$6"
DEPLOY_ENV="$7"
DEPLOY_COMMIT="$8"
RELEASE_VERSION="$9"

cd "$APP_DIR"

if [[ ! -f .env ]]; then
  echo "Error: .env is missing at $APP_DIR/.env"
  echo "Create the environment .env on the server first, then re-run deploy."
  exit 1
fi

APP_ENV_VALUE="$(awk -F= '/^APP_ENV=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
if [[ "$DEPLOY_ENV" == "production" && "$APP_ENV_VALUE" != "production" ]]; then
  echo "Error: remote APP_ENV is '$APP_ENV_VALUE' but this is a production deploy."
  exit 1
fi
if [[ "$DEPLOY_ENV" == "staging" && "$APP_ENV_VALUE" == "production" ]]; then
  echo "Error: remote APP_ENV is production. Staging deploys must use APP_ENV=staging."
  exit 1
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app

"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [[ "$RUN_NPM_BUILD" == "1" ]]; then
  if "$NPM_BIN" ci --no-audit --no-fund; then
    :
  else
    "$NPM_BIN" install --no-audit --no-fund
  fi
  "$NPM_BIN" run build
  "$NPM_BIN" prune --omit=dev --no-audit --no-fund || true
fi

rm -f public/hot

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --class=SafeConfigurationSeeder --force || true
"$PHP_BIN" artisan gl:backfill-disbursements || true
if [[ "$DEPLOY_ENV" == "staging" ]]; then
  "$PHP_BIN" artisan db:seed --class=MarketplaceAssetSeeder --force || true
  "$PHP_BIN" artisan marketplace:fix-photos || true
fi
"$PHP_BIN" artisan storage:link || true

"$PHP_BIN" -r '
$payload = [
    "commit" => $argv[1],
    "version" => $argv[2],
    "environment" => $argv[3],
    "deployed_at" => gmdate("Y-m-d\\TH:i:s\\Z"),
];
file_put_contents("storage/app/release.json", json_encode($payload, JSON_PRETTY_PRINT)."\n");
' "$DEPLOY_COMMIT" "$RELEASE_VERSION" "$DEPLOY_ENV"

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan queue:restart || true
systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || service php8.3-fpm reload 2>/dev/null || true

chgrp -R "${WEB_GROUP#*:}" storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache

echo "STAGING HEAD: $DEPLOY_COMMIT"
if [[ "$DEPLOY_ENV" == "production" ]]; then
  echo "PRODUCTION HEAD: $DEPLOY_COMMIT"
fi
echo "Deploy completed ($DEPLOY_ENV $RELEASE_VERSION $DEPLOY_COMMIT)."
REMOTE

echo "==> Done"
echo "${DEPLOY_ENV} HEAD: ${DEPLOY_COMMIT}"
