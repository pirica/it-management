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

## 7. File Structure (high level)
- **config/**, **includes/**, **modules/**, **scripts/** — application code.
- **dashboard.php** — landing stats: row 1 module counts (Equipment, Tickets, Employees); row 2 **Active** (HR employment status Active), **Online now** (session presence via `includes/itm_active_sessions.php`), **On Leave** (HR employment status On Leave) for the active `company_id`. Distinct from Roles & Permissions sidebar **N active** counts per role (`employees.role_id` + HR Active).
- **css/styles.css** — global stylesheet with responsive breakpoints and shared layout utilities (see **`css/AGENT_NOTES.md`**).
- **phpunit/** — PHPUnit PHAR, `phpunit.xml`, and `tests/` tree. Runner: **`scripts/run_tests.php`**; coverage report: **`phpunit/coverage/html/coverage.html`**. See **`phpunit/AGENT_NOTES.md`** and **`scripts/SCRIPTS.md` → PHPUnit test runner**.

## 12. Module Owner Notes (Optional)
This is the entry point for the entire system. Refer to `AGENTS.md` for the authoritative process and technical standards.
