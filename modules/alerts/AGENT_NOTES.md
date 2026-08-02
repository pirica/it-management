# AGENT_NOTES.md - Alerts

## 1. Module Purpose
This module manages notifications and alerts within the system. It supports both "Global" alerts (visible to everyone in a company) and "Private" alerts (assigned to a specific user).

## 2. Key Tables
- **alerts** — main storage for alert messages, timing, and assignments.

## 3. Required Relationships
- **alerts** → depends on **companies** (via `company_id`).
- **alerts** → depends on **event_categories** (via `category_id`).
- **alerts** → depends on **users** (via `assigned_to_employee_id` for private alerts and `created_by`).

## 4. Business Rules (Critical for Agents)
- **Visibility Logic**:
    - **Global Alerts**: Records where `assigned_to_employee_id IS NULL` are visible to all users in the company.
    - **Private Alerts**: Records where `assigned_to_employee_id = $user_id` are visible only to that user and the creator.
- **Visibility Helpers**: Always use `includes/alerts_visibility.php` to generate SQL conditions for visibility. List counts and **Add sample data** both use `itm_alerts_build_scoped_where_sql()` (company + visibility + `deleted_at IS NULL`) so an empty list matches the sample-data gate.
- **ICS Support**: Supports importing events from ICS files.

## 5. UI Behavior Requirements
- **Contextual Visibility**: The list view must filter alerts based on the logged-in user's identity and the global/private rules.
- **CSRF Protection**: All forms and actions must be protected by CSRF tokens.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import of alerts.

## 7. File Structure
- **index.php** — list view with visibility filtering and bulk actions.
- **create.php**, **edit.php**, **view.php** — standard CRUD wrappers.
- **delete.php** — handles deletion.

## 8. Multi-Tenant Rules
- All queries must be scoped by `company_id`.
- Use the helper functions in `includes/alerts_visibility.php` to ensure tenant and user-level isolation.

## 9. Audit Logging Requirements
- Mutations are logged via database triggers (`trg_alerts_audit_*`) into `audit_logs`.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via `itm_crud_stamp_create_audit()` / `itm_crud_stamp_update_audit()`; before prepared INSERT/UPDATE bind, call `itm_crud_normalize_bind_values_for_persist($data, $fieldColumns)` so empty audit timestamps bind as SQL `NULL` (not `''`, which triggers MySQL 1292 *Date has an invalid date or time*); delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Add sample data gate:** must count visible live rows (`itm_alerts_build_scoped_where_sql()`), not all `company_id` rows — otherwise private alerts for other users or soft-deleted rows block seeding while the list looks empty. [Cursor-Fixed]
- **Sample data visibility:** `db/02_data_sample.sql` and `itm_seed_apply_alerts_sample_row_defaults()` force `assigned_to_employee_id = NULL` (global alert) and stamp `created_by` from the session employee (or first tenant employee). Rows with a non-null assignee and `created_by = NULL` exist in the DB but are hidden from other users — fix existing rows with `UPDATE alerts SET assigned_to_employee_id = NULL, created_by = <employee_id> WHERE …`. [Cursor-Fixed]
- **Leaking Private Alerts**: Failing to include the `assigned_to_employee_id` check in custom queries can leak private notifications to other users. [Cursor-Valid]
- **Date/Time Formatting**: Ensure `start_datetime` and `end_datetime` are handled correctly for SQL and UI display. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT with Visibility
```php
require_once '../../includes/alerts_visibility.php';
$visibilitySql = itm_alerts_visibility_sql('a');
$sql = "SELECT a.* FROM alerts a WHERE a.company_id = ? AND ($visibilitySql)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO alerts (company_id, title, description, assigned_to_employee_id, created_by) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issii", $companyId, $title, $description, $assignedId, $creatorId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The alerts module is closely related to the Calendar/Events module. Ensure consistency in category usage.
## Share (temporary QR / code)
- **Capable:** `itm_qr_share_capable_module_slugs()`.
- **UI:** Share buttons on index.php inline view block.
- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.
- **Doc:** `docs/CRUD_RECORD_SHARE.md`.
