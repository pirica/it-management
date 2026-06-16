# AGENT_NOTES.md - PHPUnit

## 1. Module Purpose
Houses the PHPUnit runner, configuration, and the full unit/integration test tree for the IT Management System.

## 7. File Structure
- **phpunit.phar** — PHPUnit 9.6 PHAR executable (no Composer).
- **phpunit.xml** — PHPUnit configuration (bootstrap, test suites, verbose output, HTML coverage report paths).
- **coverage/html/** — generated HTML coverage report (gitignored; created by `run_tests.php --coverage`).
- **tests/** — test suite root (`bootstrap.php`, `Unit/`, agent notes, preferences).

## 11. Examples of Safe Code Patterns

### Run the suite (from repository root)
```bash
php scripts/run_tests.php
```

### Verbose output
Configured in `phpunit.xml` (`verbose="true"`) and passed explicitly as `--verbose` from `scripts/run_tests.php`.

### HTML coverage (requires Xdebug or PCOV)
```bash
php scripts/run_tests.php --coverage
# or
ITM_COVERAGE=1 php scripts/run_tests.php
```
Open `phpunit/coverage/html/index.html` in a browser.

### Run PHPUnit directly
```bash
php phpunit/phpunit.phar -c phpunit/phpunit.xml --verbose
php phpunit/phpunit.phar -c phpunit/phpunit.xml --verbose --coverage-html phpunit/coverage/html
```

## 12. Module Owner Notes (Optional)
Entry point for agents is `phpunit/tests/AGENT_NOTES.md`. Generated module tests are written under `phpunit/tests/Unit/Modules/` by `scripts/generate_tests.php`.
