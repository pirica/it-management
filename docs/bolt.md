# Bolt Journal (PHP Edition)

Critical performance learnings only. Routine optimizations are not logged here.

## 19-06-2026 - UI configuration schema ensure runs 20+ metadata queries per call
**Learning:** `itm_ensure_ui_configuration_table()` and `itm_ensure_employee_sidebar_preferences_table()` each issue many `SHOW TABLES` / `SHOW COLUMNS` / `SHOW INDEX` checks on every invocation. A typical authenticated request calls them from `config.php` (`itm_get_ui_configuration`) and again from API rate-limit helpers — redundant work within the same PHP request.
**Action:** Cache successful schema-ensure results in a per-request `static` flag; bypass the cache only when callers pass a `$report` array (Settings diagnostics).
