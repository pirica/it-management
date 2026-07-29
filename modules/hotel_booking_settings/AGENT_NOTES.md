# AGENT_NOTES.md - Hotel Booking Settings

## 1. Module Purpose

Tenant configuration for the public `booking/` portal and shared copy (welcome text, footnotes, airport info, accessible features default).

## 2. Key Tables

- **hotel_booking_settings** — one row per `company_id`; includes `reviews_url` (external TripAdvisor/reviews page, opened `target="_blank"` from the portal).

## 3. Business Rules

- `reviews_url` must be `http://` or `https://` (normalized via `itm_hotel_booking_normalize_reviews_url()`).
- `public_portal_enabled` gates which company `hb_public_company_id()` uses for anonymous guests.
