# AGENT_NOTES.md - Ops Report (PHPUnit)

## 1. Module Purpose
Unit tests for `modules/ops_report/` — daily report CRUD and D-2 edit-lock rules.

## 2. Key Tables
- `ops_report` and child tables (see `modules/ops_report/AGENT_NOTES.md`).

## 7. File Structure
- `OpsReportTest.php` — insert/read/update/delete on `ops_report`.
- `OpsReportPermissionsTest.php` — pure PHP date-window checks (today, yesterday editable; D-2 locked).

## 12. Module Owner Notes (Optional)
Run: `php scripts/run_tests.php --filter OpsReport`

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
