# AGENT_NOTES.md - System Status

---

## 1. Module Purpose

Admin-only diagnostic dashboard for server health: real-time monitoring (CPU, RAM, disk), PHP configuration, and MySQL/database metrics. **PHP Settings** and **Database** tabs are **server-rendered** from the active Apache/mysqli runtime (no AJAX). **Monitoring** uses `scripts/system_status_api.php` — hardware via `includes/*.ps1` on Windows (requires `shell_exec`) or `includes/itm_system_status_native.php` on Linux/CI. Full PHP detail: `scripts/system_status_phpinfo.php` (admin-only `phpinfo()`).

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
- **PHP + MySQL:** always native (`ini_get()`, `get_loaded_extensions()`, mysqli) — never PowerShell — so Laragon tabs work when `shell_exec` is disabled.
- **Hardware (Monitoring tab):** Windows uses `includes/*.ps1` via `itm_system_status_run_powershell_action()`; non-Windows uses `itm_system_status_native_payload()` (`/proc`, `disk_*_space`).
- **Win11 troubleshooting:** run `php scripts/verify_system_status.php` — checks `shell_exec`, `.ps1` readability, and per-script `test_*.php` wrappers.
- **Not standard CRUD:** no `create.php` / `delete.php`; tab router only (`?tab=monitoring|php_settings|database`).

---

## 5. UI Behavior Requirements

- **Tabs:** Monitoring, PHP Settings, Database (`index.php` → `tabs/*.php`).
- **AJAX:** Monitoring tab only — fetches `../../scripts/system_status_api.php?action=…` (Chart.js doughnuts). Failed hardware calls show an inline error instead of perpetual Loading….
- **PHP Settings tab:** server-rendered PHP core, limits, extensions; link to `scripts/system_status_phpinfo.php`.
- **Database tab:** server-rendered MySQL service card, storage summary, and full `information_schema` size table.
- **Refresh:** toolbar **Refresh** reloads current tab.
- **Layout:** uses shared `sidebar.php` / `header.php`; module-specific CSS in `index.php`.

---

## 6. API Actions (If Applicable)

Dispatcher: `scripts/system_status_api.php` (Admin session required).

| action | Source |
|--------|--------|
| `system_info` | Windows: `includes/system_info.ps1`. Linux: `/proc` native |
| `cpu_usage` | Windows: `includes/cpu_usage.ps1`. Linux: `/proc/loadavg` |
| `ram_usage` | Windows: `includes/ram_usage.ps1`. Linux: `/proc/meminfo` |
| `disk_usage` | Windows: `includes/disk_usage.ps1`. Linux: `disk_*_space` |
| `uptime` | Windows: `includes/uptime.ps1`. Linux: `/proc/uptime` |
| `php_version` | Always native (`PHP_VERSION`, `php_ini_loaded_file()`) |
| `php_extensions` | Always native (`get_loaded_extensions()`) |
| `php_ini_values` | Always native (`ini_get()`) |
| `mysql_status` | Always native (`mysqli_ping()`) |
| `mysql_version` | Always native (`mysqli_get_server_info()`) |
| `mysql_databases` | Always native (`SHOW DATABASES`) |
| `mysql_size` | Always native (`information_schema`) |

Documented in `scripts/api.php` (`itmDocSystemStatusApiActions()`).

---

## 7. File Structure

- `index.php` — Admin gate, tab router, shared styles.
- `tabs/monitoring.php` — System overview, CPU/RAM gauges, disk cards.
- `tabs/php_settings.php` — Server-rendered PHP core, limits, extensions; phpinfo link.
- `tabs/database.php` — Server-rendered MySQL service, storage summary, size table.
- `AGENT_NOTES.md` — this file.

Shared helpers:

- `includes/itm_system_status_native.php` — PHP/MySQL + Linux hardware JSON payloads.
- `includes/itm_system_status_powershell.php` — Windows `shell_exec` runner and permission checks.
- `includes/*.ps1` — Windows hardware metrics (12 scripts; PHP/MySQL API actions no longer route here).
- `scripts/system_status_phpinfo.php` — admin-only full `phpinfo()`.

---

## 8. Multi-Tenant Rules

System-wide metrics; not scoped by `company_id`. Admin gate is the access control. Database size queries use the active mysqli connection (server-wide `information_schema`).

---

## 9. Audit Logging Requirements

N/A (read-only).

---

## 10. Common Pitfalls

- **Element IDs:** Monitoring tab uses hyphenated IDs (`system-info-loader`, `system-info-content`); mismatched underscores leave loaders visible forever.
- **PowerShell on Linux:** hardware actions use native `/proc` paths; never call `powershell.exe`.
- **Laragon paths:** `.ps1` scripts use `Get-CimInstance`; Apache/PHP user must **read** `includes/*.ps1` (verify with `php scripts/verify_system_status.php`).
- **`shell_exec` disabled:** Monitoring hardware metrics fail with an inline error; PHP Settings and Database tabs still render (server-side PHP/mysqli).
- **Stale AJAX tabs:** PHP Settings and Database must not fetch PowerShell — they are server-rendered only.
- **MySQL service status on Linux:** native `mysql_status` reports Running when `mysqli_ping()` succeeds (not Windows Service Control Manager).

---

## 11. Examples of Safe Code Patterns

### Native-first PHP/MySQL (API)

```php
if (itm_system_status_prefers_native($action)) {
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
