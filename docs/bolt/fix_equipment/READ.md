# Bolt Performance Optimization - Equipment Module

This module iteration focuses on reducing database load and improving response times for the Equipment index page.

## Optimizations Applied

1.  **Cached Schema Check**: Replaced a raw `SHOW COLUMNS` query for `switch_fiber_port_label` with the cached `itm_table_has_column()` helper.
2.  **Smart Search Joins**: Refactored `itm_equipment_search_join_sql()` to support a minimal join set. When search is not active (default list view), the number of `LEFT JOIN`s is reduced from ~25 down to 8.
3.  **Lazy-Loaded Switch Data**: The expensive query that fetches all switches for the company (to populate the Switch Port Manager) now only executes when the manager is explicitly requested via `spm=1`.
4.  **Paginated Switch Detection**: The "Switch Port Manager" buttons in the action column are now determined based on the data already fetched for the current page, eliminating a redundant company-wide switch scan.
5.  **Optimized Count Query**: The total row count query now omits joins entirely when no filters or search terms are applied.

## Performance Impact

-   **Reduced Query Complexity**: Significant reduction in JOIN operations for the default view.
-   **Eliminated Redundant Queries**: One expensive company-wide query skipped per page load in the default state.
-   **Faster Metadata Checks**: Schema results are now served from memory after the first check.

## Files Modified (via auto_fix_vuln.php)

-   `includes/itm_equipment_search.php`
-   `modules/equipment/index.php`
