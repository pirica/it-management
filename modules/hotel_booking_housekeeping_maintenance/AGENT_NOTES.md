# AGENT_NOTES.md - HSK Maintenance

## 1. Module Purpose

Room maintenance windows (OOO/OOS): block dates on a room with return HSK status and comments.

## 2. Key Tables

- **hotel_booking_housekeeping_maintenance** — `room_id`, `from_date`, `through_date`, `return_status_id` → `hotel_booking_housekeeping_statuses`, `maintenance_status_id` → `hotel_booking_housekeeping_maintenance_status`, `comments`.

## 3. UI

Flattened scaffold CRUD. List shows FK labels for room number and status names.

Planning grid integration: `hotel_bookings` planning double-clicks OOO/OOS bars open this module’s `edit.php?id={id}&embed=1` in a modal iframe; embed mode strips sidebar/header, posts `embed=1` hidden field, redirects back to embed edit with `saved=1`, and `postMessage` notifies the parent to reload the grid.

## 4. Pitfalls

- `room_id` is required; scope all queries by `company_id`.
- Date fields use **`d/M/Y`** (`31/Aug/2026`, `01/Oct/2026`) via `itm_format_hotel_date_display()` and `itm_render_hotel_date_input()` (`js/hotel-date-input.js`) on `from_date` / `through_date` forms; list/view via `itm_format_cell_scalar_display()`.
