# Bolt Journal (PHP Edition)

Critical performance learnings only. Routine optimizations are not logged here.

## 19-06-2026 - Sidebar module-access N+1 on every slug check
**Learning:** Building the live sidebar (`itm_sidebar_structure()` plus one `has_module_access()` per catalog slug) used roughly **417** MySQL `Questions` on a typical TechCorp-sized registry when each slug re-ran registry, admin, and `company_module_access` lookups. Mocked **100** repeated `has_module_access()` calls added ~**200** queries; `itm_sidebar_structure()` alone added ~**171**. Access-only timing for those 100 checks was ~**0.057s** legacy vs ~**0.014s** optimized (~**75%** faster). Absolute totals vary by registry row count and discovery path — treat `php scripts/benchmark_sidebar_module_access.php` as the authoritative local measurement, not fixed constants.
**Action:** Prefetch all `modules_registry` + `company_module_access` rows for the active `company_id` once per request inside `has_module_access()` (`includes/itm_company_module_access.php`); keep per-request `static` structure cache in `itm_sidebar_structure()`. Writers that mutate CMA or registry rows call `itm_has_module_access_bust_cache()`. Regression: `php scripts/benchmark_sidebar_module_access.php` (see **`scripts/SCRIPTS.md` → Sidebar module-access benchmark**). Measured optimized path (same environment): full sidebar ~**7** queries; `has_module_access` ×100 ~**2**; `itm_sidebar_structure` ~**6**.

## 19-06-2026 - UI configuration schema ensure runs 20+ metadata queries per call
**Learning:** `itm_ensure_ui_configuration_table()` and `itm_ensure_employee_sidebar_preferences_table()` each issue many `SHOW TABLES` / `SHOW COLUMNS` / `SHOW INDEX` checks on every invocation. A typical authenticated request calls them from `config.php` (`itm_get_ui_configuration`) and again from API rate-limit helpers — redundant work within the same PHP request.
**Action:** Cache successful schema-ensure results in a per-request `static` flag; bypass the cache only when callers pass a `$report` array (Settings diagnostics).

## 20-06-2026 - Redundant INFORMATION_SCHEMA queries during auditing
**Learning:** Every audited database mutation (INSERT/UPDATE/DELETE) was performing at least one `INFORMATION_SCHEMA.COLUMNS` lookup via `itm_audit_table_has_column()` to check for `company_id` presence. In some cases, this added multiple redundant queries per record. Benchmark of 50 iterations showed **100** queries legacy vs **51** optimized (median ~**1.02** queries per iteration after first fetch).
**Action:** Optimized `itm_audit_table_has_column()` in `includes/audit_functions.php` to delegate to `itm_table_has_column()` from `includes/bootstrap_helpers.php`, which implements a per-request static cache and fetches all columns for a table in a single batch query. Also added dynamic detection for `user_id`/`employee_id` columns in `audit_logs` to ensure cross-schema compatibility.
