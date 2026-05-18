#!/usr/bin/env bash
# Minimal smoke test runner for CI and local development.
#
# Runs:
#   1. php -l on every *.php file in the repository
#   2. scripts/check_csrf_coverage.php
#   3. scripts/check_sql_injection_coverage.php
#   4. scripts/check_index_table_compliance.php
#   5. scripts/check_ui_configuration_coverage.php (optional; see SMOKE_SKIP_UI_CONFIG)
#
# Usage (from repository root):
#   bash scripts/smoke_test.sh
#
# Environment:
#   PHP_BIN=php              PHP executable (default: php)
#   SMOKE_SKIP_UI_CONFIG=1   Skip UI layout audit (CI: other legacy gaps; is_* façades are excluded in the checker)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php}"

echo "==> Smoke tests (root: ${ROOT})"
echo "==> PHP binary: ${PHP_BIN}"
"$PHP_BIN" -v
echo

echo "==> Step 1/5: PHP syntax lint (php -l)"
lint_failed=0
lint_count=0
while IFS= read -r -d '' file; do
  lint_count=$((lint_count + 1))
  if ! "$PHP_BIN" -l "$file" >/dev/null 2>&1; then
    echo "FAIL: ${file#$ROOT/}"
    "$PHP_BIN" -l "$file" || true
    lint_failed=1
  fi
done < <(find "$ROOT" -name '*.php' -not -path '*/.git/*' -print0)

if [[ "$lint_failed" -ne 0 ]]; then
  echo "Syntax lint failed."
  exit 1
fi
echo "OK: ${lint_count} PHP file(s) passed syntax lint."
echo

run_check() {
  local script_name="$1"
  echo "==> ${step_label}: ${script_name}"
  "$PHP_BIN" "$ROOT/scripts/${script_name}"
  echo
}

step_label="Step 2/5"
run_check "check_csrf_coverage.php"

step_label="Step 3/5"
run_check "check_sql_injection_coverage.php"

step_label="Step 4/5"
run_check "check_index_table_compliance.php"

step_label="Step 5/5"
if [[ "${SMOKE_SKIP_UI_CONFIG:-0}" == "1" ]]; then
  echo "==> Step 5/5: check_ui_configuration_coverage.php (skipped — SMOKE_SKIP_UI_CONFIG=1)"
else
  run_check "check_ui_configuration_coverage.php"
fi

echo "==> All smoke tests passed."
