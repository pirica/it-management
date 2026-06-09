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
- **run_tests.php** — central test runner.
- **check_csrf_coverage.php** / **check_sql_injection_coverage.php** — security audit tools.
- **data/** — contains excluded modules and prefixes for audits.

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
```

## 12. Module Owner Notes (Optional)
This directory is the toolbox for system administrators and developers.
