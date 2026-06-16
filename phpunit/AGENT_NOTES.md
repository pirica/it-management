# AGENT_NOTES.md - PHPUnit

## 1. Module Purpose
Houses the PHPUnit runner, configuration, and the full unit/integration test tree for the IT Management System.

## 7. File Structure
- **phpunit.phar** — PHPUnit 9.6 PHAR executable (no Composer).
- **phpunit.xml** — PHPUnit configuration (bootstrap, test suites, verbose output, HTML coverage report paths).
- **coverage/html/** — generated HTML coverage report (gitignored; created when coverage mode is selected).
- **tests/** — test suite root (`bootstrap.php`, `Unit/`, agent notes, preferences).

## 11. Examples of Safe Code Patterns

### Run the suite (from repository root)
```bash
php scripts/run_tests.php
php scripts/run_tests.php --coverage
```

### Browser
Open `scripts/run_tests.php` — menu offers **Standard** (verbose) or **HTML coverage**, plus optional skip-DB checkbox.

### Run PHPUnit directly
```bash
php phpunit/phpunit.phar -c phpunit/phpunit.xml --verbose
php phpunit/phpunit.phar -c phpunit/phpunit.xml --verbose --coverage-html phpunit/coverage/html
```

## 12. Module Owner Notes (Optional)
Entry point for agents is `phpunit/tests/AGENT_NOTES.md`. Generated module tests are written under `phpunit/tests/Unit/Modules/` by `scripts/generate_tests.php`.
