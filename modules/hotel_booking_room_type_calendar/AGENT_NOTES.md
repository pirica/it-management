# hotel_booking_room_type_calendar — AGENT_NOTES

## 1. Purpose

Admin UI for **date-range BAR overrides** (`hotel_booking_room_type_rate_overrides`) and **room-type stop-sell blocks** (`hotel_booking_room_type_blocks`) per hotel. Drives public portal pricing and availability via `includes/itm_hotel_booking.php`.

## 2. Tables

| Table | Role |
|-------|------|
| `hotel_booking_room_type_rate_overrides` | `start_date`–`end_date` inclusive nightly BAR per `room_type_id` + `hotel_id` |
| `hotel_booking_room_type_blocks` | Same keys; when any stay night overlaps, type is unsellable on portal |

Room default BAR remains `hotel_booking_rooms.price_per_night`. Overrides win per night when `active = 1` and night ∈ range (latest `id` wins).

## 3. Files

- `index.php` — hotel filter; lists overrides + blocks
- `rate_edit.php` — create/edit override (`31/Aug/2026` + 📅 via `js/hotel-date-input.js`)
- `block_edit.php` — create/edit stop-sell (`31/Aug/2026` + 📅 via `js/hotel-date-input.js`)
- `delete.php` — soft-delete (`kind=rate|block`)

## 4. Portal integration

Helpers: `itm_hotel_booking_resolve_room_type_nightly_bar()`, `itm_hotel_booking_room_type_blocked_for_stay()`, `itm_hotel_booking_room_unavailable_for_stay()` (bookings + OOO/OOS + HSK maintenance + type blocks).

Checkout uses `itm_hotel_booking_compute_stay_payment_dated_rates()` when draft carries `room_type_id`.

## 5. Regression

`php scripts/verify_hotel_booking.php` — table presence + override/block runtime probes.

Migration for live DBs: `db/migrations/hotel_booking_room_type_calendar.sql`.

## 6. Pitfalls

- Date ranges are **inclusive** on both ends.
- Type blocks apply to **all** physical rooms of that type at the hotel.
- HSK maintenance is separate (per `room_id`); both layers apply on portal.
