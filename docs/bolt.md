# ⚡ BOLT JOURNAL

## 19-06-2026 - Optimize Module Access Checks
**Learning:** The `has_module_access` function was performing multiple database queries per call, including a call to `itm_is_admin`. When rendering the sidebar (130+ items), this resulted in a 2N+1 query pattern, significantly slowing down page loads. Additionally, `itm_ensure_registry_rows_for_module_slugs` was performing a query per module slug to check for existence.

**Action:**
1. Implement per-request static caching for `itm_is_admin` results in `config/config.php`.
2. Implement per-request pre-fetch static caching in `has_module_access` (`includes/itm_company_module_access.php`) to load all company module permissions in one query.
3. Optimize `itm_ensure_registry_rows_for_module_slugs` (`includes/itm_company_module_access.php`) to use a single query to check existing slugs.

**Impact:**
- Query count for sidebar rendering dropped from **~417 queries** to **~7 queries**.
- `has_module_access` (100 checks): 200 queries -> 2 queries.
- `itm_sidebar_structure`: 171 queries -> 6 queries.
- Measured average iteration time (mocked 100 modules): **0.057s -> 0.014s** (75% faster).
