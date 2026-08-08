# AGENT_NOTES.md - Hotel Booking Settings

## 1. Module Purpose

Tenant configuration for the public `booking/` portal and shared copy (welcome text, footnotes, airport info, accessible features default).

## 2. Key Tables

- **hotel_booking_settings** — one row per `company_id`; includes `reviews_url` (external TripAdvisor/reviews page, opened `target="_blank"` from the portal), `tourist_tax_per_person_per_night` (decimal EUR per guest per night for portal steps 3–4), `free_cancellation_days_before_check_in` (default **5** for Step 2 `{date}` cancel copy), and `urlmybooking` (external URL to manage booking, default 'https://localhost/it-management/booking/users/bookings.php').

## 3. Business Rules

- `reviews_url` must be `http://` or `https://` (normalized via `itm_hotel_booking_normalize_reviews_url()`).
- `urlmybooking` must be `http://` or `https://` (normalized via `itm_hotel_booking_normalize_reviews_url()`).
- `public_portal_enabled` gates which company `hb_public_company_id()` uses for anonymous guests.
- `free_cancellation_days_before_check_in` is company default; portal rate plans may override per plan.
