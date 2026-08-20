# AGENT_NOTES.md - Hotel Bookings CSS

## 1. Module Purpose

Module-scoped styles for the **hotel_bookings** planning grid and related admin layout. Parent module: `modules/hotel_bookings/AGENT_NOTES.md`.

## 7. File Structure

| File | Role |
|------|------|
| `hotel-bookings.css` | Planning grid sticky columns (Room | HSK | Type), Date/Days/Search filter row, view/hide filters, unassigned empty row, Last room fieldset, horizontal scroll, booking bar colours, maintenance OOO/OOS bar colours, toolbar spacing |

## 5. UI Behavior Requirements

- Planning table horizontal scroll moves date columns only; sticky headers/columns must remain synchronized with `js/hotel-bookings-planning.js` DOM structure.
- Thead **⬅️** / **➡️** (`.hb-plan-date-nav`) are centered in the cell (`inline-flex` + `width:100%` on `.hb-plan-date-arrow`).
- `.hb-plan-filters-row` keeps **Date**, **Days**, and Search on one horizontal row (`flex-end`, wrappers `.hb-plan-date-filter` / `.hb-plan-days-filter` / `.hb-plan-search-filter`, shared `41px` control height) so inputs and the Search button share one baseline.
- `.hb-plan-filters` / `.hb-plan-view-filters` use `12px` bottom margin between the Date/Days/Search row and the All/Arrivals/… view buttons (matches `.hb-plan-hide-filters` spacing).
- Global table scroll patterns live in `css/styles.css` — avoid duplicating `.audit-table-wrap` rules here.

## 10. Common Pitfalls

- Booking bar colours: row `hotel_bookings.booking_color` inline styles override CSS defaults; OOO/OOS use fixed reds/blues from maintenance status codes.
