# Bolt Journal - Performance Learnings

This journal records critical learnings, surprising behaviors, and performance bottlenecks identified during Bolt's iterations.

## 28-03-2026 - Equipment Module N+1 and Join Bloat
**Learning:** The Equipment module was performing a company-wide query for all switches on every page load, even when the Switch Port Manager (SPM) was not active. Additionally, the list query used a "one-size-fits-all" join strategy, including ~25 `LEFT JOIN`s for search targets even on the default, non-filtered view.
**Action:** Implemented lazy-loading for the switch data (only query when `spm=1`). Refactored the search join helper to support a minimal join set for the default display. Replaced raw schema queries with the cached `itm_table_has_column()` helper to avoid repeated metadata lookups.
