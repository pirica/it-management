# hotel_booking_portal_rate_plans

## 1. Purpose

Admin UI for **Step 2** portal rate plans (`hotel_booking_portal_rate_plans`) and **per-hotel portal pricing** stored on `hotel_booking_hotels` (breakfast add-on, child/extra-adult supplements, pet fee). Guests see computed totals on Select a Rate and later checkout steps.

## 2. Tables

- **hotel_booking_portal_rate_plans** — per-hotel `plan_slot` (1–127), `rate_plan_slug`, `name`, `cancellation_policy_url`, `cancellation_policy_html`, `active`.
- **hotel_booking_hotels** (pricing columns) — `portal_breakfast_adult_price_per_night`, `portal_breakfast_child_price_per_night`, `portal_child_nightly_supplement`, `portal_extra_adult_supplement_percent`, `portal_pet_daily_fee`.

## 3. Business rules

- `itm_hotel_booking_ensure_portal_rate_plans_for_hotel()` seeds four default slots when a hotel is opened in admin.
- **Portal step pricing** on `index.php` (hotel selector) saves via `itm_hotel_booking_portal_save_hotel_pricing()` — one set of values per hotel, used by `itm_hotel_booking_portal_hotel_pricing()` in checkout math.
- List: `itm_hotel_booking_portal_rate_plans_admin_rows()` returns all DB rows for the hotel (ordered by `plan_slot`).
- Public Step 2 (`booking/rooms/select-rate.php`) lists active plans; selected plan id is stored on the booking at checkout.
- **Cancellation policy files:** `cancellation_policy_url` relative paths must normalize to `.html` / `.htm` / `.txt` only (`itm_hotel_booking_normalize_cancellation_policy_url()`). Defense in depth: `booking/cancellation_policy/.htaccess` denies PHP/CGI under that folder.

## 4. Helpers

- `includes/itm_hotel_booking.php` — `itm_hotel_booking_portal_pricing_defaults()`, `itm_hotel_booking_portal_hotel_pricing()`, `itm_hotel_booking_portal_save_hotel_pricing()`, `itm_hotel_booking_portal_quote_nightly()` (accepts pricing array).

## 5. Regression

- `php scripts/verify_hotel_booking.php`
