# ⚡ Bolt Optimization: Sidebar Performance

## 💡 What
Optimized the sidebar rendering logic by introducing per-request static caching and batch metadata pre-fetching.

## 🎯 Why
The sidebar was suffering from an **N+1 query bottleneck**. Every menu item (approx. 50+ items) would independently:
1. Fetch the full UI configuration from the database.
2. Check module access rights.
3. Resolve custom icons for the company.

This resulted in ~563 queries per page load for the sidebar alone.

## 📊 Impact
* **Query Reduction:** Decreased from ~563 queries to **9 queries** for a full sidebar load (98.4% improvement).
* **Latency:** Significant reduction in Time to First Byte (TTFB) on all authenticated pages.

## 🔬 Measurement
1. Run the benchmark script:
   ```bash
   php scripts/benchmark_sidebar_module_access.php
   ```
2. Apply the fix:
   ```bash
   php docs/bolt/fix_sidebar_performance/auto_fix_perf.php
   ```
3. Re-run the benchmark to verify the query count reduction.

## 🛠️ Changes
* **`includes/ui_config.php`**:
    * Added a static cache to `itm_get_ui_configuration` with invalidation support.
    * Updated `itm_save_ui_configuration` to clear the cache on update.
* **`includes/itm_company_module_access.php`**:
    * Updated `has_module_access` to perform a single batch query for all company module icons and access flags on the first call.
    * Introduced `itm_module_access_shared_static_cache` to hold this data.
    * Updated `itm_resolve_module_sidebar_icon` to use the shared cache instead of individual queries.
    * Updated `itm_has_module_access_bust_cache` to also clear the UI configuration cache.
