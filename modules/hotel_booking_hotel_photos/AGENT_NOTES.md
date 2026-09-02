# AGENT_NOTES.md - Hotel Photos

## 1. Module Purpose

Flattened CRUD for **`hotel_booking_hotel_photos`** — gallery images linked to **`hotel_booking_hotels`** (`hotel_id`), with sort order, cover flag, stored filename metadata, and standard audit columns. Supports hospitality hotel media used by the booking portal and the Hotels list **Photos** column.

## 2. Key Tables

- **hotel_booking_hotel_photos** — photo rows per hotel (`stored_filename`, `original_filename`, `sort_order`, `is_cover`)
- **hotel_booking_hotels** — parent hotel (FK `hotel_id`, `ON DELETE CASCADE`)

## 3. Required Relationships

- **hotel_booking_hotel_photos** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **hotel_booking_hotel_photos** → **hotel_booking_hotels** (`hotel_id`, `ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- **`is_cover`**: tinyint(1) flag for the primary gallery image on a hotel; only one cover per hotel is a business expectation (not enforced by UNIQUE in schema).
- **`stored_filename`**: required; files live under `booking/images/{hotel_id}/hotel_photos/` (see `modules/hotel_booking_hotels/AGENT_NOTES.md`).
- List FK **`hotel_id`** must show hotel name, not raw id (`cr_fk_map` / list search helpers).

## 5. UI Behavior Requirements

### Flattened CRUD (`index.php`)

- Hide **`company_id`** from list, view, and forms (`$hideCompanyIdTables` includes `hotel_booking_hotel_photos`).
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

- `trg_hotel_booking_hotel_photos_audit_*` in `db/03_triggers.sql` (standard scaffold audit triggers).

## 10. Common Pitfalls

- Do not expose **Company** column — table must be in `$hideCompanyIdTables`.
- Do not show raw `0`/`1` for **`is_cover`** on list/view — use ✅/❌ in `cr_render_cell_value()` on **index.php** (wrappers inherit).
- Deleting a **hotel_booking_hotels** row cascades photo rows — warn in data maintenance flows.

## 11. Examples of Safe Code Patterns

```php
$stmt = $conn->prepare(
    'SELECT id, hotel_id, is_cover, sort_order
     FROM hotel_booking_hotel_photos
     WHERE company_id = ? AND deleted_at IS NULL
     ORDER BY sort_order ASC, id ASC'
);
$stmt->bind_param('i', $companyId);
$stmt->execute();
```

## 12. Module Owner Notes

- Seed files: `booking/images/1/hotel_photos/hb_seed_*.jpg` (see `db/02_data.sql`); backfill with `php scripts/seed_hotel_booking_sample_photos.php --apply`.
- Related: `modules/hotel_booking_hotels/AGENT_NOTES.md`, `docs/BOOKING.md`
