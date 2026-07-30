# AGENT_NOTES.md - Hotel Bookings

## 1. Module Purpose

Bespoke hospitality hub: room **Planning** grid (anchor date, hotel filter), **Future / Present / History** boards, and CRUD for `hotel_bookings`. Every booking requires `customer_id` (ITM `customers`).

## 2. Key Tables

- **hotel_bookings** — reservations (`room_id`, `check_in`, `check_out`, `payment_amount`, segment status FKs)
- **hotel_bookings_future / present / history** — lifecycle status lookups (no ENUM)
- **hotel_booking_rooms**, **hotel_booking_hotels**, **booking_rooms_types** — inventory (separate CRUD modules)

## 3. Required Relationships

- `customer_id` → `customers` (mandatory, tenant-scoped)
- `room_id` → `hotel_booking_rooms`
- Segment status columns → matching `hotel_bookings_*` table by date vs today

## 4. Shared helpers

`includes/itm_hotel_booking.php` — segment resolution, overlap/cancelled checks, planning grid, portal customer ensure, photo upload paths, HK rotate (`itm_hotel_booking_rotate_room_housekeeping_status`), portal rate plan CRUD helpers.

## 5. Planning grid

- Booking bars span **half check-in cell** (right) through **half check-out cell** (left); double-click bar opens **view.php**.
- HK column double-click rotates to next active `hotel_booking_housekeeping_statuses` via `ajax_action=hk_rotate`.
- JS: `js/hotel-bookings-planning.js`.

## 5a. Create / edit form

- Shared markup: `includes/hb_booking_form.php` — all `hotel_bookings` business columns (customer, room, check-in/out, payment, three segment status FKs, notes, `active` checkbox).
- Check-in / check-out use native calendar inputs (`type="date"`, labels note dd/mm/yyyy display); `js/hotel-bookings-date-picker.js` enforces check-out after check-in.
- Audit meta (`created_by`, `created_at`, `updated_by`, `updated_at`) via hidden inputs from `itm_crud_render_form_hidden_audit_inputs()`; `company_id` stays session-scoped (not on form).
- `view.php` shows every stored column including segment status labels and audit fields.

## 6. Public portal

`booking/` — MySQLi bootstrap, portal users → `customers`; inserts into `hotel_bookings`. Payment page: `booking/rooms/payment.php`. See **`docs/BOOKING.md`** for full portal review and flows.

## 6. Regression

`php scripts/verify_hotel_booking.php` after DDL/seeds or helper changes (includes subprocess probes for all 13 Hospitality sidebar `index.php` files).

## 7. Admin page shell

Bespoke admin entry files (`index.php`, `create.php`, `edit.php`, `view.php`) and `hotel_booking_settings` / `hotel_booking_special_rates` / `hotel_booking_portal_rate_plans` use `includes/itm_hospitality_admin_layout.php` (`itm_hospitality_admin_layout_begin` / `end`) — **not** `includes/footer.php` (does not exist).
