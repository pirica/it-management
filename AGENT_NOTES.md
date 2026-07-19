# AGENT_NOTES.md - Root Project

## 1. Module Purpose
The IT Management System is a multi-tenant legacy PHP application (PHP 7.4) designed to manage IT infrastructure, employees, budgets, and helpdesk operations with zero external dependencies.

## 4. Business Rules (Critical for Agents)
- **Security**: Mandatory CSRF protection on all POST requests. Prepared statements for all SQL queries using `mysqli`.
- **Multi-Tenancy**: All data (except Companies and certain maintenance logs) must be scoped by `company_id` from the session.
- **Architecture**: No Composer, No NPM. Use `config/config.php` for environment setup.
- **API rate limits**: **Free** tier — unlimited, **no API key**, **session required** (`company_id` + `employee_id` in `PHPSESSID`). **Paid** tiers — hourly caps, API key required. See `AGENTS.md` → **API keys and rate limits (mandatory)** and `includes/itm_api_rate_limit.php`.
- **`database.sql` hygiene**: No executable `ALTER TABLE` — define indexes/FKs on `CREATE TABLE`. Multi-company seed admins use tenant-correct role/access/status lookups; see `AGENTS.md` → **Database & Schema Rules**.
- **Login session rotation:** `login.php` calls `session_regenerate_id(true)` after successful password verification and before writing auth fields into `$_SESSION` (mitigates session fixation).
- **Entry pages / errors:** root `index.php` must not force `display_errors`; error visibility comes from `config/config.php` via `enable_all_error_reporting`.

## 10. Common Pitfalls
- Bypassing the session-based company isolation. [Cursor-Valid]
- Introducing external libraries. [Cursor-Valid]
- Forgetting to update `database.sql` when changing the schema. [Cursor-Valid]
- Allowing arbitrary line-wrapping in administrative or diagnostic reporting tables (always prevent line wrapping on columns using CSS `white-space: nowrap` and an auto-scrolling wrapper). [Cursor-Fixed]
- Session fixation: reusing the pre-login session id after authentication without regeneration. [Cursor-Fixed]
- Session cookie missing HttpOnly / SameSite / Secure (when HTTPS). [Cursor-Fixed]
- Hardcoding `display_errors` on `index.php` instead of Settings-driven config. [Cursor-Fixed]
- `user-config.php` System Access SELECT must not `array_merge` hardcoded meta names absent from `employee_system_access` (e.g. inventing `changed_at` → prepare failure). [Cursor-Fixed]
- `user-config.php` profile form must save and re-display `birthday` / `hide_year` (not only email/phone/theme/emergency); blank birthday inputs and unchecked hide_year must clear / persist correctly. [Cursor-Fixed]
- `user-config.php` profile UPDATE must use the employee home `company_id`, not the tenant-switcher session company — otherwise Admin users see “Profile updated successfully!” with 0 rows changed. Theme must set `<html data-theme>` + CSS variables (hardcoded `#fff` cards hide dark mode). [Cursor-Fixed]
- `user-config.php` profile photo: “Photo updated!” with broken image (alt text “Profile”) means `emp_profile_photo_url()` used module-relative `../../modules/explorer/file.php` from the app root — must be app-absolute under `BASE_URL`. [Cursor-Fixed]
- `user-config.php` dashboard stats: consolidated COUNTs live in `includes/itm_user_config_stats.php`; `floor_plans` counts `created_by` (schema column), not `created_by_employee_id`. [Cursor-Fixed]
- `database.sql` tenant replicas with per-company lookup rows must resolve child FK seeds by `company_id` + business key (for example cost center code, GL account code, approval stage/status name, access level name) instead of assuming raw auto-increment ids line up across companies. [Cursor-Fixed]

