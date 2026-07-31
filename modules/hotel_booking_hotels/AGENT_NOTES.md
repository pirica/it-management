# AGENT_NOTES.md - Hotels

## 1. Module Purpose

Tenant CRUD for hospitality properties (`hotel_booking_hotels`). Supports photo uploads to `hotel_booking_hotel_photos` used by the public booking portal hotel cards.

---

## 2. Key Tables

- **hotel_booking_hotels** — hotel master data (name, location, check-in/out, currency, policies)
- **hotel_booking_hotel_photos** — image metadata (`stored_filename`, `is_cover`, `sort_order`) linked by `hotel_id`

---

## 3. Required Relationships

- **hotel_booking_hotels** → **companies** (`company_id`)
- **hotel_booking_hotel_photos** → **hotel_booking_hotels** (`hotel_id`, `ON DELETE CASCADE`)

---

## 4. Business Rules (Critical for Agents)

- **Photo storage:** files under `images/hotel_booking/{company_id}/hotel/{hotel_id}/` via `itm_hotel_booking_photo_storage_dir()`.
- **Upload field:** create/edit forms use `hb_photos[]` (multiple) handled by `itm_hotel_booking_photos_handle_upload()` after save. Disk names are randomized via `itm_hotel_booking_photo_random_stored_filename()`; `original_filename` keeps the client name for display only.
- **List thumbnails:** index shows **all** photos per hotel (not only cover); each opens full size in a new tab.
- **Edit thumbnails:** existing photos render above the file input via `itm_hotel_booking_photos_for_parent_table()` + `itm_hotel_booking_render_photo_thumbnail_link()`.

---

## 5. UI Behavior Requirements

- Flattened CRUD in `index.php` (`create` / `edit` / `view` via `$crud_action`; wrappers `create.php`, `edit.php`, etc.).
- **Photo column** on list (not a DB column) when `itm_hotel_booking_photos_config_for_parent_table('hotel_booking_hotels')` is set.
- Hospitality hub toolbar via `itm_hospitality_render_list_create_and_hub()`.

---

## 6. API Actions (If Applicable)

- `None` (standard CRUD + optional `itm_crud_record_share` AJAX).

---

## 7. File Structure

- **index.php** — list, create, edit, view, bulk delete, import
- **delete.php**, **view.php**, **edit.php**, **create.php**, **list_all.php** — thin wrappers

---

## 8. Multi-Tenant Rules

- All queries scoped by session `company_id`.

---

## 9. Audit Logging Requirements

- `trg_hotel_booking_hotels_audit_*` and `trg_hotel_booking_hotel_photos_audit_*` in `db/03_triggers.sql`.

---

## 10. Common Pitfalls

- Upload-only UI does not delete existing photos from edit — use DB/file maintenance or extend delete UX if required.
- Empty photo table shows em dash in list until first upload.

---

## 11. Examples of Safe Code Patterns

```php
$photos = itm_hotel_booking_photos_for_parent_table($conn, $companyId, 'hotel_booking_hotels', $hotelId);
$url = itm_hotel_booking_photo_public_url($companyId, 'hotel', $hotelId, $photos[0]['stored_filename'] ?? '');
```

---

## 12. Module Owner Notes

- Shared helpers: `includes/itm_hotel_booking.php`
- Public portal: `booking/index.php` loads hotel photos with `itm_hotel_booking_photos_load()`
