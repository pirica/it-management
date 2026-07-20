#!/usr/bin/env bash
# Import full database.sql and assert the live schema matches expectations.
# Split alternative: bash scripts/import_database_split.sh (db/01 → 03 → 02, one session).
# Used by CI (GitHub Actions MySQL service) and local verification after schema edits.
#
# Usage (repository root):
#   bash scripts/verify_database_sql_import.sh
#
# Environment:
#   MYSQL_HOST=127.0.0.1   (default)
#   MYSQL_USER=root        (default)
#   MYSQL_PASSWORD=itmanagement
#   EXPECTED_TABLE_COUNT=    (optional override; default derived from CREATE TABLE lines in database.sql)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-itmanagement}"
SQL_FILE="${ROOT}/database.sql"

if [[ ! -f "$SQL_FILE" ]]; then
  echo "FAIL: missing ${SQL_FILE}"
  exit 1
fi

# Why: Table count grows with schema; derive from database.sql so CI does not rely on a stale constant.
DERIVED_TABLE_COUNT="$(grep -c '^CREATE TABLE' "$SQL_FILE" || true)"
if [[ -z "${EXPECTED_TABLE_COUNT:-}" ]]; then
  EXPECTED_TABLE_COUNT="$DERIVED_TABLE_COUNT"
fi

if [[ "$EXPECTED_TABLE_COUNT" != "$DERIVED_TABLE_COUNT" ]]; then
  echo "WARN: EXPECTED_TABLE_COUNT=${EXPECTED_TABLE_COUNT} differs from database.sql CREATE TABLE count (${DERIVED_TABLE_COUNT})"
fi

MYSQL=(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --default-character-set=utf8mb4)

echo "==> Importing database.sql via ${MYSQL_HOST} (expect ${EXPECTED_TABLE_COUNT} tables)"
"${MYSQL[@]}" < "$SQL_FILE"

TABLE_COUNT="$("${MYSQL[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='itmanagement';")"
echo "==> Live table count: ${TABLE_COUNT}"

if [[ "$TABLE_COUNT" != "$EXPECTED_TABLE_COUNT" ]]; then
  echo "FAIL: expected ${EXPECTED_TABLE_COUNT} tables, got ${TABLE_COUNT}"
  exit 1
fi

echo "==> Verifying table names match database.sql"
php scripts/verify_database_schema.php

echo "OK: database.sql import completed (${TABLE_COUNT} tables)."
