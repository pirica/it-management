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
| `CompanySessionTest.php` | `includes/itm_company_session.php` (Admin tenant remap, company access grants; skips when MySQL unavailable) |
| `TodoVisibilityTest.php` | `includes/todo_visibility.php` |
| `NotesVisibilityTest.php` | `includes/notes_visibility.php` |
| `ItmMbqaTestUserTest.php` | `includes/itm_mbqa_test_user.php` |
| `ItmScriptEntryGuardTest.php` | `includes/itm_script_entry_guard.php` |
| `SwitchPortApiHelpersTest.php` | `includes/switch_port_api_helpers.php` |
| `ApiRateLimitTest.php` | `includes/itm_api_rate_limit.php` (tier caps, Free no API key, probe payload, `itm_api_format_key_last_used_display_label`) |
| `AppointmentModalitySampleTest.php` | `itm_appointment_regression_*` canonical Mon–Fri modality matrix (DB-free) |
| `ItmDateFormatTest.php` | `includes/itm_date_format.php` (dd/mmm/yyyy parse/display; delegates display to `itm_ui_locale_format.php` when loaded); `itm_parse_datetime_input` UK `d/M/Y H:i`; `itm_datetime_input_local_value`; `itm_format_cell_scalar_display` money + ui_config date flip |
| `UiLocaleFormatTest.php` | `includes/itm_ui_locale_format.php` (Settings `ui_configuration` money + date/time defaults, prefix/suffix mutual exclusion, datetime fallback, `itm_is_money_field_name`) |
| `SecurityHeadersTest.php` | `includes/itm_security_headers.php` (`itm_build_content_security_policy()`, `itm_request_is_https()`, `itm_session_cookie_secure_from_config()`; DB-free) |
| `ItmCrudScalarColumnSearchTest.php` | `includes/itm_crud_scalar_column_search.php` (scalar list search OR fragments) |
| `AuditSqlParserTest.php` | `includes/audit_functions.php` → `itm_parse_audit_sql()` (INSERT/UPDATE/DELETE meta; DB-free) |
| `ExplorerNormalizePathTest.php` | `includes/itm_explorer_paths.php` → `explorer_normalize_relative_path()`, `itm_explorer_is_allowed_extension()` (DB-free) |
| `CompanyModuleAccessDiscoveryTest.php` | `itm_ensure_registry_rows_for_module_slugs()`, `itm_sidebar_structure()` table discovery (requires MySQL) |
| `SidebarSectionCollapseTest.php` | `itm_normalize_sidebar_collapsed_map()`, `itm_sidebar_section_collapse_feature_enabled()`, `itm_sidebar_is_valid_section_id()` |

## 10. Common Pitfalls
- Do not `require` `header.php` / `sidebar.php` here — use guard tests only; partials need layout context. [Cursor-Valid]
- MBQA detector tests must use strict `MBQA-{table}-{company}-{seq}-{hash}` tags, not loose `mbqa-*` prefixes. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

```bash
php scripts/run_tests.php --filter AlertsVisibilityTest
ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php --filter Includes
```

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/AGENT_NOTES.md`. Plan: `docs/PHPUNIT_PLAN.md` Phase 1.
