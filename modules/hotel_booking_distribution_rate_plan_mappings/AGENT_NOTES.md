# AGENT_NOTES.md - Distribution Rate Plan Mappings

## 1. Module Purpose

Maps internal **portal rate plans** (`hotel_booking_portal_rate_plans`) to partner **external rate plan codes** per distribution channel, with LOS bounds and `price_multiplier`.

Also managed on channel `edit.php` alongside hotel/room mappings. Required for multi-rate-plan OTA pushes and restriction rows that reference `rate_plan_mapping_id`.

## 2. Key Tables

- **hotel_booking_distribution_rate_plan_mappings** — `portal_rate_plan_id`, `external_rate_plan_code`, `min_los`, `max_los`, `price_multiplier`, optional `restrictions_json`

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `channel_id` → `hotel_booking_distribution_channels` (`ON DELETE CASCADE`)
- `hotel_id` → `hotel_booking_hotels` (`ON DELETE CASCADE`)
- `portal_rate_plan_id` → `hotel_booking_portal_rate_plans` (`ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- Unique per channel: `(portal_rate_plan_id)` and `(external_rate_plan_code)`.
- `min_los` defaults to `1`; `max_los` nullable.
- `price_multiplier` decimal defaults to `1.0000`.

## 5. UI Behavior Requirements

Flattened scaffold CRUD; list/view show portal rate plan name and channel/hotel labels, not raw IDs.

## 9. Audit Logging Requirements

- `trg_hotel_booking_distribution_rate_plan_mappings_audit_*` in `db/03_triggers.sql`.

## 12. Module Owner Notes

- Referenced by `hotel_booking_distribution_ari_restrictions.rate_plan_mapping_id`.
- Regression: [verify_hotel_booking_distribution.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution.php?run=1)
