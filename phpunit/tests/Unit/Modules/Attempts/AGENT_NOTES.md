# AGENT_NOTES.md - Attempts Tests

## 1. Module Purpose
Unit/regression tests for `modules/attempts/`.

## 3. Required Relationships
- Production docs: `modules/attempts/AGENT_NOTES.md` (when present).
- Database fixtures: `database.sql`.

## 7. File Structure
- `*Test.php` / `*.unittest.php` — test classes for this module.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `users` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_user.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
