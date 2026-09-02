# AGENT_NOTES.md - TicketStatuses Tests

## 1. Module Purpose
Unit/regression tests for `modules/ticket_statuses/`.

## 3. Required Relationships
- Production docs: `modules/ticket_statuses/AGENT_NOTES.md` (when present).
- Database fixtures: `db/02_data.sql`.

## 7. File Structure
- `*Test.php` / `*.unittest.php` — test classes for this module.

## 10. Common Pitfalls

- `ticket_statuses` enforces `UNIQUE (company_id, name)`; use per-run unique values for both create and update names in `testCRUD` so orphan rows from failed runs do not block INSERT/UPDATE.
- Register `tearDown()` cleanup for the inserted row id when the test may fail before the hard `DELETE` step.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing module bugs; list new test commands in PR descriptions.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
