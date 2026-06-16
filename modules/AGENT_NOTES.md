# AGENT_NOTES.md - Modules

## 1. Module Purpose
Core functional units of the application. Each subdirectory is a module (CRUD, bespoke UI, or equipment-type façade).

## 2. Key Tables
- One primary table per module in most cases (see each `modules/<slug>/AGENT_NOTES.md`).

## 4. Business Rules (Critical for Agents)
- Every module folder **must** have its own `AGENT_NOTES.md` (template: `templates/AGENT_NOTES.md`).
- **Protection Zone** modules (`equipment`, `employees`, `contacts`, `idfs*`, `audit_logs`, `settings`, `user_companies`, `employee_system_access`, `cable_colors`, `ui_configuration`) — no logic changes unless explicitly requested.
- Standard flattened CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.
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
- **Table policy:** inserts are allowed only for tables listed in `includes/itm_select_options_policy.php`; `users`, `user_roles`, `role_module_permissions`, and other identity/RBAC tables are blocked.
- Regression: `php scripts/verify_select_options_escalation.php` (expects PASS — admin user creation via `users` table rejected).
