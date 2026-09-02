# AGENT_NOTES.md - Booking Special Rates

## 1. Module Purpose

Admin UI to set **discount_percent** (and **active**) per hotel for canonical portal special rates: program checkboxes and code inputs on `booking/rooms.php` (Special rates modal). **Registered codes** for promotion, group, corporate, and member rates are managed on the same page.

## 2. Key Tables

- **hotel_booking_special_rates** — `(company_id, hotel_id, rate_slug)` unique; `discount_percent`, `name`, `description`, `active`, audit + soft-delete; `trg_hotel_booking_special_rates_audit_*` in `db/03_triggers.sql`.
- **hotel_booking_special_rate_codes** — per-hotel rows for code-type slugs (`promo`, `group`, `corporate`, `member`): `code` (8-char, normalized uppercase), optional `label`, optional `valid_from` / `valid_to`, `active`, audit + soft-delete; unique `(company_id, hotel_id, rate_slug, code)`; `trg_hotel_booking_special_rate_codes_audit_*` in `db/03_triggers.sql`. Migration: `db/migrations/hotel_booking_special_rate_codes.sql`.

## 3. Business Rules

- Canonical slugs are defined in `itm_hotel_booking_canonical_special_rate_definitions()` (programs via `itm_hotel_booking_portal_rate_program_options()` + codes via `itm_hotel_booking_portal_code_rate_options()`).
- `itm_hotel_booking_ensure_special_rates_for_hotel()` inserts missing slug rows (0% default) when the admin page loads or saves.
- Portal resolves guest selection with `itm_hotel_booking_portal_resolved_rate_slug()` and applies `itm_hotel_booking_special_rate_discount()`.
- **Code fields** (promotion, group, corporate, member): discount applies only when the guest enters a code that exists in `hotel_booking_special_rate_codes` for that hotel and `rate_slug`, is active, not soft-deleted, and (when set) within `valid_from` / `valid_to` for the stay check-in date. Helpers: `itm_hotel_booking_portal_special_rate_code_is_valid()`, `itm_hotel_booking_portal_filter_occupancy_special_rate_codes()`. AJAX probe: `booking/validate-special-rate-code.php`. Step 1 Apply validates via `booking/js/hotel-booking-select-room.js` before updating prices.
- Program checkboxes (AAA, senior, …) are unchanged — no per-code list.

## 4. UI

- Bespoke `index.php` only: hotel selector + table of discount % and active flags; below that, per-slug code lists with add/remove (not full flattened CRUD).

## 5. Pitfalls

- Discounts are **per hotel**; each property needs rows (seeded in `db/02_data.sql` for company 1 hotel 1).
- Sample codes in seeds (`SAVE10`, `GROUP01`, …) apply only after migration or fresh `db/` import.
- Changing seeds does not update live DB rows; edit via this module or SQL.
- If no codes are registered for a slug, guests cannot use that code discount even when the special-rate row is active.
