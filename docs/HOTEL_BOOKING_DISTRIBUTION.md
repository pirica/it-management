# Hotel booking distribution API

Phase 1 adds partner-facing JSON under `modules/hotel_booking_api/api.php` with per-channel API keys managed in **Distribution Channels** (`modules/hotel_booking_distribution_channels/`).

## Architecture

| Layer | Location |
|-------|----------|
| JSON router | `modules/hotel_booking_api/api.php` |
| Business logic | `includes/itm_hotel_booking_distribution.php` |
| Channel admin | `modules/hotel_booking_distribution_channels/` |
| Schema | `hotel_booking_distribution_*` tables in `db/01_schema.sql` |
| Migration | `db/migrations/hotel_booking_distribution.sql` |

Auth uses `X-API-Key` (or `api_key` parameter). Employee login is not required (`ITM_HOTEL_BOOKING_DISTRIBUTION_API` in `config/config.php`).

## Endpoints (ITM native JSON)

Base URL: `/it-management/modules/hotel_booking_api/api.php`

| Action | Method | Description |
|--------|--------|-------------|
| `probe` | GET | Validate key; return channel metadata |
| `availability` | GET | Shop room types for `check_in` / `check_out` |
| `ari_snapshot` | GET | Outbound ARI for a date range |
| `book` | POST | Create reservation + distribution link row |
| `cancel` | POST | Cancel by `external_reservation_id` |
| `ari_push` | POST | Inbound nightly rates and stop-sell blocks |

Use internal `hotel_id` / `room_type_id` or mapped `external_hotel_code` / `external_room_type_code` (configure mappings per channel in admin).

## Standards roadmap

| `standard` value | Phase |
|------------------|-------|
| `itm_native` | **Current** — JSON documented above |
| `opentravel` | Planned — OpenTravel XML adapter on same helpers |
| `booking_com` | Planned — Booking.com Connectivity mapper |
| `ohip` | Planned — Oracle OHIP mapper |

`webhook_url` on the channel row is reserved for outbound ARI push notifications in a later phase.

## Regression

```bash
php scripts/verify_hotel_booking_distribution.php
```

Apply migration on existing databases:

```bash
php scripts/migrate.php --apply
```

Or import fresh from `db/01_schema.sql`.

## Related docs

- Public guest portal: `docs/BOOKING.md`
- Reservations admin: `modules/hotel_bookings/AGENT_NOTES.md`
