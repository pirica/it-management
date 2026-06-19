# AGENT_NOTES.md - PHPUnit Includes tests

## 1. Module Purpose
Unit tests for shared helpers under `includes/` that are safe to exercise without loading full module entry files. Improves HTML coverage for visibility SQL, MBQA markers, coverage guards, and switch-port AJAX helpers.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- **DB-free first:** visibility and MBQA tests must pass with `ITM_SKIP_DB_TESTS=1`.
- **No top-level side effects:** follow `phpunit/tests/AGENT_NOTES.md` (`TestCase` only, no `echo`).
- **Switch port helpers:** `SwitchPortApiHelpersTest` skips when `$conn` is unavailable; `find_lookup_id` tests run without MySQL.

## 7. File Structure
| Test file | Maps to |
|-----------|---------|
| `AlertsVisibilityTest.php` | `includes/alerts_visibility.php` |
| `TodoVisibilityTest.php` | `includes/todo_visibility.php` |
| `NotesVisibilityTest.php` | `includes/notes_visibility.php` |
| `ItmMbqaTestUserTest.php` | `includes/itm_mbqa_test_user.php` |
| `ItmScriptEntryGuardTest.php` | `includes/itm_script_entry_guard.php` |
| `SwitchPortApiHelpersTest.php` | `includes/switch_port_api_helpers.php` |
| `ApiRateLimitTest.php` | `includes/itm_api_rate_limit.php` (tier caps, Free no API key, probe payload) |
| `ItmDateFormatTest.php` | `includes/itm_date_format.php` (dd/mm/yyyy parse/display contract) |
| `CompanyModuleAccessDiscoveryTest.php` | `itm_ensure_registry_rows_for_module_slugs()`, `itm_sidebar_structure()` table discovery (requires MySQL) |

## 10. Common Pitfalls
- Do not `require` `header.php` / `sidebar.php` here — use guard tests only; partials need layout context.
- MBQA detector tests must use strict `MBQA-{table}-{company}-{seq}-{hash}` tags, not loose `mbqa-*` prefixes.

## 11. Examples of Safe Code Patterns

```bash
php scripts/run_tests.php --filter AlertsVisibilityTest
ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php --filter Includes
```

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/AGENT_NOTES.md`. Plan: `docs/PHPUNIT_PLAN.md` Phase 1.