## 7. File Structure (high level)
- **config/**, **includes/**, **modules/**, **scripts/** — application code.
- **login.php** — authentication; regenerates the session id on success.
- **index.php** — company selection after login (no forced error display).
- **dashboard.php** — landing stats: row 1 module totals (Equipment, Tickets, Employees) exclude soft-deleted rows; row 2 **Active** / **On Leave** via `itm_employee_count_by_employment_status_name()` (`company_id` + resolved `employment_status_id` + `deleted_at IS NULL`); **Online now** via session presence; welcome uses `work_email` / `personal_email`; company switch uses `itm_is_admin()`. Distinct from Roles & Permissions sidebar **N active** counts per role. Regression: `php scripts/verify_dashboard_active_employees.php`.
- **css/styles.css** — global stylesheet with responsive breakpoints and shared layout utilities (see **`css/AGENT_NOTES.md`**).
- **phpunit/** — PHPUnit PHAR, `phpunit.xml`, and `tests/` tree. Runner: **`scripts/run_tests.php`**; coverage report: **`phpunit/coverage/html/coverage.html`**. See **`phpunit/AGENT_NOTES.md`** and **`scripts/SCRIPTS.md` → PHPUnit test runner**.

## 12. Module Owner Notes (Optional)
This is the entry point for the entire system. Refer to `AGENTS.md` for the authoritative process and technical standards.


## 13. Employee Dashboard & Profile
The `user-config.php` has been upgraded to a full Employee Dashboard & Profile system.
- **Scoping**: All dashboard data is scoped to the logged-in employee via `employee_id` or relevant created/assigned fields.
- **Stat Cards**: Displays stats from all modules using employee-related ID fields (30+ combinations tracked).
- **Profile Management**: Integrated photo upload (circular drag-and-drop), theme selection, and emergency contact details.
- **Profile save (`action=update_profile`):** persists `work_email`, `mobile_phone`, `theme` (light/dark), emergency contact fields, `birthday` (via `itm_parse_date_input`), and `hide_year` (checkbox). Full Name is **readonly** (managed in Employees). Birthday uses `<input type="date">` with `Y-m-d` value; Hide Year follows the double-label checkbox pattern. Profile UPDATEs use the employee **home** `company_id` (not the tenant switcher session company) so multi-company admins still save. Theme applies via `<html data-theme>`, early `localStorage` + `window.ITM_PREFERRED_THEME`, `$_SESSION['ui_theme']`, and CSS variables (no hardcoded light `#fff` cards).
- **Profile photo:** upload stores under `files/{home_company_id}/Private/{username}_{employee_id}/profile/`; display uses `emp_profile_photo_url()` → app-absolute `modules/explorer/file.php?path=…` (root `user-config.php` must not use `../../modules/…`). Regression: `php scripts/verify_user_config_profile.php`.
- **Layout**: `.layout-2col` uses a 280px left column on desktop and stacks to one column at `max-width: 768px`.
- **Personalized Sidebar:** every catalog label (except `dashboard_link`) is a link to `modules/{slug}/` (or the item `href`) with `target="_blank"`, `rel="noopener noreferrer"`, and class `itm-user-config-sidebar-link` (`color: inherit; text-decoration: none` in `css/styles.css`) so all modules open in a new tab without blue/underline chrome.
- **Recent Activity:** audit rows render as `{action} in {table_name}` where `table_name` is the same undecorated new-tab link (`modules/{table}/` or catalog `href` when the slug matches), e.g. `UPDATE in employee_sidebar_preferences`.
- **Security**: Atomicity in Vault Master Key changes with automatic re-encryption of existing entries.
- **Security flash messages:** password and vault form feedback (`change_password`, `vault_key_change`) renders at the page top and again above each section Save button so users do not scroll up after submit.
- **Audit**: All profile and security changes are logged to `audit_logs`.
- **System Access Overview:** loads `employee_system_access` via `DESCRIBE` then `SELECT` of **existing** columns only (never invents `changed_at`). Meta/audit fields (`id`, `company_id`, `employee_id`, `active`, `created_*`, `updated_*`, `deleted_*`, legacy `changed_at` name in skip lists) are excluded from ✅/❌ flag counts and the overview UI.
