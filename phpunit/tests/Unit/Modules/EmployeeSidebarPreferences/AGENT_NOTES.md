# AGENT_NOTES.md - EmployeeSidebarPreferences Tests

## 1. Module Purpose
Unit/regression tests for `modules/employee_sidebar_preferences/`.

## 3. Required Relationships
- Production docs: `modules/employee_sidebar_preferences/AGENT_NOTES.md` (when present).
- Database fixtures: `database.sql`.

## 7. File Structure
- `*Test.php` / `*.unittest.php` — test classes for this module.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test employees:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed employee id `1`. See `scripts/SCRIPTS.md` → Disposable script test employees.
