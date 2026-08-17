# hotel_booking_portal_rate_plans

## 1. Purpose

Admin UI for **Step 2** portal rate plans (`hotel_booking_portal_rate_plans`) and **per-hotel portal pricing** stored on `hotel_booking_hotels` (breakfast add-on, child/extra-adult supplements, pet fee). Guests see computed totals on Select a Rate and later checkout steps.

## 2. Tables

- **hotel_booking_portal_rate_plans** — per-hotel `plan_slot` (1–127), `rate_plan_slug`, `name`, `cancellation_policy_url`, `cancellation_policy_html`, Step 2 merchandising (`pay_badge`, `price_label`, `cancel_template`, `plan_discount_percent`, `plan_surcharge_percent`, optional `free_cancellation_days_before_check_in`), `active`.
- **hotel_booking_hotels** (pricing columns) — `portal_breakfast_adult_price_per_night`, `portal_breakfast_child_price_per_night`, `portal_child_nightly_supplement`, `portal_extra_adult_supplement_percent`, `portal_pet_daily_fee`, **`portal_breakfast_child_age_min`** / **`portal_breakfast_child_age_max`** (Step 2 breakfast info copy; defaults **11** / **17**).

## 3. Business rules

- `itm_hotel_booking_ensure_portal_rate_plans_for_hotel()` seeds four default slots when a hotel is opened in admin (including merchandising defaults from `itm_hotel_booking_portal_rate_plan_offer()`).
- **Portal step pricing** on `index.php` (hotel selector) saves via `itm_hotel_booking_portal_save_hotel_pricing()` — one set of values per hotel, used by `itm_hotel_booking_portal_hotel_pricing()` in checkout math.
- Hotel selector header shows **Info** (`contact_email`) and **Email** (`reservations_email`) mailto links for the active property (same labels as the public portal).
- List: `itm_hotel_booking_portal_rate_plans_admin_rows()` returns all DB rows for the hotel (ordered by `plan_slot`).
- Public Step 2 (`booking/rooms/select-rate.php`) lists active plans; cancel `{date}` uses plan override days or company `hotel_booking_settings.free_cancellation_days_before_check_in`.
- Pricing: `plan_discount_percent` (0–`portal_max_discount_percent` from settings, default cap **50**) reduces BAR; `plan_surcharge_percent` (same cap) multiplies after discount. Checkout draft stores `discount_percent` + `surcharge_percent`.
- **Cancellation policy files:** `cancellation_policy_url` relative paths must normalize to `.html` / `.htm` / `.txt` only (`itm_hotel_booking_normalize_cancellation_policy_url()`). Guest checkout/modal links use **`booking/cancellation-policy.php`** (`itm_hotel_booking_portal_cancellation_policy_guest_url()`) so DB `cancellation_policy_html` is served without relying on static files alone. Defense in depth: `booking/cancellation_policy/.htaccess` denies PHP/CGI under that folder.

## 4. Helpers

- `includes/itm_hotel_booking.php` — `itm_hotel_booking_portal_quote_nightly()` (discount then surcharge), `itm_hotel_booking_portal_rate_plan_offer()`, `itm_hotel_booking_portal_rate_plan_effective_surcharge()`, `itm_hotel_booking_portal_free_cancellation_days_from_settings()`.

## 5. Regression

- `php scripts/verify_hotel_booking.php`
