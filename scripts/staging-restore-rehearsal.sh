#!/usr/bin/env bash
# Restore rehearsal: take a backup, insert a marker, restore, confirm marker is gone.
# Staging only. Never point this at production.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/kopafasta}"
DEST="${BACKUP_DIR:-/var/backups/kopafasta-staging}"
cd "$APP_DIR"

APP_ENV_VALUE="$(awk -F= '/^APP_ENV=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
if [[ "$APP_ENV_VALUE" == "production" ]]; then
  echo "Refusing restore rehearsal on production."
  exit 1
fi

"$APP_DIR/scripts/staging-backup.sh"
LATEST="$(ls -1t "$DEST"/db-*.sql.gz | head -1)"
test -n "$LATEST"

DB_DATABASE="$(awk -F= '/^DB_DATABASE=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
DB_USERNAME="$(awk -F= '/^DB_USERNAME=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
DB_PASSWORD="$(awk -F= '/^DB_PASSWORD=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"

mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "CREATE TABLE IF NOT EXISTS _restore_probe (id INT); INSERT INTO _restore_probe VALUES (1);"
gunzip -c "$LATEST" | mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
COUNT="$(mysql -N -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SHOW TABLES LIKE '_restore_probe';" | wc -l | tr -d ' ')"
if [[ "$COUNT" != "0" ]]; then
  echo "FAIL: probe table still present after restore"
  exit 1
fi
echo "Restore rehearsal PASS using $LATEST"
