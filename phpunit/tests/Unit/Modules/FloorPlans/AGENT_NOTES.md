# AGENT_NOTES.md - FloorPlans Tests

## 1. Module Purpose
Unit/regression tests for `modules/floor_plans/`.

## 3. Required Relationships
- Production docs: `modules/floor_plans/AGENT_NOTES.md` (when present).
- Database fixtures: `database.sql`.

## 7. File Structure
- `FloorPlansTest.php` — CRUD on `floor_plans` and folder create via `parent_folder_id`.
- `*Test.php` / `*.unittest.php` — other test classes for this module.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `users` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_user.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
