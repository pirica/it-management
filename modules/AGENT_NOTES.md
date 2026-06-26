# AGENT_NOTES.md - Modules

## 1. Module Purpose
Core functional units of the application. Each subdirectory is a module (CRUD, bespoke UI, or equipment-type façade).

## 2. Key Tables
- One primary table per module in most cases (see each `modules/<slug>/AGENT_NOTES.md`).
- **Ops Report child tables** (`ops_report_fb_outlet`, `ops_report_walk_round`, `ops_report_courtesy_call`, `ops_report_guest_experience`, `ops_report_butler`, `ops_report_night_shift`, `ops_report_hotel_figure`) have their own `modules/ops_report_*/AGENT_NOTES.md` plus the parent **modules/ops_report/AGENT_NOTES.md** for the daily report UI.

## 4. Business Rules (Critical for Agents)
- Every module folder **must** have its own `AGENT_NOTES.md` (template: `templates/AGENT_NOTES.md`).
- **Protection Zone** modules (`equipment`, `employees`, `contacts`, `idfs*`, `audit_logs`, `settings`, `employee_companies`, `employee_system_access`, `cable_colors`, `ui_configuration`) — no logic changes unless explicitly requested.
- Standard flattened CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.
- **UI action labels (NO MIXED):** View `🔎`, Edit `✏️`, Delete `🗑️`, Back/Cancel (forms/modals) `🔙`, Create/New/Add `➕`, Save `💾` — emoji-only visible text on `<a>`, `<button>`, `<input>`, and `<h1>`–`<h3>`; full phrases in `title`/`aria-label` only. Reference: `modules/manufacturers/`. Audit: `php scripts/check_ui_action_emoji.php`. See **`AGENTS.md` → UI action labels (NO MIXED)**.
- **`is_*` façades** delegate to `modules/equipment/` — do not delete canonical wrappers.

## 7. File Structure
- `modules/<slug>/` — module entry files + `AGENT_NOTES.md`.
- Subfolders such as `api/`, `includes/` — own `AGENT_NOTES.md` when they hold code.

## 8. Multi-Tenant Rules
- Almost all modules scope data by `company_id` from session.

## 12. Module Owner Notes (Optional)
Before editing any module, read its `AGENT_NOTES.md` and `AGENTS.md` Protection Zone / bespoke sections.

### `select_options_api.php` (module root)
- Shared JSON endpoint for dropdown quick-add (`js/select-add-option.js`).
- **Security:**
  - Uses **MySQLi prepared statements** for all database queries and insertions to prevent SQL injection.
  - Strictly enforces **POST only** method and **CSRF token validation**.
  - **Table policy:** inserts are allowed only for whitelisted tables in `includes/itm_select_options_policy.php`; identity/RBAC tables (`employees`, `companies`, etc.) are blocked.
  - **Company Scoping:** automatically scopes insertions and lookups by `company_id`.
  - **Module Access:** checks `has_module_access()` to ensure the owning module is enabled for the active company.
- **Business Logic:**
  - Supports **extra fields** (e.g. status_id, location_id for racks; hex_color for cable colors).
  - Automatically translates HEX colors to approximate color names (e.g. `#FF0000` -> `Red`).
  - Ensures required fields (NOT NULL without default) are provided before attempting an insert.
- **Company module access:** `config/config.php` enforces `has_module_access()` for all `modules/*` requests; sidebar/dashboard/calendar honour the same helper. Admin matrix: `modules/company_module_access/`.
- Regression: `php scripts/verify_select_options_escalation.php` (expects PASS — admin user creation via `employees` table rejected).
