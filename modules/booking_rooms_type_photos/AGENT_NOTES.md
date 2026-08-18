# AGENT_NOTES.md - Booking Room Type Photos

## 1. Module Purpose

Flattened CRUD for **`booking_rooms_type_photos`** — gallery images linked to **`booking_rooms_types`** (`room_type_id`), with sort order, cover flag, stored filename metadata, and standard audit columns. Supports hospitality room-type media used by booking modules.

## 2. Key Tables

- **booking_rooms_type_photos** — photo rows per room type (`stored_filename`, `original_filename`, `sort_order`, `is_cover`)
- **booking_rooms_types** — parent room type (FK `room_type_id`, `ON DELETE CASCADE`)

## 3. Required Relationships

- **booking_rooms_type_photos** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **booking_rooms_type_photos** → **booking_rooms_types** (`room_type_id`, `ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- **`is_cover`**: tinyint(1) flag for the primary gallery image on a room type; only one cover per type is a business expectation (not enforced by UNIQUE in schema — validate in UI/workflows if tightening).
- **`stored_filename`**: required; file storage paths live under tenant upload conventions (see hospitality/booking module notes).
- List FK **`room_type_id`** must show room type label, not raw id (`cr_fk_map` / list search helpers).

## 5. UI Behavior Requirements

### Flattened CRUD (`index.php`)

- Hide **`company_id`** from list, view, and forms (`$hideCompanyIdTables` includes `booking_rooms_type_photos`).
- **`active`**: list/view use Active/Inactive badges.
- **`is_cover`**: list/view use ✅/❌ in `cr_render_cell_value()`; create/edit use `itm-checkbox-control` for `tinyint(1)` fields.
- `$displayFieldColumns = $uiColumns` before search block.
- Bulk delete when `$totalRows >= $perPage`; Actions: `itm-actions-cell` + `data-itm-actions-origin="1"`.
- Import: `data-itm-db-import-endpoint="index.php"` on list table.
- **CSRF:** `cr_require_valid_csrf_token()` on POST; forms use `cr_get_csrf_token()`.

### Wrappers

- **edit.php**, **view.php**, **list_all.php** — set `$crud_action` then `require 'index.php'`.
- **create.php** — duplicate helpers; keep aligned with **index.php** when changing renderers or `$hideCompanyIdTables`.

## 6. API Actions

- **import_excel_rows** — JSON POST on `index.php`.

## 7. File Structure

- **index.php** — primary list/view/create/edit/delete + `cr_render_cell_value()`
- **create.php**, **edit.php**, **view.php**, **list_all.php**, **delete.php**, **join.php**

## 8. Multi-Tenant Rules

- All queries and inserts scope by session `company_id`.
- Never show `company_id` in UI (hidden input on create/edit when `$hasCompany`).

## 9. Audit Logging Requirements

- `trg_booking_rooms_type_photos_audit_*` in `db/03_triggers.sql` (standard scaffold audit triggers).

## 10. Common Pitfalls

- Do not expose **Company** column — table must be in `$hideCompanyIdTables`.
- Do not show raw `0`/`1` for **`is_cover`** on list/view — use ✅/❌ in `cr_render_cell_value()` on **index.php** (wrappers inherit).
- Deleting a **booking_rooms_types** row cascades photo rows — warn in data maintenance flows.

## 11. Examples of Safe Code Patterns

```php
$stmt = $conn->prepare(
    'SELECT id, room_type_id, is_cover, sort_order
     FROM booking_rooms_type_photos
     WHERE company_id = ? AND deleted_at IS NULL
     ORDER BY sort_order ASC, id ASC'
);
$stmt->bind_param('i', $companyId);
$stmt->execute();
```

## 12. Module Owner Notes

- Related: `modules/booking_rooms_types/AGENT_NOTES.md`, `docs/BOOKING.md`
