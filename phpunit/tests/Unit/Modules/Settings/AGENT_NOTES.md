# AGENT_NOTES.md - phpunit/tests/Unit/Modules/Settings

## 1. Module Purpose
PHPUnit coverage for `modules/settings/` API Access behaviour that is not covered by generic CRUD module tests.

## 7. File Structure
| File | Role |
|------|------|
| `SettingsApiKeyLastUsedTest.php` | **API Key Last Used** — `itm_api_format_key_last_used_display_label()` contract; Settings markup (`api_key_last_used_at_display`); `itm_api_consume_rate_limit()` updates `ui_configuration.api_key_last_used_at` on allowed Free/Basic consumes and leaves NULL when Basic is blocked at cap |

## 4. Business Rules (Critical for Agents)
- Uses disposable employees via `scripts/lib/itm_script_test_employee.php` and seeds `ui_configuration` through `scripts/lib/itm_api_tier_test_helpers.php` (`itm_apitest_seed_configuration` / `itm_apitest_reload_configuration` / `itm_apitest_cleanup_configuration`).
- Rate-limit probe (`scripts/api.php?rate_limit=1`) does **not** consume quota or touch `api_key_last_used_at` — only `itm_api_consume_rate_limit()` paths are asserted here.

## 10. Common Pitfalls
- Do not mutate seed Admin (`employee_id = 1`) or seed `ui_configuration` rows — always create disposable employees and clean up in `tearDown()`.
- `testCrossTenantAdminResolvesTenantSeedAdminConfiguration` clears `$_SESSION` before resolve assertions so earlier tests cannot leak `login_employee_id` / `employee_id` into `itm_api_resolve_configuration_employee_id()`.
