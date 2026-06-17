# AGENT_NOTES.md - Scripts

## 1. Module Purpose
Contains utility scripts, database maintenance tools, security audits, and testing runners.

## 2. Key Tables
- Interacts with almost all tables for maintenance, auditing, and seeding.

## 3. Required Relationships
- Depends on the entire database schema as defined in `database.sql`.

## 4. Business Rules (Critical for Agents)
- **Pre-implementation discovery:** before adding or changing scripts, produce the architectural map, module summary, and dependency analysis required by **`scripts/SCRIPTS.md` → Pre-implementation discovery (scripts)** and **`AGENTS.md` step 4**.
- **CLI Mode**: Scripts intended for CLI use must define `ITM_CLI_SCRIPT` to bypass session redirects.
- **DANGER**: Some scripts are destructive (e.g., `reset_git_history.php`, `repair_table_from_schema.php`). Use with extreme caution.
- **Production Safety**: `debug.php` and other diagnostic tools should be removed or blocked in production.

## 5. UI Behavior Requirements
- **Browser vs CLI**: Many scripts provide both a plain-text/HTML browser view and a CLI output mode.

## 6. API Actions (If Applicable)
- **api.php** — browser HTML catalogue of JSON/AJAX endpoints (session + CSRF). Documents Explorer file actions, IDF `api/*`, module imports, passwords vault, notes/todo AJAX, API key rate limits (**Free** = no key, session required; paid = key required), and tier regression runners (`apitest_tier_free.php`, `apitest_tier_basic.php`). Collector helpers: `phpunit/tests/Unit/Scripts/ApiFunctionsTest.php`. Maintenance rules: **`scripts/SCRIPTS.md` → API documentation (`scripts/api.php`)**.

