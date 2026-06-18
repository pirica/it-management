# System Status Module

## Overview
The **System Status** module provides real-time monitoring and configuration insights for a Windows 11 Laragon-based web-server. It is designed for administrators to quickly assess server health, PHP environment, and database status.

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
- **Database Metrics:** List of all databases and their respective sizes on disk (calculated via PHP/SQL).

## PowerShell Scripts
Metrics are collected using PowerShell scripts located in `/includes/`. All scripts return data in a standardized JSON format:

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

## API Endpoints
The module uses `scripts/system_status_api.php` as a dispatcher to execute these scripts. Access is restricted to the **Admin** role.

Example usage: `/scripts/system_status_api.php?action=cpu_usage`

## Verification
A suite of test scripts is provided in `/scripts/` to validate the output of each PowerShell script. See `scripts/SCRIPTS.md` for details.
