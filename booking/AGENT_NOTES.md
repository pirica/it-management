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
- `rooms.php` — **Step 1 of 4** Select a Room.
- `rooms/select-rate.php` — **Step 2 of 4** Select a Rate (breakfast vs room-only, special requests, comments).
- `rooms/customize.php` — **Step 3 of 4**; left **Reservation summary** box (room, rate, change rate → step 2, room charges, tourist tax from settings, total for stay); **Skip** bypasses optional room upgrade and continues to guest details.
- `rooms/room-single.php` — **Step 4 of 4** guest details; same reservation summary column; payment total includes tourist tax (`hotel_booking_settings.tourist_tax_per_person_per_night`, adults + children per night).
- `rooms/payment.php` — payment summary (wording **payment**, not pay)
- `calendar.php` — JSON nightly rates for Select Dates modal
- `users/bookings.php` — manage reservation (last name + reservation ID)
- `auth/login.php`, `register.php`, `logout.php`

## 5. Tenant

`hb_public_company_id()` reads `hotel_booking_settings.public_portal_enabled` per company (fallback company 1).

## 6. Admin

`admin-panel/index.php` redirects to ITM `modules/hotel_bookings/`.