## 7. File Structure
- **smoke_test.sh** — main shell script for linting and security coverage.
- **run_tests.php** — central test runner; browser menu (standard vs HTML coverage); detects Xdebug/PCOV; post-run link to `phpunit/coverage/html/coverage.html`. Browser coverage URL: `run_tests.php?run=1&mode=coverage`. Full docs: **`scripts/SCRIPTS.md` → PHPUnit test runner**.
- **check_csrf_coverage.php** / **check_sql_injection_coverage.php** — security audit tools.
- **verify_select_options_escalation.php** — regression for Select Options API table whitelist (`includes/itm_select_options_policy.php`); see **`scripts/SCRIPTS.md` → Select Options API verification**.
- **apitest_tier_free.php** / **apitest_tier_basic.php** — disposable `ui_configuration` tier rate-limit regressions; Free HTTP probe publishes CLI `PHPSESSID` via **`scripts/lib/itm_api_tier_test_helpers.php`** (`itm_apitest_publish_http_session()`).
- **verify_company_module_access.php** — registry/CMA regression plus sidebar discovery probes (registry-only, new MySQL table, folder-only, both, neither); PHPUnit wrapper: `phpunit/tests/Unit/Scripts/CompanyModuleAccessVerifyTest.php`.
- **verify_ops_report.php** — D-2 edit lock, `ops_report` CRUD, cascade delete, registry row; browser or CLI via `lib/script_cli_output.php`; PHPUnit: `OpsReportTest`, `OpsReportPermissionsTest`.
- **verify_employee_type_resignations.php** — `employee_type` seed, `employees.start_date` / `employee_type_id`, registry slugs, weekly resignations SQL filter (`itm_iso_week_bounds()`, `MONTH(termination_date)`, `itm_sql_valid_date_predicate()`); browser or CLI via `lib/script_cli_output.php` (do not use `fwrite(STDERR)` on web SAPI).
- **debug_resignations_termination_date.php** — read-only diagnostic for `modules/resignations/index.php` weekly filter. Default probe date `18/06/2026` (ISO week 25). Params: `date`, `company_id`, `employee_id`, `week`, `month`, `year`. Prints `[PASS]` / `[FAIL]` / `[WARN]` for week metadata, ISO bounds, legacy predicates, module SQL simulation, employee row, and today's verify-probe bounds. Catalog: `scripts/scripts.php`. Confirmed fix for empty report when MySQL 8 rejected `<> '0000-00-00'` in prepared statements.
- **employee_fields_missing.php** — compares `employees` columns in `database.sql` and live MySQL with create/edit/view/index coverage in `modules/employees/`; fails on schema or critical UI gaps (including `termination_date`). View checks map FK columns to human labels in `view.php` (e.g. `department_id` → `Department` / `department_name`).
- **count_db_tables.php** — counts live `information_schema` tables for `itmanagement`, echoes the total, and overwrites `scripts/number_db_tables.txt`. Browser and CLI; no login (`ITM_SCRIPT_NO_AUTH` allowlist in `config/config.php`).
- **floor_plans_folder_move_test.php** — regression for floor-plan folder create/move and company upload hardening (`.htaccess` + `index.html` via `fp_company_upload_dir()`).
- **data/** — contains excluded modules and prefixes for audits.
- **bypass_login.php** — CLI utility to authenticate as Admin without the UI.
- **take_screenshots.py** — Python script using Playwright to automate screenshot capture.

## 8. Multi-Tenant Rules
- Maintenance scripts usually operate across all tenants or allow specifying a `company_id` via CLI arguments.

## 9. Audit Logging Requirements
- `check_audit_logs_coverage.php` is used to verify that mutations in other modules are correctly logged.

## 10. Common Pitfalls
- Running destructive scripts on the wrong environment.
- Forgetting to define `ITM_CLI_SCRIPT` when running PHP scripts from the command line.
- **Resignations debug:** `debug_resignations_termination_date.php` defaults to `company_id=4` and `employee_id=432` — change params when debugging another tenant. Cross-month ISO weeks require the selected `month` to match `MONTH(termination_date)` or the row is excluded. Calendar year vs ISO year (`date('o')`) diverges at year boundaries; the script warns when bounds differ.
- **MySQL 8 `NO_ZERO_DATE`:** do not use `<> '0000-00-00'` in resignations or verify SQL — use `itm_sql_valid_date_predicate()` from `includes/itm_date_format.php`. Symptom: `Incorrect DATE value: '0000-00-00'` on `mysqli_prepare` and an empty weekly report despite valid `termination_date` rows.

## 11. Examples of Safe Code Patterns

### Running the Smoke Test
```bash
bash scripts/smoke_test.sh
```

### Running Unit Tests
```bash
php scripts/run_tests.php
php scripts/run_tests.php --coverage
```
Browser menu: `scripts/run_tests.php` — **Standard** or **HTML coverage** (`?run=1&mode=coverage`). Report: `phpunit/coverage/html/coverage.html`. See **`scripts/SCRIPTS.md` → PHPUnit test runner**.

### Bypassing Login for Debugging or Screenshots
This script is essential for rapid development, debugging errors as an Admin, or automating UI tasks like taking screenshots.
```bash
# Get a valid session ID for the Admin user
php scripts/bypass_login.php

# Get session for a specific user or company
php scripts/bypass_login.php --user=johndoe --company=2
```

### Resignations termination date debug
```bash
php scripts/debug_resignations_termination_date.php --date=18/06/2026 --company_id=4 --employee_id=432 --week=25 --month=6 --year=2026
```
Browser (login required): `scripts/debug_resignations_termination_date.php?date=18/06/2026&company_id=4&employee_id=432&week=25&month=6&year=2026`. Listed in **`scripts/scripts.php`**.

## 12. Bypass Login (CLI Information)
The `scripts/bypass_login.php` script allows you to:
- **Faster Screenshots**: Quickly authenticate an automated browser (like Playwright) by setting the `PHPSESSID` cookie.
- **Debug as Admin**: Directly establish an authenticated state to test admin-only logic or view protected modules without manual login.
- **Unlock Vault**: Automatically sets the `vault_key` session variable required for the Passwords module.
- **CLI Permissions**: The script automatically adjusts session file permissions (`0644`) so the web server (Apache) can read the session created in the CLI context.

### Usage with curl
```bash
# 1. Generate session
SESSION_ID=$(php scripts/bypass_login.php | grep "Session ID:" | awk '{print $3}')

# 2. Access protected page
curl -b "PHPSESSID=$SESSION_ID" http://localhost/dashboard.php
```

## 13. Module Owner Notes (Optional)
This directory is the toolbox for system administrators and developers.
