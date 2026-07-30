# AGENT_NOTES.md - HK Maintenance

## 1. Module Purpose

Room maintenance windows (OOO/OOS): block dates on a room with return HK status and comments.

## 2. Key Tables

- **hotel_booking_housekeeping_maintenance** — `room_id`, `from_date`, `through_date`, `return_status_id` → `hotel_booking_housekeeping_statuses`, `maintenance_status_id` → `hotel_booking_housekeeping_maintenance_status`, `comments`.

## 3. UI

Flattened scaffold CRUD. List shows FK labels for room number and status names.

## 4. Pitfalls

- `room_id` is required; scope all queries by `company_id`.
- Date fields use **dd/mm/yyyy** display via shared date helpers.
