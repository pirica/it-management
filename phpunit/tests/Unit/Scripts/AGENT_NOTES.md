# AGENT_NOTES.md - phpunit/tests/Unit/Scripts

## 1. Module Purpose
Tests for maintenance/audit scripts under `scripts/`.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `users` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_user.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.
- **`BypassLoginTest`:** skips when `$conn` is unavailable; requires MySQL for full run.

## 7. File Structure
- **BypassLoginTest.php** — includes `scripts/bypass_login.php` in-process; verifies session keys and Admin role.
- **CompanyModuleAccessVerifyTest.php** — subprocess CLI run of `scripts/verify_company_module_access.php` (sidebar discovery probes).
- **ItmScriptTestUserTest.php** — unit tests for `scripts/lib/itm_script_test_user.php` (create, snapshot, restore, delete).
- **ReproAuditDisclosureTest.php** — subprocess `scripts/repro_audit_disclosure.php`; asserts seed Admin `reset_token*` unchanged and no leftover `script-*` users.
- **check_script_disposable_users.unittest.php** — subprocess `scripts/check_script_disposable_users.php` static guard (exit 0).

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
