# AGENT_NOTES.md - phpunit/tests/Unit/Security

## 1. Module Purpose
CSRF, SQLi, and security guardrail tests.

## 4. Business Rules (Critical for Agents)
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.
- **Windows:** `SecurityFixesTest` must not use Unix-only shell redirects such as `2>/dev/null` (cmd.exe prints "The system cannot find the path specified."). Use `exec()` with `2>&1` via `runPhpScriptFile()`.

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
