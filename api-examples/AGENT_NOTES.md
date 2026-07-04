# AGENT_NOTES.md - API Examples

## 1. Module Purpose
Standalone reference scripts demonstrating how to interact with the system's JSON and Import APIs.

## 4. Business Rules (Critical for Agents)
- **Reference Only**: These scripts are examples and should not be used as part of the core application logic.
- **Authentication**: Demonstrates the session + CSRF flow required for all protected endpoints.

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
- **index.html** — directory listing placeholder (not an executable example).

All `api-examples/*.php` scripts are auto-listed in **`scripts/api.php`** via `itmDocCollectApiExamples()`.

## 12. Module Owner Notes (Optional)
Ideal starting point for developing external integrations.
