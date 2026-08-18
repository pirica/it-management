# AGENT_NOTES.md - Appointment Business Hours

## 1. Module Purpose

Flattened CRUD for **`appointment_business_hours`** — one row per weekday (`day_of_week` 0–6) per company: open/close times, closed flag, in-person/remote modality flags, optional `allowed_types_json`, and standard audit columns. Used by appointment booking (`includes/itm_appointment.php`) and mirrored in the **Appointment Settings** hub (`modules/appointment_settings/`).

## 2. Key Tables

- **appointment_business_hours** — weekday schedule + modality flags (`is_closed`, `allows_in_person`, `allows_remote`, `allowed_types_json`)

## 3. Required Relationships

- **appointment_business_hours** → **companies** (`company_id`, `ON DELETE CASCADE`)
- UNIQUE (`company_id`, `day_of_week`) — one row per weekday per tenant

## 4. Business Rules (Critical for Agents)

- `day_of_week`: 0 = Sunday … 6 = Saturday (schema comment).
- `open_time` / `close_time` nullable when `is_closed = 1`.
- Booking reads these rows through shared appointment helpers — keep tenant `company_id` on all writes.
- Defaults for missing weekdays are seeded by `itm_appointment_settings_ensure_company_config()` when opening **Appointment Settings**, not necessarily this standalone module.

## 5. UI Behavior Requirements

### Flattened CRUD (`index.php`)

- Hide **`company_id`** from list, view, and forms (`$hideCompanyIdTables` includes `appointment_business_hours`).
- **`active`**: list/view use Active/Inactive badges (`badge-success` / `badge-danger`).
- **`is_closed`**, **`allows_in_person`**, **`allows_remote`**: list/view use ✅/❌ in `cr_render_cell_value()`; create/edit use `itm-checkbox-control` double-label checkboxes for all `tinyint(1)` fields.
- `$displayFieldColumns = $uiColumns` before search block.
- Bulk delete when `$totalRows >= $perPage`; Actions column uses `itm-actions-cell` + `data-itm-actions-origin="1"`.
- Import: `data-itm-db-import-endpoint="index.php"` on list table.
- **CSRF:** `cr_require_valid_csrf_token()` on POST; forms use `cr_get_csrf_token()`.

### Wrappers

- **edit.php**, **view.php**, **list_all.php** — set `$crud_action` then `require 'index.php'` (inherit list/view rendering from index).
- **create.php** — standalone duplicate helpers; keep `cr_render_cell_value()` and `$hideCompanyIdTables` aligned with **index.php**.

## 6. API Actions

- **import_excel_rows** — JSON POST on `index.php` (`Content-Type: application/json`, CSRF in body).

## 7. File Structure

- **index.php** — list, view, create/edit/delete handlers, `cr_render_cell_value()`
- **create.php**, **edit.php**, **view.php**, **list_all.php** — action wrappers (edit/view/list_all → index)
- **delete.php** — bulk/single soft-delete POST handler
- **join.php** — CRUD record share join page

## 8. Multi-Tenant Rules

- All queries and inserts scope by session `company_id`.
- Never show or edit `company_id` in UI (hidden input on create/edit when `$hasCompany`).

## 9. Audit Logging Requirements

- `trg_appointment_business_hours_audit_insert|update|delete` in `db/03_triggers.sql` (unconditional `audit_logs` rows on DML).

## 10. Common Pitfalls

- Do not expose **Company** column — add `appointment_business_hours` to `$hideCompanyIdTables`, not only `$uiColumns` filter by field name.
- Do not show raw `0`/`1` for `is_closed` / `allows_in_person` / `allows_remote` on list/view — use ✅/❌ branch in `cr_render_cell_value()` on **index.php** (wrappers inherit).
- When changing `cr_render_cell_value()` or column visibility, update **index.php** and **create.php** duplicate helpers together.

## 11. Examples of Safe Code Patterns

```php
$stmt = $conn->prepare(
    'SELECT id, day_of_week, is_closed, allows_in_person, allows_remote
     FROM appointment_business_hours
     WHERE company_id = ? AND deleted_at IS NULL
     ORDER BY day_of_week ASC'
);
$stmt->bind_param('i', $companyId);
$stmt->execute();
```

## 12. Module Owner Notes

- Related hub: `modules/appointment_settings/AGENT_NOTES.md`
- Booking regression: `php scripts/verify_appointment.php`
