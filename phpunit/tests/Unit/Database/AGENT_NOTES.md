# AGENT_NOTES.md - phpunit/tests/Unit/Database

## 1. Module Purpose
Schema, seed, and database.sql consistency tests.

## 4. Business Rules (Critical for Agents)
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
