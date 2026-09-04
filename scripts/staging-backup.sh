#!/usr/bin/env bash
# Daily staging MySQL + storage backup. Staging only.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/kopafasta}"
DEST="${BACKUP_DIR:-/var/backups/kopafasta-staging}"
KEEP_DAYS="${KEEP_DAYS:-7}"
mkdir -p "$DEST"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
cd "$APP_DIR"

DB_DATABASE="$(awk -F= '/^DB_DATABASE=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
DB_USERNAME="$(awk -F= '/^DB_USERNAME=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"
DB_PASSWORD="$(awk -F= '/^DB_PASSWORD=/{print $2; exit}' .env | tr -d '"' | tr -d "'")"

mysqldump --single-transaction --routines --triggers \
  -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > "${DEST}/db-${STAMP}.sql.gz"

tar -czf "${DEST}/storage-${STAMP}.tar.gz" -C "$APP_DIR" storage/app || true
find "$DEST" -type f -mtime +"${KEEP_DAYS}" -delete
echo "Backup ${STAMP} written to ${DEST}"
