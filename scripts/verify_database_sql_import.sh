#!/usr/bin/env bash
# Import full database.sql and assert the live schema matches expectations.
# Used by CI (GitHub Actions MySQL service) and local verification after schema edits.
#
# Usage (repository root):
#   bash scripts/verify_database_sql_import.sh
#
# Environment:
#   MYSQL_HOST=127.0.0.1   (default)
#   MYSQL_USER=root        (default)
#   MYSQL_PASSWORD=itmanagement
#   EXPECTED_TABLE_COUNT=88  (default)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-itmanagement}"
EXPECTED_TABLE_COUNT="${EXPECTED_TABLE_COUNT:-88}"
SQL_FILE="${ROOT}/database.sql"

if [[ ! -f "$SQL_FILE" ]]; then
  echo "FAIL: missing ${SQL_FILE}"
  exit 1
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

echo "OK: database.sql import completed (${TABLE_COUNT} tables)."
