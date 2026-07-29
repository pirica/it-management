# AGENT_NOTES.md - Public booking portal

## 1. Purpose

Guest-facing hotel listing and booking under `/it-management/booking/`. Uses ITM `config/config.php` (MySQLi) and `includes/itm_hotel_booking.php`.

`bootstrap.php` defines `ITM_HOTEL_BOOKING_PUBLIC_PORTAL` before `config.php` so global employee login is skipped for this tree.

## 3. Auth

- **Browse and book:** no portal login. Guest details (name, email, phone) are collected on `rooms/room-single.php` and stored via `customers` + `hotel_bookings`.
- **Manage booking:** `users/bookings.php` — last name + **reservation ID** (`hotel_bookings.id`); verified by `itm_hotel_booking_fetch_for_guest_manage()`.
- **Read reviews:** `hotel_booking_settings.reviews_url` (company default) and optional per-hotel `hotel_booking_hotels.reviews_url`; resolved via `itm_hotel_booking_resolve_reviews_url()`. Under the green rating bubbles: **Guest rating** — based on recent stays, then **Read reviews ↗** (example seed: Conrad Algarve TripAdvisor `#REVIEWS` URL).
- Optional legacy: `hotel_booking_portal_users` and `auth/login.php` / `register.php` (not required for public flow).

## 4. Entry points

- `index.php` — hotel list + detail modal
- `rooms.php` — full **Select a Room** page (step 1, stay bar, room grid, hotel sidebar). **Rooms & guests**, filters, **Special rates** modal (one program checkbox at a time with **%** from admin; code fields show **%**; Apply updates prices in-page). Admin: **modules/hotel_booking_special_rates/** per hotel. **View room details** modal (`includes/portal_room_detail.php`): **Read more / Read less** description; **More room details** accordion (chevron); sample spec/comfort copy when type data is sparse. Amenities use `hotel_booking_amenities` / SVG icons.
- `rooms/payment.php` — payment summary (wording **payment**, not pay)
- `calendar.php` — JSON nightly rates for Select Dates modal
- `users/bookings.php` — manage reservation (last name + reservation ID)
- `auth/login.php`, `register.php`, `logout.php`

## 5. Tenant

`hb_public_company_id()` reads `hotel_booking_settings.public_portal_enabled` per company (fallback company 1).

## 6. Admin

`admin-panel/index.php` redirects to ITM `modules/hotel_bookings/`.
