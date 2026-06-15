# AGENT_NOTES.md - tests/Unit/Scripts

## 1. Module Purpose
Tests for maintenance/audit scripts under `scripts/`.

## 4. Business Rules (Critical for Agents)
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.

## 12. Module Owner Notes (Optional)
Parent: `tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
