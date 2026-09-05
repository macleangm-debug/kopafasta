#!/usr/bin/env bash
# Restore rehearsal: genuine database replacement plus isolated storage check.
# Staging only. Never point this at production. Does not clone onto production.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/kopafasta}"
DEST="${BACKUP_DIR:-/var/backups/kopafasta-staging}"
cd "$APP_DIR"

APP_ENV_VALUE="$(awk -F= '/^APP_ENV=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
if [[ "$APP_ENV_VALUE" == "production" ]]; then
  echo "Refusing restore rehearsal on production."
  exit 1
fi

DB_DATABASE="$(awk -F= '/^DB_DATABASE=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
DB_USERNAME="$(awk -F= '/^DB_USERNAME=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
DB_PASSWORD="$(awk -F= '/^DB_PASSWORD=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"

if [[ "$DB_DATABASE" != *staging* ]]; then
  echo "Refusing restore rehearsal: database name '$DB_DATABASE' is not a staging database."
  exit 1
fi

mysql_app() {
  mysql -N -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" "$@"
}

supervisorctl stop kopafasta-staging-worker:* >/dev/null 2>&1 || true

mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "DROP TABLE IF EXISTS _restore_probe;"

BEFORE_USERS="$(mysql_app -e "SELECT COUNT(*) FROM users;")"
BEFORE_CUSTOMERS="$(mysql_app -e "SELECT COUNT(*) FROM customers;")"
BEFORE_APPS="$(mysql_app -e "SELECT COUNT(*) FROM loan_applications;")"
BEFORE_PAYMENTS="$(mysql_app -e "SELECT COUNT(*) FROM customer_payments;")"
BEFORE_KITONGA="$(mysql_app -e "SELECT COUNT(*) FROM partners WHERE affiliate_code='KITONGA';")"
SAMPLE_FILE="$(mysql_app -e "SELECT file_path FROM customer_documents WHERE file_path NOT LIKE '%simulated%' LIMIT 1;")"

"$APP_DIR/scripts/staging-backup.sh"
LATEST_DB="$(ls -1t "$DEST"/db-*.sql.gz | head -1)"
LATEST_STORAGE="$(ls -1t "$DEST"/storage-*.tar.gz | head -1)"
test -n "$LATEST_DB"
test -n "$LATEST_STORAGE"

mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "CREATE TABLE IF NOT EXISTS _restore_probe (id INT); INSERT INTO _restore_probe VALUES (1);"

# Kill leftover app connections so DROP DATABASE is not blocked.
mysql --batch -e "SELECT CONCAT('KILL ',id,';') FROM information_schema.processlist WHERE db='${DB_DATABASE}' AND id <> CONNECTION_ID();" \
  | mysql --batch || true

mysql --batch <<SQL
DROP DATABASE \`${DB_DATABASE}\`;
CREATE DATABASE \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL

gunzip -c "$LATEST_DB" | mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"

PROBE="$(mysql_app -e "SHOW TABLES LIKE '_restore_probe';" | wc -l | tr -d ' ')"
if [[ "$PROBE" != "0" ]]; then
  echo "FAIL: probe table still present after drop-and-recreate restore"
  exit 1
fi

AFTER_USERS="$(mysql_app -e "SELECT COUNT(*) FROM users;")"
AFTER_CUSTOMERS="$(mysql_app -e "SELECT COUNT(*) FROM customers;")"
AFTER_APPS="$(mysql_app -e "SELECT COUNT(*) FROM loan_applications;")"
AFTER_PAYMENTS="$(mysql_app -e "SELECT COUNT(*) FROM customer_payments;")"
AFTER_KITONGA="$(mysql_app -e "SELECT COUNT(*) FROM partners WHERE affiliate_code='KITONGA';")"
AFTER_BXJ5="$(mysql_app -e "SELECT COUNT(*) FROM loan_applications WHERE application_number='APP-GL-BXJ5';")"

if [[ "$AFTER_USERS" != "$BEFORE_USERS" || "$AFTER_CUSTOMERS" != "$BEFORE_CUSTOMERS" || "$AFTER_APPS" != "$BEFORE_APPS" || "$AFTER_PAYMENTS" != "$BEFORE_PAYMENTS" ]]; then
  echo "FAIL: row counts changed after restore (users $BEFORE_USERS->$AFTER_USERS customers $BEFORE_CUSTOMERS->$AFTER_CUSTOMERS apps $BEFORE_APPS->$AFTER_APPS payments $BEFORE_PAYMENTS->$AFTER_PAYMENTS)"
  exit 1
fi
if [[ "$AFTER_KITONGA" != "1" ]]; then
  echo "FAIL: KITONGA affiliate missing after restore"
  exit 1
fi
if [[ "$AFTER_BXJ5" != "1" ]]; then
  echo "FAIL: APP-GL-BXJ5 missing after restore"
  exit 1
fi

CHECK_DIR="$(mktemp -d /tmp/kopafasta-storage-restore.XXXXXX)"
tar -tzf "$LATEST_STORAGE" >/dev/null
tar -xzf "$LATEST_STORAGE" -C "$CHECK_DIR"
if [[ -n "$SAMPLE_FILE" ]]; then
  if [[ ! -f "$APP_DIR/storage/app/private/${SAMPLE_FILE}" && ! -f "$APP_DIR/storage/app/public/${SAMPLE_FILE}" && ! -f "$APP_DIR/storage/app/${SAMPLE_FILE}" ]]; then
    FOUND="$(find "$CHECK_DIR" -path "*${SAMPLE_FILE}" | head -1 || true)"
    if [[ -z "$FOUND" ]]; then
      echo "FAIL: sample upload ${SAMPLE_FILE} missing from live storage and backup tarball"
      exit 1
    fi
  fi
fi
rm -rf "$CHECK_DIR"

cd "$APP_DIR"
BOOT="$(sudo -u www-data php artisan env --no-ansi || true)"
if ! grep -qi staging <<<"$BOOT"; then
  echo "FAIL: application did not boot as staging after restore ($BOOT)"
  exit 1
fi

supervisorctl start kopafasta-staging-worker:* >/dev/null 2>&1 || true
echo "Restore rehearsal PASS using $LATEST_DB (storage $LATEST_STORAGE)"
echo "counts users=$AFTER_USERS customers=$AFTER_CUSTOMERS apps=$AFTER_APPS payments=$AFTER_PAYMENTS kitonga=$AFTER_KITONGA bxj5=$AFTER_BXJ5"
