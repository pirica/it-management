# AGENT_NOTES.md - Distribution ARI Events

## 1. Module Purpose

Admin CRUD over the **ARI (availability / rates / inventory) event log** for hotel booking distribution channels. Rows capture inbound partner pushes and outbound ITM responses (`request_json` / `response_json`) for troubleshooting and certification audits.

Primary writers are `includes/itm_hotel_booking_distribution.php` and `modules/hotel_booking_api/api.php`; this module is for inspection and manual cleanup, not day-to-day channel configuration (see `modules/hotel_booking_distribution_channels/`).

## 2. Key Tables

- **hotel_booking_distribution_ari_events** — per-channel ARI audit (`event_type`, `direction`, `status`, JSON payloads, optional `room_type_id`)

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `channel_id` → `hotel_booking_distribution_channels` (`ON DELETE CASCADE`)
- `hotel_id` → `hotel_booking_hotels` (`ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- Rows are tenant-scoped by `company_id`; channel and hotel FKs must belong to the same tenant.
- `direction` is typically `inbound` or `outbound`; `status` tracks processing outcome (`received`, `processed`, `failed`, etc. — verify live enum usage in distribution helpers).
- Deleting a channel cascades to its ARI event rows.

## 5. UI Behavior Requirements

Flattened scaffold CRUD (`$crud_table = 'hotel_booking_distribution_ari_events'`):

- Standard search, sort, pagination, bulk delete when `$totalRows >= $perPage`, `$displayFieldColumns = $uiColumns`.
- Hide `company_id` from list/view/forms; FK labels for `channel_id`, `hotel_id`, `room_type_id` on list/view.
- `data-itm-db-import-endpoint="index.php"`; Actions cells use `itm-actions-cell` + `data-itm-actions-origin="1"`.

## 9. Audit Logging Requirements

- `trg_hotel_booking_distribution_ari_events_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Large `request_json` / `response_json` payloads — list search may be slow; prefer `view.php` for full JSON.
- Operational ARI changes are usually applied via API or channel **Push ARI** — do not assume admins create rows only through this CRUD UI.

## 12. Module Owner Notes

- Canonical doc: `docs/HOTEL_BOOKING_DISTRIBUTION.md`
- Regression: [verify_hotel_booking_distribution.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution.php?run=1)
- Channel admin: [hotel_booking_distribution_channels/index.php](http://localhost/it-management/modules/hotel_booking_distribution_channels/index.php) (Admin session)
