# AGENT_NOTES.md - Distribution ARI Restrictions

## 1. Module Purpose

Per-day **ARI restriction** rows for distribution channels: min/max LOS, closed-to-arrival/departure, stop-sell, derived price multiplier, and optional base price override for a hotel room type (optionally tied to a rate-plan mapping).

Used by `includes/itm_hotel_booking_distribution.php` when building availability snapshots and outbound ARI pushes. Admin CRUD allows manual overrides and inspection.

## 2. Key Tables

- **hotel_booking_distribution_ari_restrictions** — one row per `(channel, hotel, room_type, stay_date, rate_plan_mapping_id)` unique key

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `channel_id` → `hotel_booking_distribution_channels` (`ON DELETE CASCADE`)
- `hotel_id` → `hotel_booking_hotels` (`ON DELETE CASCADE`)
- `room_type_id` → `booking_rooms_types` (`ON DELETE CASCADE`)
- `rate_plan_mapping_id` → `hotel_booking_distribution_rate_plan_mappings` (`ON DELETE SET NULL`)

## 4. Business Rules (Critical for Agents)

- Unique constraint `uq_hb_dist_ari_restr_day` — duplicate day rows for the same channel/hotel/room type (and rate plan mapping) fail on insert.
- Boolean flags: `closed_to_arrival`, `closed_to_departure`, `stop_sell` (checkbox pattern on forms).
- `derived_price_multiplier` defaults to `1.0000`; `base_price_override` is optional decimal override.

## 5. UI Behavior Requirements

Flattened scaffold CRUD; standard list/search/sort/pagination/bulk contract. FK labels for channel, hotel, room type, and rate-plan mapping on list/view.

## 9. Audit Logging Requirements

- `trg_hotel_booking_distribution_ari_restrictions_audit_*` in `db/03_triggers.sql`.

## 12. Module Owner Notes

- Often edited in bulk via channel configuration or ARI sync scripts rather than this list UI.
- Regression: [verify_hotel_booking_distribution.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution.php?run=1)
- Outbound sync: [run_hotel_booking_distribution_ari_sync.php?run=1](http://localhost/it-management/scripts/run_hotel_booking_distribution_ari_sync.php?run=1)
