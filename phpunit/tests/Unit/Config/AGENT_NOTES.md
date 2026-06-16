# AGENT_NOTES.md - phpunit/tests/Unit/Config

## 1. Module Purpose
Tests for configuration loading, constants, and bootstrap behaviour.

## 4. Business Rules (Critical for Agents)
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
