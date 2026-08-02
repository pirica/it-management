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
| Booking.com Connectivity API | `includes/itm_hotel_booking_distribution_booking_com_connect.php` |
| Partner secrets | `includes/itm_hotel_booking_distribution_secrets.php` |
| Webhook queue + signatures | `includes/itm_hotel_booking_distribution_webhooks.php` |
| Deeper ARI (LOS, restrictions, delta) | `includes/itm_hotel_booking_distribution_ari_deep.php` |
| Oracle OHIP JSON | `includes/itm_hotel_booking_distribution_ohip.php` |
| Channel admin | `modules/hotel_booking_distribution_channels/` |

Auth: `X-API-Key` (or `api_key`). No employee session (`ITM_HOTEL_BOOKING_DISTRIBUTION_API`).

## Wire formats

| Channel `standard` | Request | Response |
|--------------------|---------|----------|
| `itm_native` | JSON (default) | JSON |
| `opentravel` | XML (`OTA_HotelAvailRQ`, `OTA_HotelResNotifRQ`) or `format=xml` | `OTA_HotelAvailRS`, `OTA_HotelResNotifRS`, `OTA_HotelAvailNotifRS` |
| `booking_com` | Booking.com Connectivity JSON subset | Wrapped JSON (`room_rates`, reservation ids, ACK/NACK) |
| `ohip` | OHIP JSON subset | Wrapped JSON (`status`, `confirmationId`, ACK/NACK) |

Override with `?format=json` or `?format=xml`, or `Accept: application/xml`.

## Endpoints

Base: `/it-management/modules/hotel_booking_api/api.php`

| Action | Method | Description |
|--------|--------|-------------|
| `probe` | GET | Validate API key |
| `availability` | GET / POST | Shop (JSON query or OpenTravel XML body) |
| `ari_snapshot` | GET | Pull ARI inventory/rates (enriched with rate-plan mappings + restrictions) |
| `ari_push_outbound` | POST | POST ARI snapshot to channel `webhook_url` and/or Booking.com API; `force=1` bypasses delta checksum skip |
| `book` | POST | Create reservation |
| `modify` | POST | Amend dates/room/guest by `external_reservation_id` |
| `cancel` | POST | Cancel by `external_reservation_id` |
| `notify` | POST | Inbound OTA notification (routes to book/modify/cancel) |
| `ari_push` | POST | Inbound rates / stop-sell into ITM |

## Phase 3 — production webhooks & partner credentials

### Inbound signature validation

When **Webhook signing secret** is set on a channel, every inbound `POST` must include `X-ITM-Signature: sha256=<hmac>` (also accepts `X-Hub-Signature-256`, `X-Booking-Signature`, `X-Signature`). HMAC is SHA-256 over the raw request body.

### Outbound webhook queue

`push_ari_to_webhook()` enqueues deliveries in `hotel_booking_distribution_webhook_queue`, attempts immediate delivery, and records `pending` / `failed` / `delivered` / `dead` status with exponential backoff.

Cron / manual retry:

```bash
php scripts/run_hotel_booking_distribution_webhook_queue.php
```

Rows exceeding `webhook_max_attempts` move to **dead** (dead-letter log in the queue table).

### Booking.com Connectivity

Channel edit form stores:

- Partner API username / password (encrypted)
- Property ID
- Sandbox mode flag

When `standard = booking_com` and credentials are present, outbound ARI also posts to the Booking.com rates endpoint. Reservation responses include certified **ACK** / **NACK** wrappers; `hotel_booking_distribution_reservations.ack_status` tracks delivery.

### Rate-plan mapping & deeper ARI

Per channel + hotel, map portal rate plans to external codes with LOS and price multipliers (`hotel_booking_distribution_rate_plan_mappings`). Per-day restrictions (CTA, CTD, stop-sell, derived price) live in `hotel_booking_distribution_ari_restrictions`. `build_ari_snapshot()` enriches inventory with `rate_plans[]` per day.

Delta push: unchanged snapshots are skipped unless `force=1` on `ari_push_outbound` (body or query).

## Outbound ARI to OTAs

1. **On demand:** `POST ?action=ari_push_outbound` (authenticated channel) or **Push ARI** on channel view when `webhook_url` is set. Add `force=1` to bypass checksum skip.
2. **Scheduled:** `php scripts/run_hotel_booking_distribution_ari_sync.php` — pushes to all channels with `webhook_url` (cron daily).
3. **Retries:** `php scripts/run_hotel_booking_distribution_webhook_queue.php` — processes pending/failed queue rows.

Payload format follows channel `standard` (XML for OpenTravel, partner JSON for Booking.com / OHIP).

## Regression & examples

```bash
php scripts/verify_hotel_booking_distribution.php
php scripts/verify_hotel_booking_distribution_http.php
php scripts/verify_hotel_booking_distribution_opentravel_coverage.php
php scripts/verify_hotel_booking_distribution_booking_com_cert.php
php scripts/report_hotel_booking_distribution_webhook_ops.php
php scripts/run_hotel_booking_distribution_ari_sync.php
php scripts/run_hotel_booking_distribution_webhook_queue.php
```

### Ops monitoring

- Channel **view** shows per-channel webhook queue counts and recent `dead` / `failed` rows.
- Channel **index** shows a **Dead webhooks** badge per channel.
- [report_hotel_booking_distribution_webhook_ops.php?run=1](http://localhost/it-management/scripts/report_hotel_booking_distribution_webhook_ops.php?run=1) — tenant-wide dead-letter report (exit `1` when dead rows exist).

### OpenTravel message coverage

| Message | Direction | Action |
|---------|-----------|--------|
| `OTA_HotelAvailRQ` / `OTA_HotelAvailRS` | Shop | `availability` |
| `OTA_HotelResNotifRQ` / `OTA_HotelResNotifRS` | Reservation | `notify` / `book` / `modify` / `cancel` |
| `OTA_HotelAvailNotifRQ` / `OTA_HotelAvailNotifRS` | ARI | `ari_push` inbound / `ari_snapshot` outbound |
| `OTA_PingRQ` / `OTA_PingRS` | Health | `probe` |

### Booking.com certification (offline checklist)

`verify_hotel_booking_distribution_booking_com_cert.php` validates adapter contracts without calling the live API. Production certification still requires Booking.com sandbox credentials and their official test hotel.

### API examples (`api-examples/`)

Set `ITM_DIST_API_KEY` to a channel plain-text API key from [Distribution Channels](http://localhost/it-management/modules/hotel_booking_distribution_channels/index.php). For modify/cancel, set `ITM_DIST_EXTERNAL_RESERVATION_ID` to an existing partner reservation id.

| Example | Action |
|---------|--------|
| `hotel_distribution_probe.php` | `probe` |
| `hotel_distribution_availability.php` | `availability` |
| `hotel_distribution_ari_snapshot.php` | `ari_snapshot` |
| `hotel_distribution_book.php` | `book` |
| `hotel_distribution_notify_book.php` | `notify` (book) |
| `hotel_distribution_modify.php` | `modify` |
| `hotel_distribution_cancel.php` | `cancel` |

Also listed in [scripts/api.php](http://localhost/it-management/scripts/api.php).

## Related

- Guest portal: `docs/BOOKING.md`
- Reservations: `modules/hotel_bookings/AGENT_NOTES.md`
