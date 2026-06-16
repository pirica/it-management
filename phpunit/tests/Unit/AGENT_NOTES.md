# AGENT_NOTES.md - phpunit/tests/Unit

## 1. Module Purpose
PHPUnit and procedural unit tests organised by concern (Config, CRUD, Database, Modules, MultiTenancy, Scripts, Security).

## 4. Business Rules (Critical for Agents)
- Test classes end in `Test.php` or `*.unittest.php`.
- Avoid `exit()` in tests — halts `scripts/run_tests.php`.
- Each `Modules/<Name>/` folder maps to `modules/<snake_case>/` (see that module's `AGENT_NOTES.md`).

## 7. File Structure
- **Config/** — configuration/bootstrap tests.
- **CRUD/** — generic CRUD contract tests.
- **Database/** — schema/seed tests.
- **Modules/** — per-module tests (mirror of `modules/`).
- **MultiTenancy/** — company_id isolation tests.
- **Scripts/** — script/audit tool tests.
- **Security/** — CSRF/SQLi-related tests.

## 12. Module Owner Notes (Optional)
Run: `php scripts/run_tests.php`. Parent: `phpunit/tests/AGENT_NOTES.md`.
