# Testing Preferences and Results

## Framework & Scope
- **Framework:** PHPUnit 9.6 (`phpunit/phpunit.phar`, no Composer)
- **Priority Modules:** All modules (critical CRUD paths, security, multi-tenancy)
- **Integration:** MySQL database integration tests included

## Test Data & Isolation
- **Fixtures:** Initial data from `db/01_schema.sql`
- **Multi-tenancy:** Tests cover 5 seeded companies (TechCorp Global … Enterprise IT)
- **Cleanup:** Tests use transaction rollback or temporary row approaches where implemented

## Coverage Goals
- **Long-term target:** 80% line coverage (aspirational — not the current measured baseline).
- **Current baseline:** With full-suite HTML coverage (239 tests, MySQL, Xdebug/PCOV), expect **~0.4% total lines** and **&lt; 0.2% for `modules/`** because module tests exercise the database via MySQLi, not `modules/*/*.php` entry files. See **`scripts/SCRIPTS.md` → Interpreting HTML coverage percentages**.
- **Phase 1 (includes + scripts):** Add `phpunit/tests/Unit/Includes/` and upgrade audit `*.unittest.php` stubs to CLI subprocess tests — raises `includes/` and `scripts/` buckets without requiring module HTTP loads. Full plan: **`docs/PHPUNIT_PLAN.md`**.
- **Phase 2 (optional):** Module functional tests (`PasswordsFunctionalTest`, `SecurityFixesTest::runIsolated`) for pilot modules — moves `modules/` off 0% slowly.
- **FK Regression:** Includes foreign key and delete cascade regression tests (including bespoke modules where applicable)
- **Security Focus:** SQLi payloads, CSRF token validation, and XSS sanitization

## CI Integration
- **Smoke (GitHub Actions):** `bash scripts/smoke_test.sh` — lint, CSRF audit, SQLi audit (not the full PHPUnit suite)
- **PHPUnit:** Run manually or via `scripts/run_tests.php` (browser or CLI)
- **MySQL Independence:** `ITM_SKIP_DB_TESTS=1` or browser **Skip database tests** checkbox

## Deliverable Format
- **Directory Structure:** `phpunit/tests/Unit/` (Config, Security, CRUD, Database, Includes, MultiTenancy, Modules, Scripts, Support)
- **Naming Convention:** `*Test.php` and `*.unittest.php`
- **Bootstrap:** `phpunit/tests/bootstrap.php`

## Test runner (`scripts/run_tests.php`)

| Mode | Browser | CLI |
|------|---------|-----|
| Standard (verbose, no coverage) | `run_tests.php` → **Standard** | `php scripts/run_tests.php` |
| HTML coverage | `run_tests.php?run=1&mode=coverage` | `php scripts/run_tests.php --coverage` |
| Skip DB tests | Checkbox on menu | `ITM_SKIP_DB_TESTS=1` |

- **Verbose output:** `verbose="true"` in `phpunit/phpunit.xml` plus `--verbose` from the runner
- **Coverage driver:** Xdebug or PCOV required for HTML coverage; runner detects and warns if missing
- **HTML report:** `phpunit/coverage/html/coverage.html` (runner renames PHPUnit’s `index.html`)
- **PHPUnit config:** `processUncoveredFiles="false"` in `phpunit/phpunit.xml` — report generation does not bare-`require` every uncovered file under `modules/` and `scripts/`

### HTML coverage guardrails (for agents)
When adding HTTP entry scripts, view partials, or `*Test.php` files, follow **`scripts/SCRIPTS.md` → HTML coverage — report generation guardrails`**. Key helpers: `includes/itm_script_entry_guard.php`, `includes/switch_port_api_helpers.php`.

---

## Results
*Update `phpunit/tests/RESULTS.md` after major suite runs if publishing QA-style summaries.*
