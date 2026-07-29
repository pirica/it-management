# AGENT_NOTES.md - Public booking portal

## 1. Purpose

Guest-facing hotel listing and booking under `/it-management/booking/`. Uses ITM `config/config.php` (MySQLi) and `includes/itm_hotel_booking.php`.

`bootstrap.php` defines `ITM_HOTEL_BOOKING_PUBLIC_PORTAL` before `config.php` so global employee login is skipped for this tree.

## 3. Auth

- **Browse and book:** no portal login. Guest details (name, email, phone) are collected on `rooms/room-single.php` and stored via `customers` + `hotel_bookings`.
- **Manage booking:** `users/bookings.php` — last name + **reservation ID** (`hotel_bookings.id`); verified by `itm_hotel_booking_fetch_for_guest_manage()`.
- **Read reviews:** `hotel_booking_settings.reviews_url` (company default) and optional per-hotel `hotel_booking_hotels.reviews_url`; resolved via `itm_hotel_booking_resolve_reviews_url()`. Under the green rating bubbles: **Guest rating** — based on recent stays, then **Read reviews ↗** (example seed: Conrad Algarve TripAdvisor `#REVIEWS` URL).
- Optional legacy: `hotel_booking_portal_users` and `auth/login.php` / `register.php` (not required for public flow).
- **CSRF:** Public auth and legacy `admin-panel/` POST forms use `itm_require_post_csrf()` (via `bootstrap.php` or `includes/portal_csrf.php` for PDO admin scripts).

## 4. Entry points

- `index.php` — hotel list + detail modal
- `rooms.php` — **Step 1 of 4** Select a Room.
- `rooms/select-rate.php` — **Step 2 of 4** Select a Rate (breakfast vs room-only, special requests, comments).
- `rooms/customize.php` — **Step 3 of 4**: main column upgrade card; **View room details** opens the same room-detail modal as Step 1 (no Quick compare link); right column stacks stepper then **Reservation summary** (tourist tax €2/guest/night from settings).
- `rooms/room-single.php` — **Step 4 of 4** guest form: locked check-in/out from draft; email (`filter_var`) and phone (E.164 `+` country code) validated server-side.
- `rooms/payment.php` — confirmation after step 4: **Number of nights** `(N night(s))`, **Guests** `👤 …` occupancy line, **Reservation notes**, jsPDF download (**Save booking confirmation** — same card as screen, not print preview). Occupancy stored in `hotel_bookings.notes` as `Occupancy: rooms=…` meta line.
- `calendar.php` — JSON nightly rates for Select Dates modal (check-in + optional check-out range; single check-in = 1 night).
- `users/bookings.php` — manage reservation (last name + reservation ID); lookup form uses **Back** (`hb-checkout-skip`); found booking renders the same confirmation panel as `rooms/payment.php` (room type title without room number, nights, guests, reservation notes, PDF + action buttons). Cancelled stays show a **red** confirmation state (`Reservation cancelled`, status badge). Aside includes **Cancellation policy**, **Change booking** (modal with hotel name, directions, website, phone from `hotel_booking_hotels`), and **Cancel Booking** (future stays only; sets segment status to `CANCELLED`).
- `cancellation_policy/` — default HTML policy pages (`1_cancellation_policy.html` … `4_cancellation_policy.html`); URLs configurable per hotel in **Portal Rate Plans** admin module.
- `auth/login.php`, `register.php`, `logout.php`

## 5. Tenant

`hb_public_company_id()` reads `hotel_booking_settings.public_portal_enabled` per company (fallback company 1).

## 6. Admin

`admin-panel/index.php` redirects to ITM `modules/hotel_bookings/`.
