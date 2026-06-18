# 📓 BOLT’S JOURNAL (PHP Edition)

## 24-05-2026 - Optimized Metadata Checks via Static Caching
**Learning:** Frequent calls to `information_schema.COLUMNS` for individual column presence and nullability checks created significant query overhead during application bootstrap, especially in modules like Switch Port Manager which perform multiple schema compatibility checks.
**Action:** Implemented a per-request static cache in `itm_table_has_column` and `itm_table_column_is_nullable`. The first check for any column in a table now fetches all columns for that table in a single query. This reduced overhead for repeated checks in a single request by over 99% in benchmarks.

**Verify:** `php scripts/verify_metadata_column_cache.php` — cold batch expects `Questions` delta 1–2 (MySQL may count prepare+execute); warm repeat expects delta 0. Optional env: `ITM_META_CACHE_TABLE` (default `switch_ports`).
