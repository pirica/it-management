# Bolt Journal (PHP Edition)

Critical performance learnings only. Routine optimizations are not logged here.

## 19-06-2026 - Sidebar module-access N+1 on every slug check
**Learning:** Building the live sidebar (`itm_sidebar_structure()` plus one `has_module_access()` per catalog slug) used roughly **417** MySQL `Questions` on a typical TechCorp-sized registry when each slug re-ran registry, admin, and `company_module_access` lookups. Mocked **100** repeated `has_module_access()` calls added ~**200** queries; `itm_sidebar_structure()` alone added ~**171**. Access-only timing for those 100 checks was ~**0.057s** legacy vs ~**0.014s** optimized (~**75%** faster). Absolute totals vary by registry row count and discovery path — treat `php scripts/benchmark_sidebar_module_access.php` as the authoritative local measurement, not fixed constants.
**Action:** Prefetch all `modules_registry` + `company_module_access` rows for the active `company_id` once per request inside `has_module_access()` (`includes/itm_company_module_access.php`); keep per-request `static` structure cache in `itm_sidebar_structure()`. Writers that mutate CMA or registry rows call `itm_has_module_access_bust_cache()`. Regression: `php scripts/benchmark_sidebar_module_access.php` (see **`scripts/SCRIPTS.md` → Sidebar module-access benchmark**). Measured optimized path (same environment): full sidebar ~**7** queries; `has_module_access` ×100 ~**2**; `itm_sidebar_structure` ~**6**.

## 19-06-2026 - UI configuration schema ensure runs 20+ metadata queries per call
**Learning:** `itm_ensure_ui_configuration_table()` and `itm_ensure_employee_sidebar_preferences_table()` each issue many `SHOW TABLES` / `SHOW COLUMNS` / `SHOW INDEX` checks on every invocation. A typical authenticated request calls them from `config.php` (`itm_get_ui_configuration`) and again from API rate-limit helpers — redundant work within the same PHP request.
**Action:** Cache successful schema-ensure results in a per-request `static` flag; bypass the cache only when callers pass a `$report` array (Settings diagnostics).

## 19-06-2026 - itm_audit_table_has_column repeated INFORMATION_SCHEMA queries
**Learning:** `itm_audit_table_has_column` (includes/audit_functions.php) executed a fresh `information_schema.COLUMNS` query on every call. During data mutations (UPDATE/DELETE), this function is called to verify the presence of `company_id`. Since `itm_table_has_column` (includes/bootstrap_helpers.php) already implements a per-request static cache for all columns of a table, using it reduces the query overhead by ~100% for repeated checks.
**Action:** Modified `itm_audit_table_has_column` to use `itm_table_has_column` when available, with a fallback for environments where the bootstrap helper might not be loaded. Measured improvement: Queries per iteration reduced from 1 to 0 (cached) in `scripts/benchmark_audit_column_check.php`.

## 25-10-2026 - Sidebar rendering N+1 on UI configuration and Registry lookups
**Learning:** Rendering the sidebar with ~140 modules issued over **1,000** queries because `itm_get_ui_configuration` and `itm_module_access_registry_row` were called repeatedly without internal caching. The former also ran expensive relational reconciliation logic on every call. Baseline: **1,004 queries** and **316.47 ms**.
**Action:** Implemented per-request static caching. `itm_get_ui_configuration` now uses a `$cache` keyed by `company_id:user_id`, and `itm_module_access_registry_row` caches metadata rows by slug. Optimized versions were generated in Bolt docs. Estimated boost: **98%** reduction in queries.
