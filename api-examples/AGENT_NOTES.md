# AGENT_NOTES.md - API Examples

## 1. Module Purpose
Standalone reference scripts demonstrating how to interact with the system's JSON and Import APIs.

## 4. Business Rules (Critical for Agents)
- **Reference Only**: These scripts are examples and should not be used as part of the core application logic.
- **Authentication**: Session + CSRF for legacy module JSON/import paths; API v2 examples use paid-tier `X-API-Key` only (no session).
- **Environment variables (example-only — not in `.env.example`):** set in your shell or a local `.env` when running samples; classified as tooling in `scripts/lib/itm_env_vars_audit.php`. API v2: `ITM_API_V2_KEY`, `ITM_API_V2_TICKET_ID`, `ITM_API_V2_EQUIPMENT_ID`, `ITM_API_V2_EQUIPMENT_STATUS_ID`, `ITM_API_V2_EQUIPMENT_TYPE_ID` — see `docs/API_V2.md`. Hotel distribution: `ITM_DIST_API_KEY`, `ITM_DIST_EXTERNAL_RESERVATION_ID` — see `docs/HOTEL_BOOKING_DISTRIBUTION.md`.

## 7. File Structure
- **authenticate.php** — full login, session cookie, and CSRF acquisition flow.
- **sessionCookie.php** — capture `PHPSESSID` from login response headers.
- **csrfToken.php** — extract `csrf_token` from HTML forms or JS variables.
- **equipment.php** — multi-row `import_excel_rows` equipment import.
- **employees.php** — employee directory import with auto-lookup resolution.
- **tickets.php** — bulk ticket creation via JSON import.
- **catalogs.php** — catalog product listing import.
- **events.php** — calendar event batch import.
- **ticket_archive.php** — archive/unarchive ticket via form POST (redirect response).
- **catalog_delete.php** — single catalog delete via `modules/catalogs/delete.php`.
- **equipment_edit.php** — update equipment via `modules/equipment/edit.php`.
- **employees_singleview.php** — fetch and parse single employee HTML view.
- **tickets_listall_open.php** — filter tickets with `search=Open` and parse HTML.
- **catalogs_listall_active.php** — list active catalog rows from index HTML.
- **hotel_distribution_probe.php** — distribution API key probe (`action=probe`, `X-API-Key`).
- **hotel_distribution_availability.php** — distribution API availability shop (`X-API-Key`).
- **hotel_distribution_ari_snapshot.php** — outbound ARI snapshot pull (`action=ari_snapshot`).
- **hotel_distribution_book.php** — direct `action=book` reservation create (`X-API-Key`).
- **hotel_distribution_notify_book.php** — distribution `notify` book payload (`X-API-Key`).
- **hotel_distribution_modify.php** — `action=modify` amend by `external_reservation_id`.
- **hotel_distribution_cancel.php** — `action=cancel` by `external_reservation_id`.
- **api_v2_probe.php** — API v2 `GET /probe` with `X-API-Key`.
- **api_v2_tickets_list.php** — API v2 `GET /tickets` list.
- **api_v2_ticket_get.php** — API v2 `GET /tickets/{id}`.
- **api_v2_ticket_create.php** — API v2 `POST /tickets` (needs `tickets.write`).
- **api_v2_ticket_update.php** — API v2 `PATCH /tickets/{id}` (needs `tickets.write`).
- **api_v2_equipment_list.php** — API v2 `GET /equipment` list.
- **api_v2_equipment_get.php** — API v2 `GET /equipment/{id}`.
- **api_v2_equipment_create.php** — API v2 `POST /equipment` (needs `equipment.write`).
- **api_v2_equipment_update.php** — API v2 `PATCH /equipment/{id}` (needs `equipment.write`).
- **index.html** — directory listing placeholder (not an executable example).

All `api-examples/*.php` scripts are auto-listed in **`scripts/api.php`** via `itmDocCollectApiExamples()`.

## 10. Common Pitfalls

- Reference-only samples — never wire `api-examples/` into application runtime includes. [Cursor-Valid]
- Examples need a signed-in session and CSRF; do not invent auth bypasses. [Cursor-Valid]
- When adding examples, keep them cataloged from `scripts/api.php`. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Ideal starting point for developing external integrations.
