# Hotel booking distribution API

Partner-facing distribution under `modules/hotel_booking_api/api.php` with per-channel API keys in **Distribution Channels** (`modules/hotel_booking_distribution_channels/`).

## Architecture

| Layer | Location |
|-------|----------|
| Router | `modules/hotel_booking_api/api.php` |
| Core logic | `includes/itm_hotel_booking_distribution.php` |
| Wire negotiation | `includes/itm_hotel_booking_distribution_wire.php` |
| OpenTravel XML | `includes/itm_hotel_booking_distribution_opentravel.php` |
| Booking.com JSON | `includes/itm_hotel_booking_distribution_booking_com.php` |
| Oracle OHIP JSON | `includes/itm_hotel_booking_distribution_ohip.php` |
| Channel admin | `modules/hotel_booking_distribution_channels/` |

Auth: `X-API-Key` (or `api_key`). No employee session (`ITM_HOTEL_BOOKING_DISTRIBUTION_API`).

## Wire formats

| Channel `standard` | Request | Response |
|--------------------|---------|----------|
| `itm_native` | JSON (default) | JSON |
| `opentravel` | XML (`OTA_HotelAvailRQ`, `OTA_HotelResNotifRQ`) or `format=xml` | `OTA_HotelAvailRS`, `OTA_HotelResNotifRS`, `OTA_HotelAvailNotifRS` |
| `booking_com` | Booking.com Connectivity JSON subset | Wrapped JSON (`room_rates`, reservation ids) |
| `ohip` | OHIP JSON subset | Wrapped JSON (`status`, `confirmationId`) |

Override with `?format=json` or `?format=xml`, or `Accept: application/xml`.

## Endpoints

Base: `/it-management/modules/hotel_booking_api/api.php`

| Action | Method | Description |
|--------|--------|-------------|
| `probe` | GET | Validate API key |
| `availability` | GET / POST | Shop (JSON query or OpenTravel XML body) |
| `ari_snapshot` | GET | Pull ARI inventory/rates |
| `ari_push_outbound` | POST | POST ARI snapshot to channel `webhook_url` |
| `book` | POST | Create reservation |
| `modify` | POST | Amend dates/room/guest by `external_reservation_id` |
| `cancel` | POST | Cancel by `external_reservation_id` |
| `notify` | POST | Inbound OTA notification (routes to book/modify/cancel) |
| `ari_push` | POST | Inbound rates / stop-sell into ITM |

## Outbound ARI to OTAs

1. **On demand:** `POST ?action=ari_push_outbound` (authenticated channel) or **Push ARI** on channel view when `webhook_url` is set.
2. **Scheduled:** `php scripts/run_hotel_booking_distribution_ari_sync.php` — pushes to all channels with `webhook_url` (cron daily).

Payload format follows channel `standard` (XML for OpenTravel, partner JSON for Booking.com / OHIP).

## Regression

```bash
php scripts/verify_hotel_booking_distribution.php
php scripts/run_hotel_booking_distribution_ari_sync.php
```

## Related

- Guest portal: `docs/BOOKING.md`
- Reservations: `modules/hotel_bookings/AGENT_NOTES.md`
