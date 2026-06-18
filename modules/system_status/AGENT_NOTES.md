# AGENT_NOTES.md - System Status

---

## 1. Module Purpose

This module provides a comprehensive overview of the server status for a Windows 11 Laragon-based web-server. It displays real-time monitoring metrics, PHP configuration details, and MySQL database information.

---

## 2. Key Tables

This module does not own any specific data tables. It reads from:
- **information_schema.TABLES** — to calculate database sizes in the Database tab.

---

## 3. Required Relationships

N/A

---

## 4. Business Rules (Critical for Agents)

- **Admin Only:** This module is strictly restricted to users with the 'Admin' role.
- **Windows Environment:** Metrics collection relies on PowerShell scripts executed via `shell_exec()`. It is designed specifically for Windows-based servers (Laragon).
- **Read-Only:** The module is purely for diagnostic and monitoring purposes; it does not perform any mutations to the system or database.

---

## 5. UI Behavior Requirements

- **Tabs:** Three tabs (Monitoring, PHP Settings, Database) organized via URL parameters (`?tab=`).
- **Real-time Data:** Metrics in the Monitoring and PHP Settings tabs are fetched asynchronously via AJAX from `scripts/system_status_api.php`.
- **Charts:** Uses Chart.js for CPU and RAM usage gauges.
- **Refresh:** A manual refresh button reloads the page to update all metrics.

---

## 6. API Actions (If Applicable)

The module exposes internal API endpoints via `scripts/system_status_api.php`:
- **system_info** — OS, CPU, RAM, Disk, and Network info.
- **cpu_usage** — Current CPU load %.
- **ram_usage** — Used/Free/Total RAM.
- **disk_usage** — Usage for all local disks.
- **uptime** — Human-readable uptime.
- **php_version** — Active PHP version and ini path.
- **php_extensions** — List of enabled extensions.
- **php_ini_values** — Key ini settings.
- **mysql_status** — MySQL service status.
- **mysql_version** — MySQL version.
- **mysql_databases** — List of all databases.
- **mysql_size** — Size of each DB.

---

## 7. File Structure

- **index.php** — Main wrapper and tab router.
- **tabs/monitoring.php** — Monitoring dashboard with Chart.js.
- **tabs/php_settings.php** — PHP configuration view.
- **tabs/database.php** — MySQL status and metrics.
- **AGENT_NOTES.md** — Module documentation.

---

## 8. Multi-Tenant Rules

- **Admin Only:** Since it's an admin-only module, it bypasses standard company scoping for system-wide metrics, but SQL queries for database sizes use the active connection which may be scoped.

---

## 9. Audit Logging Requirements

N/A (Read-only module)

---

## 10. Common Pitfalls

- **PowerShell Execution:** Ensure `shell_exec` and PowerShell execution policy allow running the scripts in `includes/`.
- **Paths:** Hardcoded paths for Laragon fallbacks might need adjustment if the server installation differs significantly.
- **Environment:** These scripts will fail on Linux environments.

---

## 11. Examples of Safe Code Patterns

### Executing PowerShell via API
```php
$command = "powershell.exe -ExecutionPolicy Bypass -File " . escapeshellarg($script_path);
$output = shell_exec($command);
```

---

## 12. Module Owner Notes (Optional)

Regression scripts are registered in `scripts/scripts.php` for each PowerShell script.
