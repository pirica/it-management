# AGENT_NOTES.md - Public booking portal

## 1. Purpose

Guest-facing hotel listing and booking under `/it-management/booking/`. Uses ITM `config/config.php` (MySQLi) and `includes/itm_hotel_booking.php`.

## 2. Auth

- `hotel_booking_portal_users` linked to `customers` via `itm_hotel_booking_ensure_customer_for_portal()`
- Session: `hotel_booking_customer_id`

## 3. Entry points

- `index.php` — hotel list + detail modal
- `rooms.php`, `rooms/room-single.php` — room list and date booking form
- `rooms/payment.php` — payment summary (wording **payment**, not pay)
- `users/bookings.php` — signed-in guest history
- `auth/login.php`, `register.php`, `logout.php`

## 4. Tenant

`hb_public_company_id()` reads `hotel_booking_settings.public_portal_enabled` per company (fallback company 1).

## 5. Admin

`admin-panel/index.php` redirects to ITM `modules/hotel_bookings/`.
