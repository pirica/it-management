# AGENT_NOTES.md - phpunit/tests/Unit/Scripts

## 1. Module Purpose
Tests for maintenance/audit scripts under `scripts/`.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.
- **`BypassLoginTest`:** skips when `$conn` is unavailable; requires MySQL for full run.

## 7. File Structure
- **ApiFunctionsTest.php** — collector helpers in `scripts/api.php` (module imports, Explorer actions, IDF endpoints, switch-port API catalog, api-examples list).
- **BypassLoginTest.php** — includes `scripts/bypass_login.php` in-process; verifies session keys and Admin role (`itm_is_admin()` gate on seed Admin user).
- **CompanyModuleAccessVerifyTest.php** — subprocess CLI run of `scripts/verify_company_module_access.php` (sidebar discovery probes).
- **ItmScriptTestUserTest.php** — unit tests for `scripts/lib/itm_script_test_employee.php` (create, snapshot, restore, delete, `create_session_actor`; create/delete clear stale `@app_employee_id`).
- **ItmScriptBootstrapTest.php** — `scripts/lib/itm_script_bootstrap.php` disposable session detection, `itm_script_with_test_session_context()` Admin restore, `itm_script_sync_csrf_to_browser_session_backup()`, and `itm_script_finish_browser_isolated_session()` `csrf_token` merge on browser isolation shutdown.
- **ReproAuditDisclosureTest.php** — subprocess `scripts/repro_audit_disclosure.php`; asserts seed Admin `reset_token*` unchanged and no leftover `script-*` users. Output guard uses `\buser ID 1\b` so disposable IDs like 108 do not false-fail.
- **check_script_disposable_employees.unittest.php** — subprocess `scripts/check_script_disposable_employees.php` static guard (exit 0).
- **FieldsMissingBespokeGateTest.php** — bespoke gate contract checks; reviewed registry (`scripts/data/fields_missing_reviewed.json`, `[SKIP][fail][reviewed]` labels); strict gate exit (`--strict-gate` / unreviewed `[SKIP][fail]`); bookmarks in-memory Search/Sort via `bkm_query_bookmarks_for_list()` (`testBookmarksBespokeGatePassesSearchAndSort`); index list audit meta hidden via `itm_crud_is_list_hidden_audit_field()` (`testCableColorsBespokeGateHidesAuditMetaOnIndexList`); view audit meta required via `itm_fields_missing_module_view_covers_audit_meta_field()` (`testCompaniesBespokeGateFlagsMissingViewAuditMeta`, `testBespokeViewAuditMetaRejectsRawAliasHelperOnly`, `testBespokeViewAuditMetaPassesWithViewColumnsLoop`).

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
