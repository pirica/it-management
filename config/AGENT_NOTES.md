# AGENT_NOTES.md - Config

## 1. Module Purpose
Maintains system-wide configuration, database credentials, path constants, and core security functions.

## 2. Key Tables
- Interacts with **companies** for initial tenant resolution.

## 4. Business Rules (Critical for Agents)
- **Environment Variables**: Prefer loading secrets from environment variables (e.g., `ITM_DB_HOST`).
- **No PDO**: The system strictly uses `mysqli`.
- **Zero Dependencies**: Do not introduce external packages (Composer/NPM).
- **Administrator helpers**: `itm_is_admin()` checks role/username; `itm_require_admin()` enforces admin access (HTTP 403 on POST, redirect on GET).
- **Tenant context**: `itm_resolve_active_company_id()` (from `includes/itm_role_module_permissions.php`) syncs `$company_id` from session when `config.php` short-circuits on repeat `require`.
- **Module access enforcement**: `itm_enforce_module_access_or_exit()` runs **after** `itm_is_admin()` is defined so system-module admin bypass works during central enforcement.
- **API rate-limit probe auth bypass**: `scripts/api.php?rate_limit=1` defines `ITM_API_RATE_LIMIT_PROBE` before loading `config.php`, which sets `$itmSkipWebAuth` so clients receive JSON instead of a `login.php` redirect. This is **not** anonymous access: **Free** tier may omit `api_key` only when `PHPSESSID` carries authenticated `company_id` + `employee_id`; otherwise `itm_api_resolve_rate_limit_row()` returns null and the probe responds `401`. Paid tiers always require `X-API-Key` / `api_key`.
- **No-auth script allowlist**: scripts that define `ITM_SCRIPT_NO_AUTH` before `config.php` may skip the login redirect in the browser when their basename is listed in `$itmNoAuthScripts` (currently `count_db_tables.php` only). Use only for read-only aggregate diagnostics.
- **JSON import validation**: `itm_handle_json_table_import()` rejects invalid numeric, date/datetime, and enum column values (increments `failed`, sets `ok:false`, HTTP 400 when no rows inserted/updated). Regression: `php scripts/verify_json_import_validation.php`. On **UPDATE** operations, only fields present in the import payload (or auto-derived during normalization, such as resolved foreign keys, created departments/positions, or reclassified `personal_email`) are modified in the database. **INSERT** operations still apply defaults/auto-derived values for any missing columns as before. Empty data rows (no non-blank, non-`null` cell values after normalization) are skipped without affecting existing data. Rows that match an existing `id` but supply no writable columns increment `skipped` (not `updated`). Field tracking uses a per-row `providedFields` list filtered to columns with resolved non-`NULL` SQL literals before building the UPDATE set. Regression: `php scripts/repro_employee_dataloss.php`, `php scripts/repro_generic_dataloss.php`.
- **System Status cache constants**: `ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID` (default `1`) and optional `SYSTEM_STATUS_DISABLE_TENANT_FALLBACK` (env `SYSTEM_STATUS_DISABLE_TENANT_FALLBACK=1` or define in `config.php`) control admin cache fallback when session `company_id` is missing.

## 7. File Structure
- **config.php** — the core configuration file required by every entry point.

## 10. Common Pitfalls
- Committing secrets to version control. [Cursor-Valid]
- Modifying constants without checking their global impact. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe Database Connection (via config.php)
```php
require_once 'config.php';
// $conn is now available
```

## 12. Module Owner Notes (Optional)
The single source of truth for system environment and security foundations.
