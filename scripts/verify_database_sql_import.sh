#!/usr/bin/env bash
# Import db/ split bundle and assert the live schema matches expectations.
# Used by CI (GitHub Actions MySQL service) and local verification after schema edits.
#
# Usage (repository root):
#   bash scripts/verify_database_sql_import.sh
#
# Environment: passed through to import_database_split.sh (MYSQL_HOST, MYSQL_PORT, …).
# CI sets MYSQL_PORT=3306 in smoke.yml; Dunebox default is 3307.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

exec bash "${ROOT}/scripts/import_database_split.sh"