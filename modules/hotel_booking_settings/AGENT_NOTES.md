# AGENT_NOTES.md - Hotel Booking Settings

## 1. Module Purpose

Tenant configuration for the public `booking/` portal and shared copy (welcome text, footnotes, airport info, accessible features default).

## 2. Key Tables

- **hotel_booking_settings** — one row per `company_id`; includes `reviews_url` (external TripAdvisor/reviews page, opened `target="_blank"` from the portal), `tourist_tax_per_person_per_night` (decimal EUR per guest per night for portal steps 3–4), `free_cancellation_days_before_check_in` (default **5** for Step 2 `{date}` cancel copy), `calendar_month_advance_days_left` (default **3**; Select Dates auto-advance when days left in check-in month is below this value; **0** disables), `show_discount_strikethrough` (default **1**; when enabled, portal Step 1/2 show list-price strikethrough next to discounted sale price), **`portal_complimentary_min_rooms_paid`** / **`portal_complimentary_rooms_free`** (multi-room complimentary credit — `0` min rooms = disabled), **`portal_confirmation_email_guest`** / **`portal_confirmation_email_reservations`** (Step 4 confirmation emails — default **on**), `urlmybooking` (external URL to manage booking, default 'https://localhost/it-management/booking/users/bookings.php'), and **Stripe Checkout** (`stripe_enabled`, `stripe_mode`, publishable key, encrypted secret + webhook signing secret, `deposit_percent`). Canonical doc: `docs/STRIPE_CHECKOUT.md`.

## 3. Business Rules

- `reviews_url` must be `http://` or `https://` (normalized via `itm_hotel_booking_normalize_reviews_url()`).
- `urlmybooking` must be `http://` or `https://` (normalized via `itm_hotel_booking_normalize_reviews_url()`).
- `public_portal_enabled` gates which company `hb_public_company_id()` uses for anonymous welcome/chrome **and** hard-blocks browse/book via `hb_require_company_public_portal()` (rooms, calendar, select-rate, customize, room-single); home hotel grid skips disabled tenants.
- `free_cancellation_days_before_check_in` is company default; portal rate plans may override per plan.
- `calendar_month_advance_days_left` is company-scoped (0–31); portal JS reads it from `HB_HOTELS` / calendar JSON / `HB_SETTINGS`.
- `show_discount_strikethrough` is company-scoped; portal reads via `itm_hotel_booking_portal_show_discount_strikethrough_from_settings()` and `HB_SELECT_ROOM.showDiscountStrikethrough` (Step 1 JS).
- **Complimentary rooms:** when `portal_complimentary_min_rooms_paid` > 0 and booked room count exceeds it, credit the cheapest `portal_complimentary_rooms_free` room stay totals (`itm_hotel_booking_portal_complimentary_room_credit()`).
- **Step 4 confirmation emails:** `portal_confirmation_email_guest` and `portal_confirmation_email_reservations` gate `itm_hotel_booking_portal_send_booking_confirmation_emails()` (also used after Stripe webhook). Uncheck both to suppress all confirmation mail for that company.
- **Portal display:** `portal_show_room_number_on_confirmation` (default off) prefixes assigned room numbers on guest summaries/confirmation; `portal_hide_upgrade_upsell_when_multi_room` (default on) hides Step 3 upgrade card when `occupancy.rooms > 1`; `portal_money_symbol` (`EUR`/`GBP`/`USD`) with `portal_money_symbol_suffix` (default on) or `portal_money_symbol_prefix` for portal price formatting (`itm_hotel_booking_portal_money_format_options_from_settings()`).
- **Internal rates (portal):** `portal_show_internal_rates` (default off) exposes **HOUSE USE (USE)** and **COMPLIMENTARY (COMP)** in Step 1 Special rates modal; guest POST is ignored when off. Admin reservations always set `hotel_bookings.internal_rate_code` (`use` / `comp` / empty).
- **Portal date/time formats:** `portal_date_format` (`european_ddmmyyyy` default, `us_mmddyyyy`, `iso_yyyymmdd`); `portal_time_format` (`h24` default, `h12`); datetime display toggles `portal_datetime_european1_enabled`, `portal_datetime_european2_enabled` (default on), `portal_datetime_iso_enabled`, `portal_datetime_readable_enabled` with `portal_datetime_format_default` (default `european2`). Save requires at least one datetime format enabled and default must be enabled. Helpers: `itm_hotel_booking_portal_format_date_display()`, `itm_hotel_booking_portal_format_datetime_display()`, `itm_hotel_booking_portal_public_settings_for_js()`.
- **Stripe Checkout:** when `stripe_enabled` and keys are configured (`itm_stripe_checkout_is_enabled()`), portal Step 4 offers **Pay now with card** → `booking/payment-stripe.php` → Stripe hosted Checkout; webhook `booking/stripe-webhook.php?company_id=` marks `hotel_bookings.payment_status` and sends deferred confirmation emails. Admin saves encrypted secret + webhook signing secret (blank password keeps existing).
