#!/usr/bin/env bash
set -euo pipefail
# Promote the exact commit already approved on Staging to Production.
# Does not copy files from the staging server. Git is the source of truth.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ -z "${APPROVED_COMMIT:-}" ]]; then
  echo "Set APPROVED_COMMIT to the Staging SHA the owner accepted."
  echo "Example: APPROVED_COMMIT=def5678 CONFIRM_PRODUCTION=1 ./scripts/promote-production.sh"
  exit 1
fi

HOST="${PRODUCTION_HOST:-}"
APP_DIR="${PRODUCTION_APP_DIR:-/var/www/kopafasta}"
if [[ -z "$HOST" ]]; then
  echo "Set PRODUCTION_HOST=root@<PRODUCTION_DROPLET_IP> before cutover."
  echo "Do not point www.kopafasta.co.tz (or any production DNS) until staging UAT has passed."
  exit 1
fi

export DEPLOY_ENV=production
export DEPLOY_COMMIT="$APPROVED_COMMIT"
export CONFIRM_PRODUCTION="${CONFIRM_PRODUCTION:-0}"
export RELEASE_VERSION="${RELEASE_VERSION:-}"

exec "$ROOT/scripts/deploy.sh" "$HOST" "$APP_DIR"
