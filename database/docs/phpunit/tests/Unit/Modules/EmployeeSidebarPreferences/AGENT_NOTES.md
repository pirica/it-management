# AGENT_NOTES.md - EmployeeSidebarPreferences Tests

## 1. Module Purpose
Unit/regression tests for `modules/employee_sidebar_preferences/`.

## 3. Required Relationships
- Production docs: `modules/employee_sidebar_preferences/AGENT_NOTES.md`.
- Database fixtures: `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`.

## 4. Business Rules (Critical for Agents)
- **Disposable script test employees:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed employee id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- Module UI is **read-only** (no `create.php`, `edit.php`, or `delete.php`); `EmployeeSidebarPreferencesTest` covers direct DB insert/update/delete only.
- Bespoke UI gate coverage: `phpunit/tests/Unit/Scripts/FieldsMissingBespokeGateTest.php` (`employee_sidebar_preferences` slug).

## 7. File Structure
- `EmployeeSidebarPreferencesTest.php` — DB-level CRUD smoke for `employee_sidebar_preferences`.
- `FieldsMissingBespokeGateTest.php` (under `Unit/Scripts/`) — bespoke gate contract for this slug.

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.
