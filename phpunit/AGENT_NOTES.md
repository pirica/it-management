# AGENT_NOTES.md - PHPUnit

## 1. Module Purpose
Houses the PHPUnit runner, configuration, and the full unit/integration test tree for the IT Management System.

## 7. File Structure
- **phpunit.phar** — PHPUnit 9.6 PHAR executable (no Composer).
- **phpunit.xml** — bootstrap `tests/bootstrap.php`, verbose output, `<coverage>` HTML paths. **`processUncoveredFiles="false"`** so HTML report generation does not bare-`require` every uncovered module/script entry file.
- **coverage/html/** — generated HTML coverage (gitignored). Entry file **`coverage.html`** after `run_tests.php` renames PHPUnit’s `index.html`.
- **tests/** — suite root (`bootstrap.php`, `Unit/`, `PREFERENCES.md`, `AGENT_NOTES.md`).

## 10. Common Pitfalls
- Running **`phpunit.phar` directly** without `--no-coverage` when no Xdebug/PCOV — prefer **`scripts/run_tests.php`** (driver check + menu).
- **Coverage paths in docs** must say `phpunit/coverage/html/coverage.html`, not `index.html`.

## 11. Examples of Safe Code Patterns

### Run via central runner (preferred)
```bash
php scripts/run_tests.php
php scripts/run_tests.php --coverage
```

### Browser
- Menu: `http://localhost/it-management/scripts/run_tests.php`
- HTML coverage: `scripts/run_tests.php?run=1&mode=coverage`

### Run PHPUnit PHAR directly
```bash
php phpunit/phpunit.phar -c phpunit/phpunit.xml --verbose
php phpunit/phpunit.phar -c phpunit/phpunit.xml --verbose --coverage-html phpunit/coverage/html
```

### Documentation map
| Topic | Location |
|--------|----------|
| Runner modes, Laragon commands, guardrails | **`scripts/SCRIPTS.md` → PHPUnit test runner** |
| Test authoring rules | **`phpunit/tests/AGENT_NOTES.md`** |
| Preferences / URLs | **`phpunit/tests/PREFERENCES.md`** |
| Entry guards for coverage-safe includes | **`includes/itm_script_entry_guard.php`**, **`includes/AGENT_NOTES.md`** |

## 12. Module Owner Notes (Optional)
Generated module tests: `scripts/generate_tests.php` → `phpunit/tests/Unit/Modules/`. Test tree entry: `phpunit/tests/AGENT_NOTES.md`.
