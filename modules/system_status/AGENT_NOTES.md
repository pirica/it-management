# AGENT_NOTES.md - System Status

---

## 1. Module Purpose

Admin-only diagnostic dashboard for server health: real-time monitoring (CPU, RAM, disk), on-disk **Sub Storage** breakdown (Explorer + upload trees), PHP configuration, and MySQL/database metrics.

- **Monitoring tab:** hardware metrics via AJAX (`scripts/system_status_api.php`); **Sub Storage** server-rendered via `includes/itm_system_status_storage.php`.
- **PHP Settings tab:** server-rendered from the active Apache PHP runtime (no AJAX).
- **Database tab:** server-rendered from the active mysqli connection and `DB_NAME` (no AJAX).

Canonical overview: `docs/system_status.md`.

---

## 2. Key Tables

No owned tables. Read-only queries:

- **information_schema.TABLES** — per-table row counts and sizes for **active** `DB_NAME` only on the Database tab.
- **companies**, **departments**, **users**, **user_companies** — read for Explorer storage tree labels only (not tenant-scoped display).

Registry: `modules_registry.module_slug = system_status` (system module, active).

---

## 3. Required Relationships

N/A — no FK-owned data.

---

## 4. Business Rules (Critical for Agents)

- **Admin only:** `index.php`, `scripts/system_status_api.php`, and `scripts/system_status_phpinfo.php` require `itm_is_admin()`; non-admins redirect to `dashboard.php` (module UI) or receive HTTP 403 (`system_status_phpinfo.php`, API).
- **Read-only:** no INSERT/UPDATE/DELETE; no audit triggers required.
- **PHP + MySQL API actions:** always native (`ini_get()`, `get_loaded_extensions()`, mysqli) — never PowerShell — so Laragon tabs work when `shell_exec` is disabled.
- **Hardware (Monitoring tab):** Windows uses `includes/*.ps1` via `itm_system_status_run_powershell_action()`; non-Windows uses `itm_system_status_native_payload()` (`/proc`, `disk_*_space`).
- **API action allowlist:** `scripts/system_status_api.php` rejects unknown `action` values with HTTP 400. `itm_system_status_run_powershell_action()` allowlists **hardware actions only** and requires `[a-z0-9_]+` before loading `includes/{action}.ps1`.
- **Win11 troubleshooting:** run `php scripts/verify_system_status.php` — checks layout, registry, native payloads, storage/DB reports, `information_schema`; on Windows also checks `shell_exec`, `.ps1` readability, and per-script `test_*.php` wrappers.
- **Not standard CRUD:** no `create.php` / `delete.php`; tab router only (`?tab=monitoring|php_settings|database`).

---

## 5. UI Behavior Requirements

- **Tabs:** Monitoring, PHP Settings, Database (`index.php` → `tabs/*.php`). Invalid `tab` query falls back to `monitoring`.
- **AJAX (Monitoring hardware only):** fetches `../../scripts/system_status_api.php?action=…` (Chart.js doughnuts). Failed hardware calls show an inline error instead of perpetual Loading….
- **Sub Storage (Monitoring):** server-rendered Explorer `files/{company_id}/` tree (Common, Departments by dept, Private by user, Trash), plus `tickets_photos/`, `images/`, `floor_plans/` (by company), `backups/`. Parent folders with children include **direct files in that folder** plus child totals (`itm_system_status_directory_direct_metrics()`).
- **PHP Settings tab:** vertical stack (`.metrics-stack`) of three cards — PHP Core, Resource Limits, Enabled Extensions. Extensions use a scrollable three-column list (`.ss-extensions-list`, max-height 320px, `tabindex="0"`). Link to `scripts/system_status_phpinfo.php`. Long paths wrap via `.ss-path-value`.
- **Database tab:** active `DB_NAME` only — table list with approximate row counts, per-table size, and totals via `itm_system_status_build_database_table_report()`.
- **Tabs UI:** active tab uses `var(--accent)` background with white label text.
- **Refresh:** toolbar **Refresh** reloads current tab (`?tab=` preserved).
- **Layout:** shared `sidebar.php` / `header.php`; module-specific CSS in `index.php` (`.metrics-grid`, `.metrics-stack`, `.metric-card`, `.ss-storage-*`, `.status-badge`).

---

## 6. API Actions (If Applicable)

Dispatcher: `scripts/system_status_api.php` (Admin session required; JSON `Content-Type: charset=utf-8`).

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

Documented in `scripts/api.php` (`itmDocSystemStatusApiActions()`). Catalogued in `scripts/scripts.php` and `scripts/SCRIPTS.md` → System Status scripts.

---

## 7. File Structure

| Path | Role |
|------|------|
| `index.php` | Admin gate, tab router, shared module CSS |
| `tabs/monitoring.php` | System overview, CPU/RAM gauges, disk cards, Sub Storage tree |
| `tabs/php_settings.php` | Server-rendered PHP core, limits, extensions; phpinfo link |
| `tabs/database.php` | Active `DB_NAME` table metrics with row counts and totals |
| `AGENT_NOTES.md` | This file |

**Shared helpers**

| Path | Role |
|------|------|
| `includes/itm_system_status_native.php` | PHP/MySQL + Linux hardware JSON payloads |
| `includes/itm_system_status_powershell.php` | Windows hardware `shell_exec` runner; hardware-only action allowlist |
| `includes/itm_system_status_storage.php` | On-disk storage tree + active DB table report |
| `includes/*.ps1` | Windows hardware metrics (12 scripts; PHP/MySQL API actions no longer route here) |
| `scripts/system_status_api.php` | Admin JSON dispatcher |
| `scripts/system_status_phpinfo.php` | Admin-only full `phpinfo()` |
| `scripts/verify_system_status.php` | Regression runner (CLI + browser) |

---

## 8. Multi-Tenant Rules

System-wide metrics; not scoped by `company_id` for display. Admin gate is the access control. Storage tree lists all companies' Explorer folders. Database size queries use the active mysqli connection (server-wide `information_schema`).

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
- **Storage parent totals:** nodes with children must include direct files in that folder — do not sum only child bytes (see `itm_system_status_directory_direct_metrics()`).
- **README screenshot:** `python3 scripts/take_screenshots_modules.py` must wait for `#system-info-content` populated — otherwise `docs/readme/system_status.png` shows login or Loading….

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

### PowerShell action guard (runner)

```php
if (!in_array($action, $allowedActions, true) || !preg_match('/^[a-z0-9_]+$/', $action)) {
    return ['status' => 'error', 'message' => 'Invalid PowerShell action requested.'];
}
```

---

## 12. Module Owner Notes (Optional)

| Check | Command |
|-------|---------|
| Full regression | `php scripts/verify_system_status.php` |
| Per-script `.ps1` probes (Windows) | `php scripts/test_system_info.php`, `test_cpu_usage.php`, … |
| PHPUnit | `phpunit/tests/Unit/Modules/SystemStatusApiTest.php` |
| README screenshot | `ITM_SCREENSHOT_ONLY=system_status python3 scripts/take_screenshots_modules.py` → `docs/readme/system_status.png` |

Run verification when changing this module, `scripts/system_status_api.php`, `includes/itm_system_status_*.php`, or any `includes/*.ps1` metrics script.
