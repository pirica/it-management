# AGENT_NOTES.md - Public booking portal

## 1. Purpose

Guest-facing hotel listing and booking under `/it-management/booking/`. Uses ITM `config/config.php` (MySQLi) and `includes/itm_hotel_booking.php`.

**Canonical doc:** `docs/BOOKING.md` (architecture, flows, admin map, review, troubleshooting).

`bootstrap.php` defines `ITM_HOTEL_BOOKING_PUBLIC_PORTAL` before `config.php` so global employee login is skipped for this tree.

## 3. Auth

- **Browse and book:** no portal login. Guest details (name, email, phone) are collected on `rooms/room-single.php` and stored via `customers` + `hotel_bookings`.
- **Manage booking:** `users/bookings.php` — last name + **reservation ID** (`hotel_bookings.id`); verified by `itm_hotel_booking_fetch_for_guest_manage()`.
- **Read reviews:** `hotel_booking_settings.reviews_url` (company default) and optional per-hotel `hotel_booking_hotels.reviews_url`; resolved via `itm_hotel_booking_resolve_reviews_url()`. Under the green rating bubbles: **Guest rating** — based on recent stays, then **Read reviews ↗** (example seed: Conrad Algarve TripAdvisor `#REVIEWS` URL).
- Optional legacy: `hotel_booking_portal_users` and `auth/login.php` / `register.php` (not required for public flow).
- **CSRF:** Public auth POST handlers use `itm_require_post_csrf()` via `bootstrap.php`.

## 4. Entry points

- `index.php` — hotel list + detail modal
- `rooms.php` — **Step 1 of 4** Select a Room.
- `rooms/select-rate.php` — **Step 2 of 4** Select a Rate (breakfast vs room-only, special requests, comments).
- `rooms/customize.php` — **Step 3 of 4**: main column upgrade card; **View room details** opens the same room-detail modal as Step 1 (no Quick compare link); right column stacks stepper then **Reservation summary** (tourist tax €2/guest/night from settings).
- `rooms/room-single.php` — **Step 4 of 4** guest form: locked check-in/out from draft; email (`filter_var`) and phone (E.164 `+` country code) validated server-side.
- `rooms/payment.php` — confirmation after step 4: **Number of nights** `(N night(s))`, **Guests** `👤 …` occupancy line, **Reservation notes**, jsPDF download (**Save booking confirmation** — same card as screen, not print preview). Occupancy stored in `hotel_bookings.notes` as `Occupancy: rooms=…` meta line.
- `rooms/confirmation-pdf.php` — session-scoped auto-PDF page (`data-hb-auto-pdf`) for the last booking in the same browser session.
- `calendar.php` — JSON nightly rates for Select Dates modal (check-in + optional check-out range; single check-in = 1 night).
- `users/bookings.php` — manage reservation (last name + reservation ID); lookup form uses **Back** (`hb-checkout-skip`); found booking renders the same confirmation panel as `rooms/payment.php` (room type title without room number, nights, guests, reservation notes, PDF + action buttons). Stay bar shows **Logout** (not **Edit stay**) linking to `auth/logout.php`. Cancelled stays show a **red** confirmation state (`Reservation cancelled`, status badge). Aside includes **Cancellation policy**, **Change booking** (modal with hotel name, directions, website, phone from `hotel_booking_hotels`), and **Cancel Booking** (future stays only; sets segment status to `CANCELLED`).
- `cancellation_policy/` — default HTML policy pages (`1_cancellation_policy.html` … `4_cancellation_policy.html`); URLs configurable per hotel in **Portal Rate Plans** admin module. Each page ends with a **Back** button (`history.go(-1)`).
- `auth/login.php`, `register.php`, `logout.php` (`logout.php` clears portal user session and returns to `index.php`)

## 5. Tenant

`hb_public_company_id()` reads `hotel_booking_settings.public_portal_enabled` per company (fallback company 1).

## 6. Admin

Hotel administration lives in ITM modules: `modules/hotel_bookings/`, `modules/hotel_booking_hotels/`, `modules/hotel_booking_rooms/`, etc. (Admin sidebar — Hospitality).

## 7. Active assets (post-legacy cleanup)

| Area | Files |
|------|--------|
| Bootstrap | `bootstrap.php` |
| Includes | `includes/portal_chrome.php`, `portal_checkout.php`, `portal_room_detail.php` |
| CSS | `css/hotel-booking-modern.css` only |
| JS | `js/hotel-booking-{public,dates,amenity-icons,select-room,customize,change-booking,confirmation-pdf}.js` |
| Images | `images/amenities/*.svg` (+ `ATTRIBUTION.md`); hotel/room photos come from `images/hotel_booking/{company_id}/…` via ITM upload helpers |

Removed legacy Colorlib template tree: `about.php`, `contact.php`, `services.php`, `404.php`, `config/config.php` (PDO), `includes/header.php` / `footer.php`, vendored `scss/`, `css/style.css`, jQuery/Bootstrap JS stack, `fonts/`, and the entire `admin-panel/` folder.
