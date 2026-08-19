# AGENT_NOTES.md - Hotel Bookings

## 1. Module Purpose

Bespoke hospitality hub: room **Planning** grid (Date + Days filters, company hotel), **Future / Present / History** boards, and CRUD for `hotel_bookings`. Every booking requires `customer_id` (ITM `customers`). One hotel per company (`itm_hotel_booking_photo_default_hotel_id()`).

## 2. Key Tables

- **hotel_bookings** — reservations (`room_id`, `check_in`, `check_out`, `payment_amount`, **`auth2`** guest manage PIN (random 4 digits), `portal_rate_plan_id` → `hotel_booking_portal_rate_plans`, **`internal_rate_code`** (`use` / `comp` / empty — USE waives room only, COMP all charges), `booking_color` planning bar `#rrggbb`, segment status FKs)
- **hotel_booking_last_rooms** — last-room snapshot (`booking_id` reservation id, room number/name, hotel, type, floor) stamped when status is **CANCELLED** or **NO-SHOW**
- **hotel_bookings_future / present / history** — lifecycle status lookups (no ENUM)
- **hotel_booking_rooms**, **hotel_booking_hotels**, **booking_rooms_types** — inventory (separate CRUD modules)

## 3. Required Relationships

- `customer_id` → `customers` (mandatory, tenant-scoped)
- `room_id` → `hotel_booking_rooms`
- Segment status columns → matching `hotel_bookings_*` table by date vs today

## 4. Shared helpers

`includes/itm_hotel_booking.php` — segment resolution, overlap/cancelled checks, planning grid, portal customer ensure, **guest auth2 PIN** (`itm_hotel_booking_generate_auth2`, manage/cancel lookup), photo upload paths, HSK rotate (`itm_hotel_booking_rotate_room_housekeeping_status`), portal rate plan CRUD helpers.

## 5. Planning grid

- Sticky columns **Room | HSK | Type** stay fixed while horizontal scroll moves date columns only; **⬅️** / **➡️** shift the anchor by the current **Days** window (filters and sort preserved).
- Planning filters **Date** (`anchor` query) and **Days** sit on one `.hb-plan-filters-row` with Search. There is no Hotel dropdown: the grid and `ajax_action=planning_grid` always use that company’s hotel. Hide checkboxes stay on a separate `.hb-plan-hide-filters` row (`hb_hide[]`).
- Planning view buttons **All / Arrivals / Departures / In-house / Future** (`hb_view`) use the **Date** field as business `$today`. Hide checkboxes (`hb_hide[]`) omit bars by resolved status name (NO-SHOW, CANCELLED, IN-HOUSE, CHECKED-OUT, DUE-OUT, DUE-IN).
- **CANCELLED** and **NO-SHOW** bars render on an **empty** bottom row (no room number / HSK / type). Last-room snapshot is stored in `hotel_booking_last_rooms` keyed by `booking_id`.
- Sortable headers: `plan_sort` = `room` | `hk` | `type`, `plan_dir` = `asc` | `desc` (`itm_hotel_booking_planning_sort_rooms()`).
- **HSK** badge shows `hotel_booking_housekeeping_statuses.code` (fallback `name`); double-click rotates via `ajax_action=hk_rotate`.
- Booking bars span **half check-in cell** (right) through **half check-out cell** (left); **same-day turnover** renders checkout (left) and next check-in (right) in one cell when dates align.
- Each booking uses `hotel_bookings.booking_color` on the planning grid (fallback palette by id when NULL); **OOO** maintenance is **red** (`#c62828`), **OOS** is **blue** (`#1565c0`) from `hotel_booking_housekeeping_maintenance` + `hotel_booking_housekeeping_maintenance_status.code`.
- Helpers: `itm_hotel_booking_planning_match_bookings_for_day()`, `itm_hotel_booking_planning_match_maintenance_for_day()` in `includes/itm_hotel_booking.php`.
- Double-click booking bar opens **view.php**; **Room** column double-click opens `hotel_booking_rooms/view.php?id=` (plain cell text — no link styling); HSK column double-click rotates `hotel_booking_housekeeping_statuses` via `ajax_action=hk_rotate`.
- **Drag-and-drop** on planning bars (bookings + OOO/OOS maintenance): drop on any day cell to shift dates and/or move to another room row; `POST index.php?ajax_action=planning_move` with CSRF. Helpers: `itm_hotel_booking_planning_move_booking()`, `itm_hotel_booking_planning_move_maintenance()`, overlap checks `itm_hotel_booking_has_overlap()` / `itm_hotel_booking_maintenance_has_overlap()`.
- Double-click **OOO/OOS** bar opens **HSK Maintenance** edit in a modal iframe (`hotel_booking_housekeeping_maintenance/edit.php?id=&embed=1`); save reloads the planning grid.
- JS: `js/hotel-bookings-planning.js`.

## 5a. Create / edit form

- Shared markup: `includes/hb_booking_form.php` — all `hotel_bookings` business columns (customer, room, **Last room** readonly snapshot from `hotel_booking_last_rooms`, check-in/out, payment, **internal rate** select (`internal_rate_code`), **auth2** read-only on edit / auto-generated on create, **portal rate plan** select with ➕ modal create, Planning color via `type="color"` → `booking_color`, three segment status FKs, notes, `active` checkbox). `hb_booking_compute_suggested_payment()` applies internal waivers on save; `js/hotel-bookings-date-picker.js` suggests payment on room/date/internal-rate change.
- Portal rate plan options filter by selected room’s `hotel_id`; defaults seeded per hotel when the form loads. **➕** is the standard `__add_new__` option inside the plan `<select>` (opens create modal without requiring a room — embed form includes hotel select; room choice only pre-fills `hotel_id`). **🔎** / **✏️** beside the select open view/edit in a modal iframe (`embed=1`). `hb_booking_end_form_page()` renders the modal outside `.content`. Regression: `php scripts/check_hotel_bookings_rate_plan_form.php` and `php scripts/lib/itm_hospitality_booking_form_probe.php create|edit`.
- Check-in / check-out use hospitality date fields (`d/M/Y` + 📅 via `js/hotel-date-input.js`); planning **Date** uses the same widget (`name="anchor"`); `js/hotel-bookings-date-picker.js` enforces check-out after check-in.
- Audit meta (`created_by`, `created_at`, `updated_by`, `updated_at`) via hidden inputs from `itm_crud_render_form_hidden_audit_inputs()`; `company_id` stays session-scoped (not on form).
- `view.php` shows every stored column including segment status labels and audit fields; also joins the room’s hotel for **Info** (`contact_email`) and **Email** (`reservations_email`) mailto links plus phone.

## 6. Public portal

`booking/` — MySQLi bootstrap, portal users → `customers`; inserts into `hotel_bookings`. Payment page: `booking/rooms/payment.php`. See **`docs/BOOKING.md`** for full portal review and flows.

**Distribution API (channel partners):** `modules/hotel_booking_api/api.php` — not the guest portal; see **`docs/HOTEL_BOOKING_DISTRIBUTION.md`**.

## 6. Regression

`php scripts/verify_hotel_booking.php` after DDL/seeds or helper changes (includes subprocess probes for all 13 Hospitality sidebar `index.php` files).

## 7. Admin page shell

Bespoke admin entry files (`index.php`, `create.php`, `edit.php`, `view.php`) and `hotel_booking_settings` / `hotel_booking_special_rates` / `hotel_booking_portal_rate_plans` use `includes/itm_hospitality_admin_layout.php` (`itm_hospitality_admin_layout_begin` / `end`) — **not** `includes/footer.php` (does not exist).
