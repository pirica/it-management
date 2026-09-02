# AGENT_NOTES.md - phpunit/tests/Unit/Security

## 1. Module Purpose
CSRF, SQLi, and security guardrail tests.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.
- **Windows:** `SecurityFixesTest` must not use Unix-only shell redirects such as `2>/dev/null` (cmd.exe prints "The system cannot find the path specified."). Use `exec()` with `2>&1` via `runPhpScriptFile()`.

## 7. File Structure
- **ExplorerPathBypassTest.php** — `./Private` ACL regression for `get_full_path()` after `explorer_normalize_relative_path()`.
- **ExplorerZipSlipTest.php** — Zip Slip blocked by `explorer_extract_zip_safely()`.
- **AttemptsDataLeakTest.php** — login attempt identifier redaction via `itm_normalize_login_attempt_identifier()`.
- **SelectOptionsBypassTest.php** — `companies` blocked from Select Options quick-add whitelist.
- **VulnerabilityVerificationTest.php** — June 2026 security review regression tests (assert remediated behaviour); uses disposable users via `itm_script_test_employee_create()` for Notes ZIP traversal, Notes IDOR view, audit reset-token omission, RBAC delete on Expenses, users tenant scoping, and employee_companies admin gate checks. `createDisposableUser()` honours optional `company_id` in options (defaults to company `1`).
- **VaultSecurityTest.php** — `itm_vault_reencrypt_password_entries()` rollback contract for vault master key changes (unchanged ciphertext + old-key decrypt); disposable user via `itm_script_test_employee_create()` (no `employees.active` column). Do not `assertFalse(itm_decrypt(..., wrongKey))` — OpenSSL often returns non-false garbage for a wrong AES key.
- **TotpTest.php** — secret generation, encrypt/decrypt round-trip, employee-row verification, disabled skip path; disposable user via `itm_script_test_employee_create()`.
- **SecurityFixesTest.php** — subprocess verification of fixed security paths; role-escalation and sensitive-import cases seed attackers via `itm_script_test_employee_create()` (no `employees.active` column).
- **PentestReportTest.php** — subprocess runner for `scripts/verify_pentest_report.php` (`docs/report.md` ITM-PENTEST-001–022; expects `[PASS]`/`[INFO]` labels; `[INFO]` ITM-PENTEST-004, 004-mitigation, 005; asserts ITM-PENTEST-007, ITM-PENTEST-008, ITM-PENTEST-009, ITM-PENTEST-010, ITM-PENTEST-011, ITM-PENTEST-012, ITM-PENTEST-013, ITM-PENTEST-014, ITM-PENTEST-015, and ITM-PENTEST-016 remediated).
- **RequestPasswordApprovalTest.php** — `itm_request_password_approval_secret()` env helper; approver-bound HMAC sign/verify.

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
