# AGENT_NOTES.md - scripts/data

## 1. Module Purpose
Static data files consumed by audit and compliance scripts (allowlists, baselines, excluded module lists).

## 7. File Structure
- **index_table_compliance_baseline.txt** — grandfather list for `check_index_table_compliance.php` (legacy gaps until fixed). Intentional no-import / no-Actions modules are handled by the checker (`data-itm-no-import-excel`, no Actions column) — do not baseline those as failures.
- **multi_tenant_leak_allowlist.json** — known exceptions for tenant leak audits.
- **ui_configuration_excluded_modules.txt** / **ui_configuration_excluded_prefixes.txt** — modules skipped by UI configuration coverage script. Keep aligned with bespoke slugs in `docs/list_bespoke_UI.txt` that do not use the flattened CRUD list contract. `check_ui_configuration_coverage.php` prints skip **counts** by default; `--list-excluded` / `?list_excluded=1` prints slug names (avoids mistaking `calendar` in the header for a failure).
- **scripts-matrix-destroy-log.md** — append-only destroy→fresh-clone log for blanket `scripts/*` verification (`SCRIPTS_TEST_MATRIX.md` protocol).
- **scripts_errors.txt** — latest safe scripts matrix run report (tiers 1–3): counts plus A–Z lists for Passed, Skipped, Excluded, Covered, and Failures with root-cause notes. Regenerated after a matrix run; not a product runtime file.

## 4. Business Rules (Critical for Agents)
- Update these files when intentionally excluding a module from an audit — do not silence checks by editing the checker alone.
- `index.html` blocks directory listing.

## 10. Common Pitfalls
- JSON must be UTF-8 without BOM for `json_decode()` in scripts. [Cursor-Valid]
