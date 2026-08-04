# AGENT_NOTES.md - Hotel Booking Rooms

## 1. Module Purpose

Flattened CRUD for physical hotel inventory (`hotel_booking_rooms`): room number, display name, hotel, room type, housekeeping status, pricing, capacity, and photo uploads. Rooms feed the **Hotel Bookings** planning grid and public booking portal.

## 2. Key Tables

- **hotel_booking_rooms** — main room records (`room_number`, `name`, `hotel_id`, `room_type_id`, `housekeeping_status_id`, pricing/capacity fields)
- **hotel_booking_room_photos** — optional per-room images (upload on create/edit)
- **hotel_booking_room_utilities** — amenity links (separate module)

## 3. Required Relationships

- `hotel_id` → `hotel_booking_hotels` (RESTRICT)
- `room_type_id` → `booking_rooms_types` (RESTRICT)
- `housekeeping_status_id` → `hotel_booking_housekeeping_statuses` (SET NULL)
- Unique per tenant/hotel: `(company_id, hotel_id, room_number)`

## 4. Business Rules

- **List/search:** standard flattened scaffold; FK labels for hotel, type, and HSK status; `company_id` hidden in UI.
- **Duplicate action:** `duplicate.php` (POST + CSRF, `can_create`) clones a row via `itm_hotel_booking_room_duplicate_record()` in `includes/itm_hotel_booking.php`. New `room_number` gets a `-C` / `-C2` … suffix unique within the hotel; `name` gets ` Copy` / ` Copy 2` … suffix. Redirects to **edit** for the new row.
- **Photos:** `itm_hotel_booking_photos_handle_upload()` on create/edit; duplicates do not copy photo rows (re-upload on the new room if needed).
- **Delete:** soft-delete via shared scaffold; bookings referencing the room are RESTRICT-protected.

## 5. UI

- Actions column: View, Edit, **Duplicate** (📋, `title="Duplicate"`), Delete.
- View toolbar includes Duplicate beside share/back/edit controls.
- Hospitality admin layout helpers available via sibling modules; this module uses standard `includes/header.php` scaffold shell.

## 6. Regression

- `php scripts/verify_hotel_booking.php` — duplicate helper + module wiring checks.
- Hospitality index probe includes `hotel_booking_rooms`.

## 7. Pitfalls

- `room_number` max length **20** — duplicate suffix logic truncates the base before appending `-C`.
- Never reuse the source `room_number` on duplicate; unique index `uq_hotel_booking_rooms_company_hotel_number` will fail.
