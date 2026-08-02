# AGENT_NOTES.md - Distribution Channels

## 1. Module Purpose

Admin UI for **hotel booking distribution** partner channels: API keys, standard label (`itm_native`, `opentravel`, `booking_com`, `ohip`), hourly rate limits, optional webhook URL, partner credentials, webhook signing secrets, and hotel/room-type **external code mappings** plus **rate-plan mappings**.

## 2. Key Tables

- **hotel_booking_distribution_channels** — main channel rows (API key prefix + bcrypt hash; partner credentials encrypted; `last_ari_push_checksum`, `webhook_max_attempts`)
- **hotel_booking_distribution_mappings** — `entity_type` `hotel` or `room_type`, `internal_id`, `external_code`
- **hotel_booking_distribution_rate_plan_mappings** — portal rate plan ↔ external code, LOS, price multiplier
- **hotel_booking_distribution_ari_restrictions** — per-day CTA/CTD/stop-sell/derived pricing
- **hotel_booking_distribution_webhook_queue** — outbound retry queue (`pending` / `failed` / `delivered` / `dead`)

## 3. Required Relationships

- `channel_id` → `hotel_booking_distribution_channels.id` (CASCADE)
- Mappings reference `hotel_booking_hotels.id` or `booking_rooms_types.id` via `internal_id`
- Rate-plan mappings reference `hotel_booking_portal_rate_plans.id` via `portal_rate_plan_id`

## 5. UI Behaviour

Bespoke hospitality admin (not flattened scaffold CRUD):

- `index.php` — channel list
- `create.php` — new channel; plain API key shown once via session on redirect to `view.php`
- `edit.php` — update channel, rotate API key, partner credentials (Booking.com), generate webhook signing secret, outbound webhook API key, rate-plan mapping CRUD, add mapping rows
- `view.php` — details + API endpoint documentation
- `delete.php` — soft-delete
- `list_all.php` — redirect to `index.php`

Uses `includes/itm_hospitality_admin_layout.php`.

## 8. Multi-Tenant Rules

- All queries scoped by session `company_id`.
- Channel codes unique per company (`uq_hb_dist_channel_company_code`).

## 12. Module Owner Notes

- Partner API entry: [modules/hotel_booking_api/api.php](http://localhost/it-management/modules/hotel_booking_api/api.php) (API key auth, no employee session)
- Canonical doc: `docs/HOTEL_BOOKING_DISTRIBUTION.md`
- Regression: [verify_hotel_booking_distribution.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution.php?run=1) · [verify_hotel_booking_distribution_http.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution_http.php?run=1)
- Outbound ARI: channel view **Push ARI** button or [run_hotel_booking_distribution_ari_sync.php?run=1](http://localhost/it-management/scripts/run_hotel_booking_distribution_ari_sync.php?run=1)
- Webhook retries: [run_hotel_booking_distribution_webhook_queue.php?run=1](http://localhost/it-management/scripts/run_hotel_booking_distribution_webhook_queue.php?run=1)
