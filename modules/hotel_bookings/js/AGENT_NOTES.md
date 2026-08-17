# AGENT_NOTES.md - Hotel Bookings JavaScript

## 1. Module Purpose

Client-side behaviour for the **hotel_bookings** planning grid and booking date fields. Parent module: `modules/hotel_bookings/AGENT_NOTES.md`.

## 7. File Structure

| File | Role |
|------|------|
| `hotel-bookings-planning.js` | Planning grid: anchor navigation (⬅️/➡️), drag-and-drop moves (`ajax_action=planning_move`), HSK double-click rotate, **Room** column double-click → `hotel_booking_rooms/view.php`, OOO/OOS modal iframe, bar double-click to view |
| `hotel-bookings-date-picker.js` | Create/edit check-in vs check-out validation; coordinates with `js/hotel-date-input.js` |

## 5. UI Behavior Requirements

- Planning moves POST to `index.php` with CSRF — server helpers `itm_hotel_booking_planning_move_booking()` / `itm_hotel_booking_planning_move_maintenance()`.
- Do not break sticky column layout paired with `css/hotel-bookings.css`.

## 10. Common Pitfalls

- AJAX actions must stay aligned with `index.php` `ajax_action` dispatch table.
- Date picker assumes hospitality date input markup from `hb_booking_form.php`.
