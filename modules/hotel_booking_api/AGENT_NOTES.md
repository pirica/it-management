# AGENT_NOTES.md - Hotel Booking Distribution API

## 1. Module Purpose

Authenticated JSON API for **channel partners** (OTAs, channel managers, PMS bridges). Partners shop availability, create/cancel reservations, and exchange ARI (availability, rates, inventory) using per-channel API keys from **Distribution Channels** admin.

Wire format in phase 1 is **ITM native JSON**; `standard` on the channel row labels future OpenTravel / Booking.com / OHIP adapters.

## 2. Key Tables

- **hotel_booking_distribution_channels** — API key (prefix + bcrypt hash), hourly quota, `standard`, optional `webhook_url`
- **hotel_booking_distribution_mappings** — `hotel` / `room_type` internal id ↔ `external_code` per channel
- **hotel_booking_distribution_reservations** — `external_reservation_id` ↔ `hotel_bookings.id`
- **hotel_booking_distribution_ari_events** — inbound/outbound ARI audit log

Reads/writes **hotel_bookings**, **hotel_booking_rooms**, **booking_rooms_types**, rate overrides, and room-type blocks via `includes/itm_hotel_booking_distribution.php`.

## 3. Required Relationships

- Channel row is tenant-scoped (`company_id`); auth resolves company from the key — no employee session.
- Bookings require **customers** (guest email/name) and a physical **hotel_booking_rooms** row (auto-assigned by room type when `room_id` omitted).

## 4. Business Rules

- Auth: `X-API-Key` header or `api_key` query/body; invalid/missing key → `401`.
- Hourly rate limit per channel (`hourly_rate_limit`, rolling window on channel row) → `429`.
- `external_reservation_id` must be unique per channel; duplicate book → `409`.
- Cancel uses portal rules (`itm_hotel_booking_portal_guest_can_cancel_booking`) and sets future status **CANCELLED**.
- ARI push writes **hotel_booking_room_type_rate_overrides** and optional **hotel_booking_room_type_blocks** (stop-sell).

## 5. UI Behaviour

No employee UI in this folder — admin lives in `modules/hotel_booking_distribution_channels/`. `index.php` redirects to `api.php`.

## 6. API Actions

Base: `modules/hotel_booking_api/api.php` — define `ITM_HOTEL_BOOKING_DISTRIBUTION_API` skips employee login (`config/config.php`).

| Action | Method | Purpose |
|--------|--------|---------|
| *(empty)* or `probe` | GET | Channel metadata + supported actions (key check) |
| `availability` | GET | Shop: `hotel_id` or `external_hotel_code`, `check_in`, `check_out`, occupancy |
| `ari_snapshot` | GET | Outbound ARI pull for date range |
| `book` | POST | JSON: `external_reservation_id`, dates, guest, `room_type_id` / codes |
| `cancel` | POST | JSON: `external_reservation_id` |
| `ari_push` | POST | JSON: rates array and/or `stop_sell` window |

## 7. File Structure

- **api.php** — JSON router (only entry)
- **index.php** — redirect to `api.php`
- **index.html** — directory listing guard

## 8. Multi-Tenant Rules

- Every query scoped by `company_id` from the authenticated channel row.
- Hotel/room-type external codes resolve only within that channel’s mapping rows.

## 9. Security

- API keys stored as bcrypt hash; plain key shown once on channel create (admin).
- No CSRF (machine clients); no `itm_api_enforce_rate_limit_or_exit()` (uses channel hourly limit instead).

## 10. Pitfalls

- Module access gate is skipped when `ITM_HOTEL_BOOKING_DISTRIBUTION_API` is set — do not remove that constant from `api.php`.
- Partners must map hotels/room types in admin before using `external_*_code` parameters.
- Standard values `opentravel`, `booking_com`, `ohip` are labels only until adapter phases ship.

## 12. Module Owner Notes

- Canonical design: `docs/HOTEL_BOOKING_DISTRIBUTION.md`
- Helpers: `includes/itm_hotel_booking_distribution.php`
- Admin: [hotel_booking_distribution_channels/index.php](http://localhost/it-management/modules/hotel_booking_distribution_channels/index.php) (Admin session)
- Regression: [verify_hotel_booking_distribution.php?run=1](http://localhost/it-management/scripts/verify_hotel_booking_distribution.php?run=1)
