# AGENT_NOTES.md - Employees Tests

## 1. Module Purpose
Unit/regression tests for `modules/employees/`.

## 3. Required Relationships
- Production docs: `modules/employees/AGENT_NOTES.md` (when present).
- Database fixtures: `database.sql`.

## 7. File Structure
- `SafeImportTest.php` — asserts import does not delete rows missing from payload; runs `modules/employees/index.php` import via `ItmModuleIsolatedTestTrait::runIsolatedModule()` (subprocess — avoids redeclare fatal).
- `EmployeesBespokeTest.php` — clear-table soft-delete: live rows (`deleted_at IS NULL`) go to zero, soft-deleted row keeps `active=0` + `deleted_at`, detach clears `employee_system_access`, and clear succeeds when non-detached inbound FKs remain (e.g. `forecast_revisions.submitted_by`).
- `*Test.php` / `*.unittest.php` — other test classes for this module.

## 10. Common Pitfalls

[Confirmed] Clear-table assertions must count **live** employees (`deleted_at IS NULL`), not `COUNT(*)` on the table — soft-delete leaves audit rows.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
