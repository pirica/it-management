# AGENT_NOTES.md - Hotel Booking Room Type Blocks

## 1. Module Purpose

Date-range **inventory blocks** per hotel room type: closes availability for a room type between `start_date` and `end_date` (optional `reason`). Used by hospitality availability and distribution ARI logic alongside physical room counts and bookings.

## 2. Key Tables

- **hotel_booking_room_type_blocks** — `hotel_id`, `room_type_id`, `start_date`, `end_date`, `reason`

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `hotel_id` → `hotel_booking_hotels` (`ON DELETE CASCADE`)
- `room_type_id` → `booking_rooms_types` (`ON DELETE RESTRICT`)

## 4. Business Rules (Critical for Agents)

- `end_date` must be on or after `start_date` (enforce in forms and import).
- Index `idx_hb_rt_block_range` supports overlap queries by company/hotel/room type and date range.
- Soft-delete via standard audit columns (`deleted_at IS NULL` on list).

## 5. UI Behavior Requirements

Flattened scaffold CRUD (same pattern as `hotel_booking_room_type_base_prices`):

- Search includes FK labels for hotel and room type names.
- Dates display **dd/mmm/yyyy** via `itm_format_cell_scalar_display()` / `itm_parse_date_input()`.
- Bulk toolbar when `$totalRows >= $perPage`; hide `company_id`.

## 9. Audit Logging Requirements

- `trg_hotel_booking_room_type_blocks_audit_*` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Overlapping blocks for the same room type are allowed in schema — availability helpers must aggregate all active ranges.
- Deleting a room type is **RESTRICT** — clear or reassign blocks first.

## 12. Module Owner Notes

- Regression: `php scripts/verify_hotel_booking.php`
- Related: [hotel_booking_room_type_rate_overrides/index.php](http://localhost/it-management/modules/hotel_booking_room_type_rate_overrides/index.php) for price overrides on date ranges
