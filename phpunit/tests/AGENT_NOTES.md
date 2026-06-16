# AGENT_NOTES.md - Tests

## 1. Module Purpose
Contains the PHPUnit test suite for validating system functionality and regression prevention.

## 4. Business Rules (Critical for Agents)
- **Naming Convention**: Unit tests must end in `.unittest.php` or `Test.php`.
- **Procedural Tests**: Some functional tests are procedural scripts; they must avoid calling `exit()` to prevent halting the runner.

## 7. File Structure
- **Unit/** — contains the actual test files.
- **bootstrap.php** — initializes the test environment (`ROOT_PATH` resolves two levels up to the repository root).
- **../phpunit.xml** — PHPUnit configuration (sibling under `phpunit/`): verbose output, `<coverage>` HTML report target.
- **../phpunit.phar** — PHPUnit PHAR runner (sibling under `phpunit/`).

## 11. Examples of Safe Code Patterns

### Running the Suite
```bash
php scripts/run_tests.php
```

## 12. Module Owner Notes (Optional)
Every major feature or bug fix should be accompanied by a test in this directory. Parent folder notes: `phpunit/AGENT_NOTES.md`.
