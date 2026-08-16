# AGENT_NOTES.md - Hotel Bookings Includes

## 1. Module Purpose

Shared PHP partials for the bespoke **hotel_bookings** module — not a standalone CRUD entry. Parent module: `modules/hotel_bookings/AGENT_NOTES.md`.

## 7. File Structure

| File | Role |
|------|------|
| `hb_booking_form.php` | Shared create/edit form for `hotel_bookings` — customer, room, check-in/out, payment, **auth2** PIN, portal rate plan (➕ / modal view/edit), planning `booking_color`, segment status FKs, notes, `active` checkbox, audit hidden inputs |

## 4. Business Rules (Critical for Agents)

- Portal rate plan `<select>` filters by room’s `hotel_id`; **➕** uses `__add_new__` with modal iframe (`embed=1`) to `hotel_booking_portal_rate_plans`.
- Check-in/out use hospitality date widgets (`d/M/Y` + `js/hotel-date-input.js`); `js/hotel-bookings-date-picker.js` enforces check-out after check-in.
- `company_id` is session-scoped — not exposed on the form.

## 10. Common Pitfalls

- Changing field order or names here requires matching POST handlers in `hotel_bookings/index.php` / `create.php` / `edit.php`.
- Rate plan modal regression: `php scripts/check_hotel_bookings_rate_plan_form.php`
