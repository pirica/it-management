# AGENT_NOTES.md - Hotel Booking Distribution API

## 1. Module Purpose

Authenticated **JSON + XML** API for channel partners (OTAs, channel managers, PMS bridges). Supports ITM native JSON, **OpenTravel OTA XML**, **Booking.com Connectivity JSON**, and **Oracle OHIP JSON** per channel `standard`.

## 2. Key Tables

Same as phase 1 — see `includes/itm_hotel_booking_distribution.php` and `hotel_booking_distribution_*` tables.

## 6. API Actions

Base: [api.php](http://localhost/it-management/modules/hotel_booking_api/api.php)

| Action | Method | Notes |
|--------|--------|-------|
| `probe` | GET | Key check + supported actions |
| `availability` | GET / POST | Shop; OpenTravel via `format=xml` or `OTA_HotelAvailRQ` POST body |
| `ari_snapshot` | GET | Pull ARI |
| `ari_push_outbound` | POST | POST snapshot to `webhook_url` |
| `book` / `modify` / `cancel` | POST | Reservation lifecycle |
| `notify` | POST | Inbound OTA (auto-routes book/modify/cancel) |
| `ari_push` | POST | Inbound rates / stop-sell |

## 7. File Structure

- **api.php** — router (uses wire helpers)
- **index.php** / **index.html** — redirect + listing guard

## 9. Security

Channel hourly rate limit; bcrypt API keys. Outbound webhook uses `file_get_contents` HTTP POST (30s timeout); logged in `hotel_booking_distribution_ari_events`.

## 12. Module Owner Notes

- Adapters: `includes/itm_hotel_booking_distribution_{wire,opentravel,booking_com,ohip}.php`
- ARI cron: [run_hotel_booking_distribution_ari_sync.php?run=1](http://localhost/it-management/scripts/run_hotel_booking_distribution_ari_sync.php?run=1)
- Admin: [Distribution Channels](http://localhost/it-management/modules/hotel_booking_distribution_channels/index.php)
- Doc: `docs/HOTEL_BOOKING_DISTRIBUTION.md`
- Regression: [verify_hotel_booking_distribution.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution.php?run=1)
