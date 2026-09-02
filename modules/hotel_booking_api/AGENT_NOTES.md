# AGENT_NOTES.md - Hotel Booking Distribution API

## 1. Module Purpose

Authenticated **JSON + XML** API for channel partners (OTAs, channel managers, PMS bridges). Supports ITM native JSON, **OpenTravel OTA XML**, **Booking.com Connectivity JSON**, and **Oracle OHIP JSON** per channel `standard`. Phase 3 adds inbound HMAC signature validation, outbound webhook queue with retries/dead-letter, deeper ARI (LOS/restrictions/derived rates/delta push), and Booking.com ACK/NACK reservation responses.

## 2. Key Tables

`hotel_booking_distribution_*` — channels, mappings, reservations (`ack_status`), ARI events, rate-plan mappings, ARI restrictions, webhook queue. See `includes/itm_hotel_booking_distribution.php` and `docs/HOTEL_BOOKING_DISTRIBUTION.md`.

## 6. API Actions

Base: [api.php](http://localhost/it-management/modules/hotel_booking_api/api.php)

| Action | Method | Notes |
|--------|--------|-------|
| `probe` | GET | Key check + supported actions |
| `availability` | GET / POST | Shop; OpenTravel via `format=xml` or `OTA_HotelAvailRQ` POST body |
| `ari_snapshot` | GET | Pull enriched ARI |
| `ari_push_outbound` | POST | POST snapshot to `webhook_url` / Booking.com API; `force=1` bypasses delta skip |
| `book` / `modify` / `cancel` | POST | Reservation lifecycle with partner ACK/NACK |
| `notify` | POST | Inbound OTA (auto-routes book/modify/cancel); signature required when signing secret set |
| `ari_push` | POST | Inbound rates / stop-sell |

## 7. File Structure

- **api.php** — router (uses wire helpers)
- **index.php** / **index.html** — redirect + listing guard

## 9. Security

Channel hourly rate limit; bcrypt API keys. Inbound POST signature via `itm_hotel_booking_distribution_verify_inbound_signature()`. Outbound deliveries use `hotel_booking_distribution_webhook_queue` with HMAC signing and optional `X-API-Key` header; logged in `hotel_booking_distribution_ari_events`.

## 12. Module Owner Notes

- Adapters: `includes/itm_hotel_booking_distribution_{wire,opentravel,booking_com,booking_com_connect,ohip,secrets,webhooks,ari_deep}.php`
- ARI cron: [run_hotel_booking_distribution_ari_sync.php?run=1](http://localhost/it-management/scripts/run_hotel_booking_distribution_ari_sync.php?run=1)
- Webhook queue: [run_hotel_booking_distribution_webhook_queue.php?run=1](http://localhost/it-management/scripts/run_hotel_booking_distribution_webhook_queue.php?run=1)
- Admin: [Distribution Channels](http://localhost/it-management/modules/hotel_booking_distribution_channels/index.php)
- Doc: `docs/HOTEL_BOOKING_DISTRIBUTION.md`
- Regression: [verify_hotel_booking_distribution.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution.php?run=1) · [verify_hotel_booking_distribution_http.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution_http.php?run=1)
- Examples: `api-examples/hotel_distribution_availability.php`, `api-examples/hotel_distribution_notify_book.php`
