# AGENT_NOTES.md - Portal Rate Plans

## 1. Module Purpose

Admin UI for **Step 2** portal rate plan cancellation policy URLs (`hotel_booking_portal_rate_plans`). Guests open the policy matching their booked rate on Manage my booking and payment confirmation.

## 2. Key Tables

- **hotel_booking_portal_rate_plans** — per-hotel `plan_slot` (tinyint 1–127, unique with `company_id` + `hotel_id`), `rate_plan_slug`, `name`, `cancellation_policy_url`, `cancellation_policy_html`, `active`.

## 3. Business Rules

- Built-in templates (slots 1–4): `itm_hotel_booking_portal_rate_plan_definitions()` (`room_only`, `breakfast`, `flexible`, `non_refundable`).
- `itm_hotel_booking_ensure_portal_rate_plans_for_hotel()` seeds missing built-in rows with default paths under `booking/cancellation_policy/` — called from **index.php only** (not create POST).
- **Custom slots (5+):** **create.php** INSERTs via `itm_hotel_booking_portal_rate_plan_create()`; optional template dropdown prefills slot/name/slug but does not block custom values. Default `plan_slot` = `itm_hotel_booking_portal_rate_plan_next_free_slot()`.
- Uniqueness: `itm_hotel_booking_portal_rate_plan_slot_in_use()` on `(company_id, hotel_id, plan_slot)` for live rows.
- List: `itm_hotel_booking_portal_rate_plans_admin_rows()` returns **all** DB rows for the hotel (ordered by `plan_slot`), not only the four definitions.
- Guest lookup: `itm_hotel_booking_portal_parse_rate_plan_from_notes()` reads `Rate plan:` / `Rate:` on `hotel_bookings.notes`; `itm_hotel_booking_portal_resolve_cancellation_policy_url()` returns the hotel row URL or default path.
- Public Step 2 UI still posts `rate_plan` `room_only` or `breakfast` today; custom slugs remain usable for cancellation policies and `Rate plan:` notes parsing.

## 4. UI

- Bespoke `index.php`: hotel selector + URL/active table (not flattened CRUD).
- **create.php** — hotel, plan slot (number), plan name, Step 2 slug, optional policy URL, active; optional template quick-fill; **💾** Save INSERT → **edit.php** for Quill policy HTML.
- **edit.php** — Plan name, Step 2 slug, cancellation policy URL, Active checkbox, Quill WYSIWYG for policy HTML (saved to DB + local HTML file when URL is relative).
- **view.php** — read-only summary + policy preview.
- **delete.php** — hard `DELETE` (not soft-delete); `itm_hotel_booking_ensure_portal_rate_plans_for_hotel()` recreates default slot rows on next index load.
- List **index.php** toolbar: vertical stack **➕** then **🏨** (`itm_hospitality_render_list_create_and_hub()`). **create.php** shows **🏨** hub link (stacked under title). **🔙** returns to index with `hotel_id`.

## 5. Pitfalls

- Relative URLs resolve against the public booking portal base (`APPURL`); prefer `cancellation_policy/N_cancellation_policy.html` or full https links.
- Changing seeds does not update live DB rows; edit via this module.
- Do not call `ensure_portal_rate_plans_for_hotel()` on create POST — it can recreate deleted built-in slots before a custom INSERT.
