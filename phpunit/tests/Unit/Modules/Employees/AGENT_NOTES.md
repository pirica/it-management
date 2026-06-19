# AGENT_NOTES.md - Employees Tests

## 1. Module Purpose
Unit/regression tests for `modules/employees/`.

## 3. Required Relationships
- Production docs: `modules/employees/AGENT_NOTES.md` (when present).
- Database fixtures: `database.sql`.

## 7. File Structure
- `SafeImportTest.php` — asserts import does not delete rows missing from payload; counts only fixture `work_email` values (`keep@example.com`, `other@example.com`) so protected seed employees are excluded.
- `*Test.php` / `*.unittest.php` — other test classes for this module.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
