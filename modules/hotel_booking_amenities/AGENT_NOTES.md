# AGENT_NOTES.md - Booking Amenities

## 1. Module Purpose

Tenant catalog of hotel amenities shown on the public booking portal (name, SVG icon slug, sort order, active). Admins manage rows here; **Room Utilities** can link a utility row to a catalog entry via `amenity_id`.

## 2. Key Tables

- **hotel_booking_amenities** — catalog (`name`, `icon_slug`, `description`, `sort_order`, `active`, audit + soft-delete)
- **hotel_booking_room_utilities** — optional `amenity_id` → catalog (icon/name for portal)

## 3. Required Relationships

- **hotel_booking_amenities** → `companies` (`company_id`, CASCADE)
- **hotel_booking_room_utilities.amenity_id** → **hotel_booking_amenities** (`ON DELETE SET NULL`)

## 4. Business Rules

- `icon_slug` maps to `booking/images/amenities/{slug}.svg` (Lucide SVGs; see `ATTRIBUTION.md` in that folder).
- Upload/delete SVG files on **icons.php** (admin only). `default` icon cannot be deleted; delete blocked when slug is used on catalog rows.
- Portal resolves icons via `includes/itm_hotel_booking_amenity_icons.php` and `hb_portal_render_amenities_scroll()`.

## 5. UI

- Flattened CRUD: list/create/edit/view/delete; **icon_slug** uses visual picker on forms.
- **Manage icons** links to `icons.php` for SVG library maintenance.

## 6. API

N/A (standard `import_excel_rows` on `index.php` only).

## 7. Files

- `index.php` — CRUD hub
- `icons.php` — SVG upload/delete
- Wrappers: `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`

## 8. Multi-tenant

All queries scoped by session `company_id`.

## 9. Audit

`trg_hotel_booking_amenities_audit_*` in `db/03_triggers.sql`.

## 10. Regression

`php scripts/verify_hotel_booking.php` (table presence). Fresh DB: `db/01_schema.sql` + seeds in `db/02_data.sql`. Existing DB: `db/migrations/hotel_booking_amenities.sql` (destructive to `hotel_booking_room_utilities` — back up first).
