# Bolt's Journal

## 2026-09-01 - Per-request static caching of schema introspection queries
**Learning:** `itm_fk_table_column_names()` in `includes/fk_dropdown_helpers.php` previously ran a `DESCRIBE` SQL query every time a foreign key label or dropdown option was resolved. In pages rendering multiple FK cells or options, this caused dozens of redundant schema queries per HTTP request. Adding a simple per-request `static $cache` array in `itm_fk_table_column_names()` completely eliminates duplicate `DESCRIBE` queries for table column metadata across the request lifecycle.
**Action:** Always look for schema introspection functions (e.g. `DESCRIBE` or `information_schema` queries) that are called inside loops or helper functions, and wrap them in per-request static caches (`static $cache = []`).
