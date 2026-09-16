# AGENT_NOTES.md - Distribution Reservations

## 1. Module Purpose

Links **channel partner reservation IDs** to internal `hotel_bookings` rows. Tracks external status, acknowledgement (`ack_status`, `ack_at`, `nack_reason`), partner message id, and optional `payload_json` snapshot.

Written by `includes/itm_hotel_booking_distribution.php` on book/modify/cancel API flows; admin CRUD is for support and reconciliation.

## 2. Key Tables

- **hotel_booking_distribution_reservations** — `(channel_id, external_reservation_id)` unique; points to `hotel_booking_id`

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `channel_id` → `hotel_booking_distribution_channels` (`ON DELETE CASCADE`)
- `hotel_booking_id` → `hotel_bookings` (`ON DELETE RESTRICT` — do not delete bookings while distribution links exist)

## 4. Business Rules (Critical for Agents)

- `external_reservation_id` is partner-supplied; unique per channel.
- `ack_status` default `pending`; partners may require ack/nack handling in API layer.
- `hotel_booking_id` must belong to the same `company_id` as the channel.

## 5. UI Behavior Requirements

Flattened scaffold CRUD; list/view must show channel name and booking reference labels (guest/customer/room) via FK helpers, not numeric IDs only.

## 9. Audit Logging Requirements

- `trg_hotel_booking_distribution_reservations_audit_*` in `db/03_triggers.sql`.

## 12. Module Owner Notes

- Booking CRUD: [hotel_bookings/index.php](http://localhost/it-management/modules/hotel_bookings/index.php)
- API router: [hotel_booking_api/api.php](http://localhost/it-management/modules/hotel_booking_api/api.php)
