#!/usr/bin/env bash
# Import split database files in one MySQL session (schema → data → triggers).
#
# Usage (repository root):
#   bash scripts/import_database_split.sh
#
# Environment:
#   MYSQL_HOST=127.0.0.1   (default)
#   MYSQL_USER=root        (default)
#   MYSQL_PASSWORD=itmanagement

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-itmanagement}"

SCHEMA_FILE="${ROOT}/db/01_schema.sql"
DATA_FILE="${ROOT}/db/02_data.sql"
TRIGGERS_FILE="${ROOT}/db/03_triggers.sql"

for f in "$SCHEMA_FILE" "$DATA_FILE" "$TRIGGERS_FILE"; do
  if [[ ! -f "$f" ]]; then
    echo "FAIL: missing ${f} — ensure db/01_schema.sql, db/02_data.sql, and db/03_triggers.sql exist."
    exit 1
  fi
done

MYSQL=(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --default-character-set=utf8mb4)

echo "==> Importing split database (01_schema → 02_data → 03_triggers) via ${MYSQL_HOST}"
{
  cat "$SCHEMA_FILE"
  echo ""
  cat "$DATA_FILE"
  echo ""
  cat "$TRIGGERS_FILE"
} | "${MYSQL[@]}"

DERIVED_TABLE_COUNT="$(grep -c '^CREATE TABLE' "$SCHEMA_FILE" || true)"
TABLE_COUNT="$("${MYSQL[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='itmanagement';")"
echo "==> Live table count: ${TABLE_COUNT} (expected ${DERIVED_TABLE_COUNT})"

if [[ "$TABLE_COUNT" != "$DERIVED_TABLE_COUNT" ]]; then
  echo "FAIL: expected ${DERIVED_TABLE_COUNT} tables, got ${TABLE_COUNT}"
  exit 1
fi

php scripts/verify_database_schema.php

echo "OK: db/ import completed (${TABLE_COUNT} tables)."
