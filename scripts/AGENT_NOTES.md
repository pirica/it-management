# AGENT_NOTES.md - Scripts

## 1. Module Purpose
Contains utility scripts, database maintenance tools, security audits, and testing runners.

## 2. Key Tables
- Interacts with almost all tables for maintenance, auditing, and seeding.

## 3. Required Relationships
- Depends on the entire database schema as defined in `database.sql`.

## 4. Business Rules (Critical for Agents)
- **CLI Mode**: Scripts intended for CLI use must define `ITM_CLI_SCRIPT` to bypass session redirects.
- **DANGER**: Some scripts are destructive (e.g., `reset_git_history.php`, `repair_table_from_schema.php`). Use with extreme caution.
- **Production Safety**: `debug.php` and other diagnostic tools should be removed or blocked in production.

## 5. UI Behavior Requirements
- **Browser vs CLI**: Many scripts provide both a plain-text/HTML browser view and a CLI output mode.

## 6. API Actions (If Applicable)
- **api.php** — provides centralized documentation for the system's JSON and import APIs.

## 7. File Structure
- **smoke_test.sh** — main shell script for linting and security coverage.
- **run_tests.php** — central test runner; browser menu (standard vs HTML coverage); detects Xdebug/PCOV; post-run link to `phpunit/coverage/html/coverage.html`. Browser coverage URL: `run_tests.php?run=1&mode=coverage`. Full docs: **`scripts/SCRIPTS.md` → PHPUnit test runner**.
- **check_csrf_coverage.php** / **check_sql_injection_coverage.php** — security audit tools.
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
