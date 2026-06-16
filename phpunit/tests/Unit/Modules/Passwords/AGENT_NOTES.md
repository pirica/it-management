# AGENT_NOTES.md - Passwords Tests

## 1. Module Purpose
Unit/regression tests for `modules/passwords/`.

## 3. Required Relationships
- Production docs: `modules/passwords/AGENT_NOTES.md` (when present).
- Database fixtures: `database.sql`.

## 7. File Structure
- `PasswordsTest.php` — DB CRUD unit tests.
- `PasswordsFunctionalTest.php` — PHPUnit class exercising `ajax_handler.php` (no top-level echo; safe with HTML coverage).

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.
