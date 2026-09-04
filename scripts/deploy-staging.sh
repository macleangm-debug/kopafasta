#!/usr/bin/env bash
set -euo pipefail
# Deploy an exact Git commit to staging.kopafasta.com (after the Droplet exists).
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export DEPLOY_ENV=staging
export DEPLOY_COMMIT="${DEPLOY_COMMIT:-HEAD}"
HOST="${STAGING_HOST:-}"
APP_DIR="${STAGING_APP_DIR:-/var/www/kopafasta}"

if [[ -z "$HOST" ]]; then
  echo "Set STAGING_HOST=root@<STAGING_DROPLET_IP> once the staging Droplet exists."
  echo "Example: STAGING_HOST=root@x.x.x.x ./scripts/deploy-staging.sh"
  exit 1
fi

exec "$ROOT/scripts/deploy.sh" "$HOST" "$APP_DIR"
