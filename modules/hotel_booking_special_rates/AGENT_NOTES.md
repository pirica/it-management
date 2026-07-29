# AGENT_NOTES.md - Booking Special Rates

## 1. Module Purpose

Admin UI to set **discount_percent** (and **active**) per hotel for canonical portal special rates: program checkboxes and code inputs on `booking/rooms.php` (Special rates modal).

## 2. Key Tables

- **hotel_booking_special_rates** — `(company_id, hotel_id, rate_slug)` unique; `discount_percent`, `name`, `description`, `active`, audit + soft-delete.

## 3. Business Rules

- Canonical slugs are defined in `itm_hotel_booking_canonical_special_rate_definitions()` (programs via `itm_hotel_booking_portal_rate_program_options()` + codes via `itm_hotel_booking_portal_code_rate_options()`).
- `itm_hotel_booking_ensure_special_rates_for_hotel()` inserts missing slug rows (0% default) when the admin page loads or saves.
- Portal resolves guest selection with `itm_hotel_booking_portal_resolved_rate_slug()` and applies `itm_hotel_booking_special_rate_discount()`.
- Code fields (promotion, group, corporate, member) apply the discount for slug `promo`, `group`, `corporate`, `member` when the guest enters any valid 8-character code (validation of specific codes is not implemented).

## 4. UI

- Bespoke `index.php` only: hotel selector + table of discount % and active flags (not full flattened CRUD).

## 5. Pitfalls

- Discounts are **per hotel**; each property needs rows (seeded in `db/02_data.sql` for company 1 hotel 1).
- Changing seeds does not update live DB rows; edit via this module or SQL.
