# AGENT_NOTES.md - scripts/data

## 1. Module Purpose
Static data files consumed by audit and compliance scripts (allowlists, baselines, excluded module lists).

## 7. File Structure
- **index_table_compliance_baseline.txt** — grandfather list for `check_index_table_compliance.php` (legacy gaps until fixed). Intentional no-import / no-Actions modules are handled by the checker (`data-itm-no-import-excel`, no Actions column) — do not baseline those as failures.
- **multi_tenant_leak_allowlist.json** — known exceptions for tenant leak audits.
- **fields_missing_reviewed.json** — reviewed bespoke `[SKIP][fail]` gate lines for `fields_missing.php` (manifest: `fields_missing_reviewed.php`). Matching failures print as `[SKIP][fail][reviewed]` (informational only — same exit semantics as `[SKIP][fail]`).

  **JSON shape (version 1):**

  ```json
  {
    "version": 1,
    "description": "…",
    "modules": {
      "<module_slug>": {
        "reviewed_at": "YYYY-MM-DD",
        "reason": "Why the gate failure is intentional",
        "checks": [
          {
            "check": "Search",
            "code": "bespoke_list_ui_search",
            "note": "Optional human note (audit detail text)"
          }
        ]
      }
    }
  }
  ```

  **Matching rules:** each `checks[]` row matches a bespoke gate failure when `module_slug` matches and either `code` equals the failure `code` (for example `bespoke_list_ui_search`) or `check` equals the gate label before `NOT OK` in the message (for example `Search`). Add a row per intentional `[SKIP][fail]` line — do not edit `itm_fields_missing_report.php` to silence checks. Validate with `php scripts/fields_missing_reviewed.php`.
- **ui_configuration_excluded_modules.txt** / **ui_configuration_excluded_prefixes.txt** — gate-excluded modules for UI configuration coverage. Listed slugs still run every check but print as `[n/a][pass]`, `[n/a][fail]`, or `[n/a][n/a]` (informational only — exit `2` only on gated `[fail]`). Keep aligned with bespoke slugs in `docs/list_bespoke_UI.txt` that do not use the flattened CRUD list contract (for example `employee_sidebar_preferences` — read-only list without bulk/import/delete entry files). Default header shows gated vs gate-excluded counts; `--list-excluded` / `?list_excluded=1` prints gate-excluded slug names (avoids mistaking a slug in the header for a failure).
- **scripts-matrix-destroy-log.md** — append-only destroy→fresh-clone log for blanket `scripts/*` verification (`SCRIPTS_TEST_MATRIX.md` protocol).
- **scripts_errors.txt** — latest safe scripts matrix run report (tiers 1–3): counts plus A–Z lists for Passed, Skipped, Excluded, Covered, and Failures with root-cause notes. Regenerated after a matrix run; not a product runtime file.

## 4. Business Rules (Critical for Agents)
- Update these files when intentionally excluding a module from an audit — do not silence checks by editing the checker alone.
- **`fields_missing_reviewed.json`:** add reviewed rows when a bespoke module intentionally fails Search/Sort/Pagination (or other bespoke gate checks) and the team has signed off. Update the matching module `AGENT_NOTES.md` with the same rationale.
- `index.html` blocks directory listing.

## 10. Common Pitfalls
- JSON must be UTF-8 without BOM for `json_decode()` in scripts. [Cursor-Valid]
