# AGENT_NOTES.md - Root Project

## 1. Module Purpose
The IT Management System is a multi-tenant legacy PHP application (PHP 7.4) designed to manage IT infrastructure, employees, budgets, and helpdesk operations with zero external dependencies.

## 4. Business Rules (Critical for Agents)
- **Security**: Mandatory CSRF protection on all POST requests. Prepared statements for all SQL queries using `mysqli`.
- **Multi-Tenancy**: All data (except Companies and certain maintenance logs) must be scoped by `company_id` from the session.
- **Architecture**: No Composer, No NPM. Use `config/config.php` for environment setup.
- **API rate limits**: **Free** tier — unlimited, **no API key**, **session required** (`company_id` + `employee_id` in `PHPSESSID`). **Paid** tiers — hourly caps, API key required. See `AGENTS.md` → **API keys and rate limits (mandatory)** and `includes/itm_api_rate_limit.php`.

## 10. Common Pitfalls
- Bypassing the session-based company isolation.
- Introducing external libraries.
- Forgetting to update `database.sql` when changing the schema.
- Allowing arbitrary line-wrapping in administrative or diagnostic reporting tables (always prevent line wrapping on columns using CSS `white-space: nowrap` and an auto-scrolling wrapper).

## 7. File Structure (high level)
- **config/**, **includes/**, **modules/**, **scripts/** — application code.
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
- **Security**: Atomicity in Vault Master Key changes with automatic re-encryption of existing entries.
- **Audit**: All profile and security changes are logged to `audit_logs`.
