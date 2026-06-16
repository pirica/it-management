# AGENT_NOTES.md - Tests

## 1. Module Purpose
Contains the PHPUnit test suite for validating system functionality and regression prevention.

## 4. Business Rules (Critical for Agents)
- **Naming:** Test classes must end in `Test.php` or `*.unittest.php` (see `phpunit/phpunit.xml` test suite directories).
- **No load-time side effects:** Files matching `*Test.php` must be proper `PHPUnit\Framework\TestCase` subclasses — **no top-level `echo`**, **`require` of production scripts with `exit`**, or procedural code at file scope. PHPUnit loads every matching file before the suite runs.
- **DB tests:** Use `$this->markTestSkipped('Database connection unavailable.')` when `$conn` is missing (see module `*Test.php` and `BypassLoginTest`).
- **Procedural legacy tests:** Avoid new procedural `*Test.php` files; convert to `TestCase` with assertions.

## 7. File Structure
- **Unit/** — test classes organised by concern (Config, CRUD, Database, **Includes**, Modules, MultiTenancy, Scripts, Security, Support).
- **Unit/Includes/** — DB-free unit tests for `includes/` visibility, MBQA, and coverage guard helpers (`docs/PHPUNIT_PLAN.md` Phase 1).
- **Unit/Support/** — shared test traits (e.g. `ItmScriptCliTestTrait` for CLI audit script subprocess tests).
- **bootstrap.php** — test bootstrap (`ROOT_PATH` = two levels up to repository root; `ITM_CLI_SCRIPT`).
- **PREFERENCES.md** — framework preferences, coverage URLs, naming conventions.
- **../phpunit.xml** — PHPUnit config (verbose, `<coverage processUncoveredFiles="false">`, HTML report under `coverage/html/`).
- **../phpunit.phar** — PHPUnit 9.6 PHAR (no Composer).

## 10. Common Pitfalls
- **`echo` in `*Test.php`** breaks HTML coverage report generation (headers already sent).
- **Including `scripts/*.php` with `exit()`** halts the runner — use subprocess or mock; skip when DB unavailable.
- **Expecting full suite without MySQL** — many module tests skip; use `ITM_SKIP_DB_TESTS=1` only when intentional.
- **Expecting high headline %** — with full tree in `phpunit.xml`, totals stay under ~1% until tests load module PHP or scope is narrowed. See **`scripts/SCRIPTS.md` → Interpreting HTML coverage percentages**.

## 11. Examples of Safe Code Patterns

### Run the suite (repository root)
```bash
php scripts/run_tests.php
php scripts/run_tests.php --coverage
```

### Browser
- Menu: `scripts/run_tests.php`
- Direct HTML coverage: `scripts/run_tests.php?run=1&mode=coverage`
- Standard verbose: `scripts/run_tests.php?run=1&mode=standard`

Report path after coverage: **`phpunit/coverage/html/coverage.html`** (requires Xdebug or PCOV).

### Authoritative runner docs
**`scripts/SCRIPTS.md` → PHPUnit test runner** — modes, Laragon commands, HTML coverage guardrails for `includes/` entry scripts and view partials.

## 12. Module Owner Notes (Optional)
Parent folder: `phpunit/AGENT_NOTES.md`. Per-module test notes: `phpunit/tests/Unit/Modules/<slug>/AGENT_NOTES.md`.
