# AGENT_NOTES.md - Hotel Bookings

## 1. Module Purpose

Bespoke hospitality hub: room **Planning** grid (anchor date, hotel filter), **Future / Present / History** boards, and CRUD for `hotel_bookings`. Every booking requires `customer_id` (ITM `customers`).

## 2. Key Tables

- **hotel_bookings** — reservations (`room_id`, `check_in`, `check_out`, `payment_amount`, segment status FKs)
- **hotel_bookings_future / present / history** — lifecycle status lookups (no ENUM)
- **hotel_booking_rooms**, **hotel_booking_hotels**, **booking_rooms_types** — inventory (separate CRUD modules)

## 3. Required Relationships

- `customer_id` → `customers` (mandatory, tenant-scoped)
- `room_id` → `hotel_booking_rooms`
- Segment status columns → matching `hotel_bookings_*` table by date vs today

## 4. Shared helpers

`includes/itm_hotel_booking.php` — segment resolution, overlap/cancelled checks, planning grid, portal customer ensure, photo upload paths.

## 5. Public portal

`booking/` — MySQLi bootstrap, portal users → `customers`; inserts into `hotel_bookings`. Payment page: `booking/rooms/payment.php` (legacy `pay.php` redirects).

## 6. Regression

`php scripts/verify_hotel_booking.php` after DDL/seeds or helper changes.
