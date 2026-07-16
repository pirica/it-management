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

## 7. File Structure (high level)
- **config/**, **includes/**, **modules/**, **scripts/** — application code.
- **login.php** — authentication; regenerates the session id on success.
- **index.php** — company selection after login (no forced error display).
- **dashboard.php** — landing stats: row 1 module counts (Equipment, Tickets, Employees); row 2 **Active** and **On Leave** count `employees` by tenant-resolved `employment_status_id` (same semantics as `WHERE company_id = ? AND employment_status_id = ?`), **Online now** via session presence; distinct from Roles & Permissions sidebar **N active** counts per role.
- **css/styles.css** — global stylesheet with responsive breakpoints and shared layout utilities (see **`css/AGENT_NOTES.md`**).
- **phpunit/** — PHPUnit PHAR, `phpunit.xml`, and `tests/` tree. Runner: **`scripts/run_tests.php`**; coverage report: **`phpunit/coverage/html/coverage.html`**. See **`phpunit/AGENT_NOTES.md`** and **`scripts/SCRIPTS.md` → PHPUnit test runner**.

## 12. Module Owner Notes (Optional)
This is the entry point for the entire system. Refer to `AGENTS.md` for the authoritative process and technical standards.


## 13. Employee Dashboard & Profile
The `user-config.php` has been upgraded to a full Employee Dashboard & Profile system.
- **Scoping**: All dashboard data is scoped to the logged-in employee via `employee_id` or relevant created/assigned fields.
- **Stat Cards**: Displays stats from all modules using employee-related ID fields (30+ combinations tracked).
- **Profile Management**: Integrated photo upload (circular drag-and-drop), theme selection, and emergency contact details.
- **Layout**: `.layout-2col` uses a 280px left column on desktop and stacks to one column at `max-width: 768px`.
- **Security**: Atomicity in Vault Master Key changes with automatic re-encryption of existing entries.
- **Audit**: All profile and security changes are logged to `audit_logs`.
- **System Access Overview:** loads `employee_system_access` via `DESCRIBE` then `SELECT` of **existing** columns only (never invents `changed_at`). Meta/audit fields (`id`, `company_id`, `employee_id`, `active`, `created_*`, `updated_*`, `deleted_*`, legacy `changed_at` name in skip lists) are excluded from ✅/❌ flag counts and the overview UI.
