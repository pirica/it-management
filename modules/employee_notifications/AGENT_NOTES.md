# AGENT_NOTES.md - Employee Notifications

## 1. Module Purpose

Per-employee in-app notification inbox (metadata rows created by `itm_notify_employee()` and module emitters). Complements the header bell dropdown.

## 2. Key Tables

- **employee_notifications** — `module_slug`, `record_id`, `title`, `body`, `action_url`, `is_read`, `read_at`.

## 4. Business Rules (Critical for Agents)

- List/view show `is_read` as emoji indicators: ✅ read (`1`), ❌ unread (`0`) — not raw `0`/`1`.
- **List performance:** default sort `created_at DESC`; skip company-wide `COUNT(*)` (scoped to session `employee_id` only). Header SSE must release the PHP session lock (`itm_release_session_lock()` in `modules/notifications/api.php`) or inbox navigation blocks until the stream ends.
- **List queries must filter** `employee_id = $_SESSION['employee_id']` (inbox is private to the signed-in user).
- `body` must remain metadata — no vault/plaintext private content.
- **Add sample data:** empty-state button when the signed-in employee has zero inbox rows (`$totalRows === 0`). POST gate uses `itm_seed_count_employee_inbox_live_notifications()` (not company-wide count). Seeder: `itm_seed_insert_employee_notifications_sample_rows()` in `includes/itm_sample_data_seed.php` — stamps session `employee_id`, resolves `record_id` / `action_url` per `module_slug`, templates in `db/02_data_sample.sql` (works for all five seed companies). **`$hasCompany` must be resolved from schema `$columns` (not UI-filtered `$fieldColumns`) because this module hides `company_id` from forms/list.**

## 5. UI Behavior Requirements

- Flattened CRUD scaffold; bulk delete when row count ≥ `records_per_page`.
- Admin sidebar: **Admin → 🔔 Employee Notifications**.

## 8. Multi-Tenant Rules

- `company_id` from session; `employee_id` locked to session employee on list.

## 9. Audit Logging Requirements

- `trg_employee_notifications_audit_*` in `db/03_triggers.sql`.

## 12. Module Owner Notes

- Dispatcher: `includes/itm_employee_notifications.php`.
- Header API: `modules/notifications/api.php`.
- Doc: `docs/NOTIFICATIONS.md`.
- Regression: `php scripts/verify_employee_notifications.php`.
