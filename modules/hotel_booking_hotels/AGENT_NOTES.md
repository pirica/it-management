# AGENT_NOTES.md - Hotels

## 1. Module Purpose

Tenant CRUD for hospitality properties (`hotel_booking_hotels`). Supports photo uploads to `hotel_booking_hotel_photos` used by the public booking portal hotel cards.

---

## 2. Key Tables

- **hotel_booking_hotels** — hotel master data (name, location, phone, **contact_email**, **reservations_email**, check-in/out, currency, policies, **portal step pricing columns**)
- **hotel_booking_hotel_photos** — image metadata (`stored_filename`, `is_cover`, `sort_order`) linked by `hotel_id`

---

## 3. Required Relationships

- **hotel_booking_hotels** → **companies** (`company_id`)
- **hotel_booking_hotel_photos** → **hotel_booking_hotels** (`hotel_id`, `ON DELETE CASCADE`)

---

## 4. Business Rules (Critical for Agents)

- **Photo storage:** files under `booking/images/{hotel_id}/hotel_photos/` via `itm_hotel_booking_photo_storage_dir($hotelId, 'hotel_photos')`.
- **Upload field:** create/edit forms use `hb_photos[]` (multiple) handled by `itm_hotel_booking_photos_handle_upload()` after save. Disk names are randomized via `itm_hotel_booking_photo_random_stored_filename()`; `original_filename` keeps the client name for display only. Edit forms POST `record_id` and use `enctype="multipart/form-data"`.
- **List thumbnails:** index shows **all** photos per hotel (not only cover); each opens full size in a new tab.
- **Edit thumbnails:** existing photos render above the file input via `itm_hotel_booking_photos_for_parent_table()` + `itm_hotel_booking_render_photo_thumbnail_link()`.
- **Portal step pricing:** `portal_breakfast_adult_price_per_night`, `portal_breakfast_child_price_per_night`, `portal_child_nightly_supplement`, `portal_extra_adult_supplement_percent`, `portal_pet_daily_fee` on `hotel_booking_hotels` — edited in **Portal Rate Plans** admin (`modules/hotel_booking_portal_rate_plans/index.php`), not on this module's forms.
- **Contact emails:** `contact_email` (general enquiries) and `reservations_email` (bookings desk) are optional `varchar(255)` fields on create/edit/view/list; POST validates with `filter_var(..., FILTER_VALIDATE_EMAIL)` when non-empty. Seeds: company 1 `info@techcorp-retreat.example` / `reservations@techcorp-retreat.example`; companies 2–5 use `info@retreat.{companyslug}.example` and `reservations@retreat.{companyslug}.example` (slug = lowercased company name without spaces).

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
$url = itm_hotel_booking_photo_public_url($hotelId, 'hotel_photos', $photos[0]['stored_filename'] ?? '');
```

---

## 12. Module Owner Notes

- Shared helpers: `includes/itm_hotel_booking.php` — admin thumbnails use `itm_app_root_public_path_prefix()` + `booking/images/{hotel_id}/hotel_photos/…`; public portal uses `APPURL/images/{hotel_id}/hotel_photos/…`.
- Public portal: `booking/index.php` loads hotel photos with `itm_hotel_booking_photos_load()` and `itm_hotel_booking_photo_public_url($hotelId, 'hotel_photos', …)`.
