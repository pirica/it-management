# AGENT_NOTES.md - phpunit/tests/Unit

## 1. Module Purpose
PHPUnit and procedural unit tests organised by concern (Config, CRUD, Database, Modules, MultiTenancy, Scripts, Security).

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- Test classes end in `Test.php` or `*.unittest.php`.
- **`*Test.php` files:** extend `PHPUnit\Framework\TestCase`; use `test*` methods — no top-level execution or `echo` (PHPUnit loads all matching files).
- Avoid `exit()` in included production scripts during tests — halts `scripts/run_tests.php`.
- Each `Modules/<Name>/` folder maps to `modules/<snake_case>/` (see that module's `AGENT_NOTES.md`).
- DB-dependent tests must **`markTestSkipped`** when `$conn` is unavailable.

## 7. File Structure
- **Config/** — configuration/bootstrap tests.
- **CRUD/** — generic CRUD contract tests.
- **Database/** — schema/seed tests.
- **Modules/** — per-module tests (mirror of `modules/`).
- **MultiTenancy/** — company_id isolation tests.
- **Scripts/** — script/audit tool tests (`BypassLoginTest`, `ItmScriptTestUserTest`, `ReproAuditDisclosureTest`, `check_script_disposable_employees.unittest.php`, etc.).
- **Security/** — CSRF/SQLi-related tests.

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 11. Examples of Safe Code Patterns

### Run
```bash
php scripts/run_tests.php
php scripts/run_tests.php --coverage
```

## 12. Module Owner Notes (Optional)
Run: `php scripts/run_tests.php`. Parent: `phpunit/tests/AGENT_NOTES.md`. Coverage guardrails: `scripts/SCRIPTS.md`.
