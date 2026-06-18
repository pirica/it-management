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

### 2. PHP Settings
Provides a detailed look at the PHP environment:
- **PHP Core:** Active version and path to the loaded `php.ini` file.
- **Resource Limits:** Key configuration values like `memory_limit`, `upload_max_filesize`, `post_max_size`, and `max_execution_time`.
- **Enabled Extensions:** A comprehensive list of all loaded PHP modules.

### 3. Database
Metrics related to the MySQL/MariaDB service:
- **MySQL Service:** Service status (Running/Stopped), display name, and binary version.
- **Storage Summary:** Total data size across all databases.
- **Database Metrics:** List of all databases and their respective sizes on disk (PHP `information_schema` plus API table).

## PowerShell Scripts (Windows)
Metrics on Laragon are collected using PowerShell scripts in `includes/`. All scripts return data in a standardized JSON format:

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

## PHP-native fallbacks (Linux / CI)
`includes/itm_system_status_native.php` serves the same `action=` values without PowerShell (`/proc` metrics, `ini_get()`, mysqli). Used automatically by `scripts/system_status_api.php` when not on Windows.

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
