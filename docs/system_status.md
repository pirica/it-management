# System Status Module

## Overview
The **System Status** module provides real-time monitoring and configuration insights for administrators. It is optimised for **Windows 11 + Laragon** (PowerShell metrics) and ships **PHP-native fallbacks** on Linux/CI so the same tabs and API actions keep working without `powershell.exe`.

Module path: `modules/system_status/index.php` (Admin only).

## Tabs

### 1. Monitoring
Displays core system metrics:
- **System Overview:** OS version, hostname, uptime, CPU model, and core/thread counts.
- **CPU Usage:** Real-time gauge showing total CPU load.
- **RAM Usage:** Real-time gauge showing used vs total physical memory.
- **Disk Usage:** Progress bars for each local physical drive showing used and free space.
- **Sub Storage:** On-disk usage for Explorer (`files/{company_id}/` with expandable Common, Departments, Private, Trash), `tickets_photos/`, `images/`, `floor_plans/` (by company), and `backups/`.

### 2. PHP Settings
Rendered directly from the **active Apache PHP runtime** (no AJAX or PowerShell):
- **PHP Core:** Version, SAPI, binary path, and loaded `php.ini` file.
- **Resource Limits:** `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`.
- **Enabled Extensions:** All loaded modules from `get_loaded_extensions()`.
- **Full detail:** Admin link to `scripts/system_status_phpinfo.php` (`phpinfo()`).

### 3. Database
Rendered from the **active mysqli connection** and `DB_NAME` (no AJAX):
- **MySQL Service:** Running/Stopped from `mysqli_ping()`, version from `mysqli_get_server_info()`.
- **Storage Summary:** Total size, table count, and approximate row total for the active database only.
- **Database Metrics:** Every table in `DB_NAME` with approximate row count and size, plus a totals row.

## PowerShell Scripts (Windows hardware only)
On Windows, **Monitoring** hardware metrics (`system_info`, `cpu_usage`, etc.) use PowerShell scripts in `includes/`. PHP and MySQL API actions always use the native PHP/mysqli runtime — the `.ps1` PHP/MySQL scripts remain for `test_*.php` regression only.

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

`scripts/system_status_api.php` routes PHP/MySQL actions through native first; Windows hardware uses PowerShell.

Admin `phpinfo()`: `scripts/system_status_phpinfo.php`.

## API Endpoints
`scripts/system_status_api.php` dispatches metrics. Access is restricted to the **Admin** role (session).

Example: `/scripts/system_status_api.php?action=cpu_usage`

Full action list: `scripts/api.php` → **System Status API**.

## Verification
| Command | Purpose |
|---------|---------|
| `php scripts/verify_system_status.php` | Module layout, registry row, native payloads, DB size query |
| `php scripts/test_system_info.php` (etc.) | Per-script PowerShell JSON validation on Windows |
| `php scripts/run_tests.php --filter SystemStatusApiTest` | PHPUnit file-existence checks |

See `scripts/SCRIPTS.md` → **System Status scripts** and `modules/system_status/AGENT_NOTES.md`.

## Screenshots
`python3 scripts/take_screenshots_modules.py` captures `docs/readme/system_status.png` (monitoring tab) for README.
