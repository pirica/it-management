# AGENT_NOTES.md - phpunit/tests/Unit/Database

## 1. Module Purpose
Schema, seed, `db/`, and split-file parity tests.

## 4. Business Rules (Critical for Agents)
- **Canonical SQL:** `db/01_schema.sql`, `db/02_data.sql`, `db/03_triggers.sql` — edit directly; path helpers in `includes/itm_database_sql_source.php`.
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- Keep tests aligned with production module contracts in `AGENTS.md`.
- Update tests when changing CSRF, SQLi, or tenant scoping in the target code.

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/Unit/AGENT_NOTES.md`. Run suite via `php scripts/run_tests.php`.
