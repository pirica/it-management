# AGENT_NOTES.md - Hotel Bookings CSS

## 1. Module Purpose

Module-scoped styles for the **hotel_bookings** planning grid and related admin layout. Parent module: `modules/hotel_bookings/AGENT_NOTES.md`.

## 7. File Structure

| File | Role |
|------|------|
| `hotel-bookings.css` | Planning grid sticky columns (Room | HSK | Type), horizontal scroll, booking bar colours, maintenance OOO/OOS bar colours, toolbar spacing |

## 5. UI Behavior Requirements

- Planning table horizontal scroll moves date columns only; sticky headers/columns must remain synchronized with `js/hotel-bookings-planning.js` DOM structure.
- Global table scroll patterns live in `css/styles.css` — avoid duplicating `.audit-table-wrap` rules here.

## 10. Common Pitfalls

- Booking bar colours: row `hotel_bookings.booking_color` inline styles override CSS defaults; OOO/OOS use fixed reds/blues from maintenance status codes.
