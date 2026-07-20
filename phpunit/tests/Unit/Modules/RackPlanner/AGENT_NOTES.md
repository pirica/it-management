# AGENT_NOTES.md - RackPlanner Tests

## 1. Module Purpose
Unit/regression tests for `modules/rack_planner/`.

## 3. Required Relationships
- Production docs: `modules/rack_planner/AGENT_NOTES.md`.
- Named CLI/browser regression: `php scripts/verify_rack_planner.php` (catalog: `scripts/scripts.php`).
- Database fixtures: `database.sql`.

## 7. File Structure
- `*Test.php` / `*.unittest.php` — test classes for this module.

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
