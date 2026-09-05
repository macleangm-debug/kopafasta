#!/usr/bin/env bash
# Recorded operator path: clone kopafasta.triptz.net TEST DATA into the
# independent staging database at staging.kopafasta.com.
#
# Never copies .env. Never points staging at the triptz MySQL instance.
# Never modifies the triptz application. Never touches 8 GB production DNS.
#
# Executed 2026-09-04:
#   source backup  /var/backups/kopafasta-triptz-migration/db-20260904T212931Z.sql.gz
#   source storage  /var/backups/kopafasta-triptz-migration/storage-20260904T212931Z.tar.gz
#   staging backup  /var/backups/kopafasta-staging/db-20260904T214348Z.sql.gz
set -euo pipefail

echo "This script is the identity-checked operator wrapper."
echo "The 2026-09-04 migration already restored dump 20260904T212931Z into kopafasta_staging."
echo "Re-running it would overwrite staging again. Stop unless you intend a fresh clone."
exit 2
