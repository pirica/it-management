# AGENT_NOTES.md - scripts/data

## 1. Module Purpose
Static data files consumed by audit and compliance scripts (allowlists, baselines, excluded module lists).

## 7. File Structure
- **index_table_compliance_baseline.txt** — grandfather list for `check_index_table_compliance.php` (legacy gaps until fixed). Intentional no-import / no-Actions modules are handled by the checker (`data-itm-no-import-excel`, no Actions column) — do not baseline those as failures.
- **multi_tenant_leak_allowlist.json** — known exceptions for tenant leak audits.
- **fields_missing_reviewed.json** — reviewed bespoke `[SKIP][fail]` gate lines for `fields_missing.php` (manifest: `fields_missing_reviewed.php`). **Catalog (What/How):** `scripts/scripts.php`. **JSON schema + workflow:** `scripts/SCRIPTS.md` → *fields_missing reviewed exceptions* (do not duplicate schema here).
- **ui_configuration_excluded_modules.txt** / **ui_configuration_excluded_prefixes.txt** — gate-excluded modules for UI configuration coverage. Listed slugs still run every check but print as `[n/a][pass]`, `[n/a][fail]`, or `[n/a][n/a]` (informational only — exit `2` only on gated `[fail]`). Optional `[reviewed]` suffix when listed in **`ui_configuration_reviewed.json`** (manifest: `ui_configuration_reviewed.php`). Keep aligned with bespoke slugs in `docs/list_bespoke_UI.txt` that do not use the flattened CRUD list contract (for example `employee_sidebar_preferences` — read-only list without bulk/import/delete entry files). Default header shows gated vs gate-excluded counts; `--list-excluded` / `?list_excluded=1` prints gate-excluded slug names (avoids mistaking a slug in the header for a failure).
- **ui_configuration_reviewed.json** — reviewed gate-excluded UI configuration lines for `check_ui_configuration_coverage.php` (manifest: `ui_configuration_reviewed.php`). **Catalog (What/How):** `scripts/scripts.php`. **JSON schema + workflow:** `scripts/SCRIPTS.md` → *ui_configuration reviewed exceptions* (do not duplicate schema here).
- **scripts-matrix-destroy-log.md** — append-only destroy→fresh-clone log for blanket `scripts/*` verification (`SCRIPTS_TEST_MATRIX.md` protocol).
- **scripts_errors.txt** — latest safe scripts matrix run report (tiers 1–3): counts plus A–Z lists for Passed, Skipped, Excluded, Covered, and Failures with root-cause notes. Regenerated after a matrix run; not a product runtime file.

## 4. Business Rules (Critical for Agents)
- Update these files when intentionally excluding a module from an audit — do not silence checks by editing the checker alone.
- **`fields_missing_reviewed.json`:** add reviewed rows when a bespoke module intentionally fails Search/Sort/Pagination (or other bespoke gate checks) and the team has signed off. Update the matching module `AGENT_NOTES.md` with the same rationale. Enforce hygiene in CI with `php scripts/fields_missing.php --strict-gate` (fails on unreviewed `[SKIP][fail]` only).
- **`ui_configuration_reviewed.json`:** add reviewed rows when a gate-excluded module intentionally prints `[n/a][pass|fail|n/a]` for a UI configuration check. Update the matching module `AGENT_NOTES.md`. Validate with `php scripts/ui_configuration_reviewed.php`.
- `index.html` blocks directory listing.

## 10. Common Pitfalls
- JSON must be UTF-8 without BOM for `json_decode()` in scripts. [Cursor-Valid]
