# AGENT_NOTES.md - Distribution Mappings

## 1. Module Purpose

Maps internal hospitality inventory IDs to **partner external codes** per distribution channel (`entity_type` = `hotel` or `room_type`, `internal_id`, `external_code`).

Without mapping rows, channel API availability/book responses return empty external codes. Primary admin UX is **Map all hotels / Map all room types** on [hotel_booking_distribution_channels/edit.php](http://localhost/it-management/modules/hotel_booking_distribution_channels/edit.php); this flattened CRUD module exposes the same table for search and bulk maintenance.

## 2. Key Tables

- **hotel_booking_distribution_mappings** — channel-scoped hotel and room-type code map

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `channel_id` → `hotel_booking_distribution_channels` (`ON DELETE CASCADE`)
- `internal_id` references `hotel_booking_hotels.id` or `booking_rooms_types.id` depending on `entity_type` (no DB FK — application-enforced)

## 4. Business Rules (Critical for Agents)

- Unique per channel: `(entity_type, internal_id)` and `(entity_type, external_code)`.
- `entity_type` must be `hotel` or `room_type` (string enum in schema).
- Seed and sample data: `includes/itm_hotel_booking_distribution_seed.php` and channel **Add sample data**.

## 5. UI Behavior Requirements

Flattened scaffold CRUD; FK label search should resolve channel name and internal hotel/room labels where helpers support `entity_type` discrimination.

## 9. Audit Logging Requirements

- `trg_hotel_booking_distribution_mappings_audit_*` in `db/03_triggers.sql`.

## 12. Module Owner Notes

- Partner API: [modules/hotel_booking_api/api.php](http://localhost/it-management/modules/hotel_booking_api/api.php) (API key auth)
- Doc: `docs/HOTEL_BOOKING_DISTRIBUTION.md`
