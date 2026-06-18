# AGENT_NOTES.md - System Status

---

## 1. Module Purpose

Admin-only diagnostic dashboard for server health: real-time monitoring (CPU, RAM, disk), PHP configuration, and MySQL/database metrics. Primary target is **Windows 11 + Laragon** (PowerShell metrics). **Linux/CI** hosts use PHP-native fallbacks via `includes/itm_system_status_native.php` so tabs and `scripts/system_status_api.php` still return JSON without `powershell.exe`.

Canonical overview: `docs/system_status.md`.

---

## 2. Key Tables

No owned tables. Read-only queries:

- **information_schema.TABLES** — database sizes on the Database tab (`tabs/database.php`).

Registry: `modules_registry.module_slug = system_status` (system module, active).

---

## 3. Required Relationships

N/A — no FK-owned data.

---

## 4. Business Rules (Critical for Agents)

- **Admin only:** `index.php` and `scripts/system_status_api.php` call `itm_is_admin()`; non-admins redirect to `dashboard.php` (UI) or receive HTTP 403 (API).
- **Read-only:** no INSERT/UPDATE/DELETE; no audit triggers required.
- **Windows:** full metrics via `includes/*.ps1` executed by `system_status_api.php`.
- **Non-Windows:** same `action=` values served from `itm_system_status_native_payload()` (`/proc` metrics, `ini_get()`, mysqli).
- **Not standard CRUD:** no `create.php` / `delete.php`; tab router only (`?tab=monitoring|php_settings|database`).

---

## 5. UI Behavior Requirements

- **Tabs:** Monitoring, PHP Settings, Database (`index.php` → `tabs/*.php`).
- **AJAX:** tabs fetch `../../scripts/system_status_api.php?action=…` (Chart.js doughnuts on Monitoring).
- **Refresh:** toolbar **Refresh** reloads current tab.
- **Database tab:** PHP-rendered size table (top 10) plus PowerShell/native AJAX table.
- **Layout:** uses shared `sidebar.php` / `header.php`; module-specific CSS in `index.php`.

---

## 6. API Actions (If Applicable)

Dispatcher: `scripts/system_status_api.php` (Admin session required).

| action | Windows source | Non-Windows fallback |
|--------|----------------|----------------------|
| `system_info` | `includes/system_info.ps1` | `/proc/meminfo`, `/proc/cpuinfo`, disk space |
| `cpu_usage` | `includes/cpu_usage.ps1` | `/proc/loadavg` |
| `ram_usage` | `includes/ram_usage.ps1` | `/proc/meminfo` |
| `disk_usage` | `includes/disk_usage.ps1` | `disk_total_space` / `disk_free_space` |
| `uptime` | `includes/uptime.ps1` | `/proc/uptime` |
| `php_version` | `includes/php_version.ps1` | `PHP_VERSION`, `php_ini_loaded_file()` |
| `php_extensions` | `includes/php_extensions.ps1` | `get_loaded_extensions()` |
| `php_ini_values` | `includes/php_ini_values.ps1` | `ini_get()` |
| `mysql_status` | `includes/mysql_status.ps1` | `mysqli_ping()` |
| `mysql_version` | `includes/mysql_version.ps1` | `mysqli_get_server_info()` |
| `mysql_databases` | `includes/mysql_databases.ps1` | `SHOW DATABASES` |
| `mysql_size` | `includes/mysql_size.ps1` | `information_schema` aggregate |

Documented in `scripts/api.php` (`itmDocSystemStatusApiActions()`).

---

## 7. File Structure

- `index.php` — Admin gate, tab router, shared styles.
- `tabs/monitoring.php` — System overview, CPU/RAM gauges, disk cards.
- `tabs/php_settings.php` — PHP core, limits, extensions list.
- `tabs/database.php` — MySQL service card, storage summary, size tables.
- `AGENT_NOTES.md` — this file.

Shared helpers:

- `includes/itm_system_status_native.php` — non-Windows JSON payloads.
- `includes/*.ps1` — Windows Laragon metrics (12 scripts).

---

## 8. Multi-Tenant Rules

System-wide metrics; not scoped by `company_id`. Admin gate is the access control. Database size queries use the active mysqli connection (server-wide `information_schema`).

---

## 9. Audit Logging Requirements

N/A (read-only).

---

## 10. Common Pitfalls

- **Element IDs:** Monitoring tab uses hyphenated IDs (`system-info-loader`, `system-info-content`); mismatched underscores leave loaders visible forever.
- **PowerShell on Linux:** `shell_exec('powershell.exe …')` returns null — native fallback must run first on non-Windows.
- **Laragon paths:** `.ps1` scripts fall back to `C:\laragon\bin\…` when binaries are not on PATH.
- **`shell_exec` disabled:** Windows metrics fail even for admins; PHP/native paths still work for PHP and DB actions on Linux.
- **MySQL service status on Linux:** native `mysql_status` reports Running when `mysqli_ping()` succeeds (not Windows Service Control Manager).

---

## 11. Examples of Safe Code Patterns

### Native fallback before PowerShell (API)

```php
if (!itm_system_status_is_windows()) {
    $json_data = itm_system_status_native_payload($action, $conn);
    echo json_encode($json_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
```

### Admin gate (index)

```php
if (!isset($_SESSION['user_id']) || !itm_is_admin($conn, $_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}
```

---

## 12. Module Owner Notes (Optional)

Regression: `php scripts/verify_system_status.php` (layout, registry, native payloads; Windows also runs `test_*.ps1` wrappers). Per-script probes: `php scripts/test_system_info.php`, etc. PHPUnit: `phpunit/tests/Unit/Modules/SystemStatusApiTest.php`. README screenshot: `docs/readme/system_status.png` via `python3 scripts/take_screenshots_modules.py`.
