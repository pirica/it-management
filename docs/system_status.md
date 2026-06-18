# System Status Module

## Overview
The **System Status** module provides monitoring and configuration insights for administrators. Metrics are **cached in the `system_status` table** (one row per tab). **Refresh** collects live data and upserts the cache; tab views read from the database.

Optimised for **Windows 11 + Laragon** (PowerShell hardware collection) with **PHP-native fallbacks** on Linux/CI.

Module path: `modules/system_status/index.php` (Admin only).

## Cache table (`system_status`)

| Column | Purpose |
|--------|---------|
| `tab_key` | `monitoring`, `php_settings`, or `database` (unique) |
| `payload_json` | UTF-8 JSON snapshot for that tab |
| `company_id` | Tenant anchor for standard fields (cache rows use company `1`) |
| `updated_at` | Last successful Refresh timestamp |

Helpers: `includes/itm_system_status_cache.php` — `itm_system_status_cache_get()`, `itm_system_status_cache_save()`, `itm_system_status_refresh_tab()`, `itm_system_status_refresh_all()`.

## Tabs

### 1. Monitoring (`?tab=monitoring`)
Cached payload includes:
- **System Overview:** OS version, hostname, uptime, CPU model, cores/threads (from `system_info` action).
- **CPU Usage:** Gauge from cached `cpu_usage`.
- **RAM Usage:** Gauge from cached `system_info` RAM fields.
- **Disk Usage:** Progress bars from cached `system_info.disks`.
- **Sub Storage:** Explorer (`files/{company_id}/` tree) and upload directories from cached `storage_report`. File counts exclude system placeholders: `.htaccess`, `index.html`, `AGENT_NOTES.md`.

### 2. PHP Settings (`?tab=php_settings`)
Cached payload includes:
- **PHP Core:** Version, SAPI, binary path, loaded `php.ini`.
- **Resource Limits:** `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`.
- **Enabled Extensions:** `get_loaded_extensions()` list.
- **Full detail:** Admin link to `scripts/system_status_phpinfo.php` (live `phpinfo()`, not cached).

### 3. Database (`?tab=database`)
Cached payload includes:
- **MySQL Service:** Running/Stopped, version, active `DB_NAME`.
- **Storage Summary:** Total size, table count, approximate rows for active database.
- **Database Metrics:** Per-table row counts and sizes from `information_schema`.

## Refresh workflow
1. Admin clicks **Refresh** (POST with CSRF) on any tab.
2. `itm_system_status_refresh_all()` collects live metrics for all three tabs and upserts `system_status`.
3. Page reloads the active tab from cache; toolbar shows **Last refreshed** (`dd/mm/yyyy HH:MM`).
4. First visit to a tab with no cache row auto-seeds that tab once.

## Responsive layout
Module CSS lives in `modules/system_status/index.php` (not global `css/styles.css`). Tab partials avoid layout inline styles; shared classes include:

| Class | Role |
|-------|------|
| `.metrics-grid` | Auto-fit card grid (`minmax(min(100%, 280px), 1fr)`) |
| `.ss-metric-span-wide` / `.ss-metric-span-full` | Full width on mobile; 2- or 3-column span from 768px / 1024px |
| `.ss-disk-grid` | Nested disk drive tiles |
| `.ss-extensions-columns` | 1 / 2 / 3 CSS columns by breakpoint |
| `.audit-table-wrap` | Horizontal scroll for wide database table |
| `.ss-storage-summary` / `.ss-storage-leaf` | Stack to one column below 576px |

Disk usage progress bars keep a dynamic inline `width` percentage only.

## PowerShell Scripts (Windows hardware collection)
On Windows, **Refresh** for the Monitoring tab uses PowerShell scripts in `includes/` for hardware metrics (`system_info`, `cpu_usage`, etc.). PHP and MySQL collection always uses the native PHP/mysqli runtime.

```json
{
  "status": "success",
  "data": { ... }
}
```

### Script List:
- `system_info.ps1`
- `cpu_usage.ps1`
- `ram_usage.ps1`
- `disk_usage.ps1`
- `uptime.ps1`
- `php_version.ps1`
- `php_extensions.ps1`
- `php_ini_values.ps1`
- `mysql_status.ps1`
- `mysql_version.ps1`
- `mysql_databases.ps1`
- `mysql_size.ps1`

## PHP-native runtime (all platforms)
`includes/itm_system_status_native.php` serves PHP/MySQL actions on every host, and hardware actions on Linux/CI (`/proc`, `disk_*_space`). `includes/itm_system_status_powershell.php` runs Windows hardware scripts with `shell_exec` permission checks.

`scripts/system_status_api.php` remains available for programmatic JSON probes (Admin session). Module tabs no longer call it on page load — they use the DB cache.

Admin `phpinfo()`: `scripts/system_status_phpinfo.php`.

## API Endpoints
`scripts/system_status_api.php` dispatches metrics. Access is restricted to the **Admin** role (session).

Example: `/scripts/system_status_api.php?action=cpu_usage`

Full action list: `scripts/api.php` → **System Status API**.

## Verification
| Command | Purpose |
|---------|---------|
| `php scripts/verify_system_status.php` | Module files, registry row, cache table, cache refresh/read, native payloads, storage + DB reports |
| `php scripts/test_system_info.php` (etc.) | Per-script PowerShell JSON validation on Windows |
| `php scripts/run_tests.php --filter SystemStatusApiTest` | PHPUnit file-existence checks |

See `scripts/SCRIPTS.md` → **System Status scripts** and `modules/system_status/AGENT_NOTES.md`.

## Screenshots
`python3 scripts/take_screenshots_modules.py` captures `docs/readme/system_status.png` (monitoring tab) for README.
