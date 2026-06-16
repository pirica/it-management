# AGENT_NOTES.md - phpunit/tests/Unit/Scripts

## 1. Module Purpose
Tests for maintenance/audit scripts under `scripts/`.

## 4. Business Rules (Critical for Agents)
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.
- **`BypassLoginTest`:** skips when `$conn` is unavailable; requires MySQL for full run.

## 7. File Structure
- **BypassLoginTest.php** — includes `scripts/bypass_login.php` in-process; verifies session keys and Admin role.

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
