# AGENT_NOTES.md - phpunit/tests/Unit/Security

## 1. Module Purpose
CSRF, SQLi, and security guardrail tests.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `users` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_user.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.
- **Windows:** `SecurityFixesTest` must not use Unix-only shell redirects such as `2>/dev/null` (cmd.exe prints "The system cannot find the path specified."). Use `exec()` with `2>&1` via `runPhpScriptFile()`.

## 7. File Structure
- **VulnerabilityVerificationTest.php** — June 2026 security review repro tests; uses disposable users via `itm_script_test_user_create()` (not seed Admin id 1) for notes IDOR, ZIP traversal, and audit reset-token disclosure checks.
- **SecurityFixesTest.php** — subprocess verification of fixed security paths.

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
