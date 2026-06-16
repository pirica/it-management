# AGENT_NOTES.md - PHPUnit Includes tests

## 1. Module Purpose
Unit tests for shared helpers under `includes/` that are safe to exercise without loading full module entry files. Improves HTML coverage for visibility SQL, MBQA markers, coverage guards, and switch-port AJAX helpers.

## 4. Business Rules (Critical for Agents)
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
