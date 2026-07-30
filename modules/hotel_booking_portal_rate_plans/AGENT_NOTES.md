# AGENT_NOTES.md - Portal Rate Plans

## 1. Module Purpose

Admin UI for **Step 2** portal rate plan cancellation policy URLs (`hotel_booking_portal_rate_plans`). Guests open the policy matching their booked rate on Manage my booking and payment confirmation.

## 2. Key Tables

- **hotel_booking_portal_rate_plans** — four slots per hotel (`plan_slot` 1–4), `rate_plan_slug` (`room_only`, `breakfast`, `flexible`, `non_refundable`), `cancellation_policy_url`, `active`.

## 3. Business Rules

- Canonical slots: `itm_hotel_booking_portal_rate_plan_definitions()`; `itm_hotel_booking_ensure_portal_rate_plans_for_hotel()` seeds missing rows with default paths under `booking/cancellation_policy/`.
- Guest lookup: `itm_hotel_booking_portal_parse_rate_plan_from_notes()` reads `Rate plan:` / `Rate:` lines on `hotel_bookings.notes`; `itm_hotel_booking_portal_resolve_cancellation_policy_url()` returns the hotel row URL or default path.
- Step 2 currently posts `rate_plan` `room_only` or `breakfast` (slots 1–2). Slots 3–4 are reserved for future rates.

## 4. UI

- Bespoke `index.php`: hotel selector + URL/active table (not flattened CRUD).
- **create.php** — pick hotel + plan slot, ensures row, redirects to **edit.php**.
- **edit.php** — Plan name, Step 2 slug, cancellation policy URL, Active checkbox, Quill WYSIWYG for policy HTML (saved to DB + local HTML file when URL is relative).
- **view.php** — read-only summary + policy preview.
- List **index.php** includes hidden `hotel_id` on edit forms; hotel name shown as label when filtered.

## 5. Pitfalls

- Relative URLs resolve against the public booking portal base (`APPURL`); prefer `cancellation_policy/N_cancellation_policy.html` or full https links.
- Changing seeds does not update live DB rows; edit via this module.
