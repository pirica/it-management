# AGENT_NOTES.md - Hotel Booking Room Type Rate Overrides

## 1. Module Purpose

Date-range **price overrides** per hotel room type (`price_per_night` between `start_date` and `end_date`, optional `notes`). Supplements base prices in `hotel_booking_room_type_base_prices` for seasonal or promotional pricing.

## 2. Key Tables

- **hotel_booking_room_type_rate_overrides** — override window and `price_per_night` decimal

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `hotel_id` → `hotel_booking_hotels` (`ON DELETE CASCADE`)
- `room_type_id` → `booking_rooms_types` (`ON DELETE RESTRICT`)

## 4. Business Rules (Critical for Agents)

- `price_per_night` non-negative decimal; comma decimal input normalized on POST/import like other price fields.
- Multiple non-overlapping or overlapping ranges may exist — pricing resolution order is in `includes/itm_hotel_booking.php` / portal helpers (verify before changing overlap rules).

## 5. UI Behavior Requirements

Flattened scaffold CRUD; FK labels for hotel and room type; hospitality date fields; standard bulk/search/sort/pagination contract.

## 9. Audit Logging Requirements

- `trg_hotel_booking_room_type_rate_overrides_audit_*` in `db/03_triggers.sql`.

## 12. Module Owner Notes

- Base prices: [hotel_booking_room_type_base_prices/index.php](http://localhost/it-management/modules/hotel_booking_room_type_base_prices/index.php)
- Regression: `php scripts/verify_hotel_booking.php`
