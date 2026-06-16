# Testing Preferences and Results

## Framework & Scope
- **Framework:** PHPUnit
- **Priority Modules:** All modules (critical CRUD paths, security, multi-tenancy)
- **Integration:** MySQL database integration tests included

## Test Data & Isolation
- **Fixtures:** Initial data from `database.sql`
- **Multi-tenancy:** Tests cover 5 seeded companies (TechCorp Global ... Enterprise IT)
- **Cleanup:** Tests use transaction rollback or temporary table approaches to ensure environment remains clean

## Coverage Goals
- **Minimum Coverage:** 80%
- **FK Regression:** Includes foreign key and delete cascade regression tests (including Protection Zone modules)
- **Security Focus:** SQLi payloads, CSRF token validation, and XSS sanitization

## CI Integration
- **Platform:** CLI-only (No GitHub Actions integration)
- **Behavior:** Failing tests report warnings only
- **MySQL Independence:** Supports `ITM_SKIP_DB_TESTS=1` for running without MySQL

## Deliverable Format
- **Directory Structure:** Organized subdirectories in `phpunit/tests/Unit/` (Config, Security, CRUD, Database, MultiTenancy)
- **Naming Convention:** `*unittest.php`
- **Bootstrap:** `phpunit/tests/bootstrap.php` provided for environment setup
- **Browser runner:** `scripts/run_tests.php` menu — **Standard** (verbose) or **HTML coverage**
- **Verbose output:** `verbose="true"` in `phpunit/phpunit.xml` plus `--verbose` from `scripts/run_tests.php`
- **HTML coverage:** `<coverage>` in `phpunit/phpunit.xml`; browser menu, `php scripts/run_tests.php --coverage`, or `ITM_COVERAGE=1` → `phpunit/coverage/html/coverage.html` (Xdebug or PCOV required)

---

## Results
*Results will be updated in `phpunit/tests/RESULTS.md` after the test suite execution.*
