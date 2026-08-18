# AGENT_NOTES.md - Room Types

## 1. Module Purpose

Tenant CRUD for hospitality room types (`booking_rooms_types`). **Photos are attached to the room type**, not individual `hotel_booking_rooms` rows, because many physical rooms share one type on the public booking portal.

---

## 2. Key Tables

- **booking_rooms_types** — type master (code, bed summary, capacity, upgrade fields)
- **booking_rooms_type_photos** — image metadata (`stored_filename`, `is_cover`, `sort_order`) linked by `room_type_id`

---

## 3. Required Relationships

- **booking_rooms_types** → **companies** (`company_id`)
- **booking_rooms_type_photos** → **booking_rooms_types** (`room_type_id`, `ON DELETE CASCADE`)
- **hotel_booking_rooms** → **booking_rooms_types** (`room_type_id`) — portal cards resolve images from the type, not per-room photos

---

## 4. Business Rules (Critical for Agents)

- **Photo storage:** `booking/images/{hotel_id}/room_types_photos/` (mirrored to every company hotel on upload). Admin thumbnails use the first hotel in the tenant. **Seed files:** `booking/images/sample-room.jpg` plus tracked copies `booking/images/1/room_types_photos/hb_rt_*.jpg` matching `db/02_data.sql`; backfill live DBs with `php scripts/seed_hotel_booking_sample_photos.php --apply`.
- **Upload field:** create/edit forms use `hb_photos[]` (multiple) via `itm_hotel_booking_photos_handle_upload()` after save. Edit POSTs `record_id` with `enctype="multipart/form-data"` and `action="edit.php?id=…"`.
- **Portal:** `hb_portal_room_type_photo_urls()` / `itm_hotel_booking_portal_room_type_photo_urls()` load `booking_rooms_type_photos` from `booking/images/{hotel_id}/room_types_photos/` (shared gallery for every room of that type on `booking/rooms.php`).
- **Portal rules (columns on `booking_rooms_types`):** occupancy (`max_total_guests` + `max_extra_beds` when `extra_bed_allowed`, `min_adults`, `child_max_age` band vs children/babies counters, `adults_only`, `included_adults_per_room`, `crib_included` waives baby nightly supplement in `itm_hotel_booking_portal_quote_nightly()`), stay (`min_stay_nights`, CTA/CTD weekday CSV), pricing overrides (`portal_*` — `NULL` inherits hotel), `portal_bookable`, `requires_approval`, pets, `allow_mixed_types_in_group`, `max_rooms_per_booking`. Multi-room portal splits validate **per slice** via `itm_hotel_booking_portal_split_occupancy_for_room_line()`. **Connecting rooms** are configured per physical room on `hotel_booking_rooms` (`connecting_room_id` + `connected_to` room number), not on room types.
- **Sample seeds:** `db/02_data.sql` inserts `hb_rt_*` rows for STD/SUP/DLX; `php scripts/seed_hotel_booking_sample_photos.php --apply` backfills files on existing DBs.

---

## 5. UI Behavior Requirements

- Flattened CRUD in `index.php`; `create.php`, `edit.php`, `view.php`, `list_all.php` are thin wrappers (same pattern as `hotel_booking_hotels/`).
- Hide **`company_id`** from list/view/forms (`booking_rooms_types` is in `$hideCompanyIdTables`).
- List **`portal_bookable`** (and other portal tinyint flags on view) render ✅/❌ via `cr_render_cell_value()`; **`active`** uses Active/Inactive badges.
- List hides detailed portal-rule columns; **create/edit** show a **Portal rules** card (`brt_portal_rule_form_columns()` / `brt_render_form_group()` in `index.php`) for occupancy caps, `child_max_age`, `extra_bed_allowed` / `max_extra_beds`, `crib_included`, mixed-type, and `max_rooms_per_booking`. `max_total_guests` and `portal_bookable` remain on the main form.
- List **Photos** column shows all thumbnails per type; edit shows **Current photos** grid + **Add photos** file input.

---

## 6. API Actions (If Applicable)

- None (standard CRUD).

---

## 7. File Structure

- **index.php** — list, create, edit, view, bulk delete, import
- **includes/brt_fk_helpers.php** — FK option helpers for upgrade room type
- **delete.php** — soft-delete handler (standalone)

---

## 8. Multi-Tenant Rules

- All queries scoped by session `company_id`.

---

## 9. Audit Logging Requirements

- `trg_booking_rooms_types_audit_*` and `trg_booking_rooms_type_photos_audit_*` in `db/03_triggers.sql`.

---

## 10. Common Pitfalls

- Do not wire portal room galleries to `hotel_booking_room_photos` when the product intent is type-level imagery.
- Duplicated full copies of `index.php` in `edit.php`/`create.php` break photo upload — keep wrappers only.

---

## 11. Examples of Safe Code Patterns

```php
$photos = itm_hotel_booking_photos_for_parent_table($conn, $companyId, 'booking_rooms_types', $typeId);
$url = itm_hotel_booking_photo_public_url($hotelId, 'room_types_photos', $photos[0]['stored_filename'] ?? '');
```

---

## 12. Regression / Maintenance

- `php scripts/verify_hotel_booking.php` — static checks for list/edit photo markup and portal helper
- `php scripts/seed_hotel_booking_sample_photos.php --apply` — hotel + room-type sample files/rows
