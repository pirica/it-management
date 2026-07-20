#!/usr/bin/env bash
# Import db/ split bundle and assert the live schema matches expectations.
# Used by CI (GitHub Actions MySQL service) and local verification after schema edits.
#
# Usage (repository root):
#   bash scripts/verify_database_sql_import.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

exec bash "${ROOT}/scripts/import_database_split.sh"
