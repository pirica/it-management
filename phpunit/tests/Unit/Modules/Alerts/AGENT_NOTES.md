# AGENT_NOTES.md - Alerts Tests

## 1. Module Purpose
Unit/regression tests for `modules/alerts/`.

## 3. Required Relationships
- Production docs: `modules/alerts/AGENT_NOTES.md` (when present).
- Database fixtures: `database.sql`.

## 7. File Structure
- `AlertsTest.php` — CRUD/visibility; seeds extra employees via `itm_script_test_employee_create()` when fewer than three rows exist (never `employees.active`). Global (unassigned) alert fixtures omit `assigned_to_employee_id` instead of binding NULL as integer.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
