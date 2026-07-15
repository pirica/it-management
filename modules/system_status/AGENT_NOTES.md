# AGENT_NOTES.md - System Status

---

## 1. Module Purpose

Admin-only diagnostic dashboard for server health: monitoring (CPU, RAM, disk), on-disk **Sub Storage** breakdown (Explorer + upload trees), PHP configuration, and MySQL/database metrics.

- **Cache:** one `system_status` row per tab (`monitoring`, `php_settings`, `database`) stores JSON snapshots.
- **Refresh:** POST collects live metrics for **all** tabs and upserts the cache.
- **Display:** tabs read cached `payload_json`; first visit auto-seeds the active tab when no row exists.

Canonical overview: `docs/system_status.md`.

---

## 2. Key Tables

| Table | Role |
|-------|------|
| **`system_status`** | Owned cache — `tab_key`, `payload_json` (`LONGTEXT` utf8mb4), `company_id` (composite unique with `tab_key`). Columns `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`. `created_by` and `updated_by` are set from the session during cache save. Audit triggers on INSERT/UPDATE/DELETE log all columns. |

Read-only queries (during Refresh collection only):

- **information_schema.TABLES** — per-table row counts and sizes for **active** `DB_NAME` on the Database tab.
- **companies**, **departments**, **users**, **employee_companies** — Explorer storage tree labels.

Registry: `modules_registry.module_slug = system_status` (system module, active).

---

## 3. Required Relationships

- `system_status.company_id` → `companies.id` (ON DELETE CASCADE). Cache rows are scoped per tenant (`UNIQUE (company_id, tab_key)`); Refresh and reads use the active session `company_id` (fallback `ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID`, default `1`). When `SYSTEM_STATUS_DISABLE_TENANT_FALLBACK` is true, missing session tenant redirects to the dashboard instead of falling back.

---

## 4. Business Rules (Critical for Agents)

- **Admin only:** `index.php`, `scripts/system_status_api.php`, and `scripts/system_status_phpinfo.php` require `itm_is_admin()`; non-admins redirect to `dashboard.php` (module UI) or receive HTTP 403 (`system_status_phpinfo.php`, API).
- **Refresh is write-only maintenance:** POST `refresh_cache` + CSRF runs `itm_system_status_refresh_all()`; tabs do not re-query live metrics on normal GET.
- **PHP + MySQL collection:** always native (`ini_get()`, `get_loaded_extensions()`, mysqli) during Refresh — never PowerShell.
- **Hardware (Monitoring Refresh):** Windows uses `includes/*.ps1` via `itm_system_status_fetch_action_payload()`; non-Windows uses `itm_system_status_native_payload()`.
- **API action allowlist:** `scripts/system_status_api.php` rejects unknown `action` values with HTTP 400. `itm_system_status_run_powershell_action()` allowlists **hardware actions only** and requires `[a-z0-9_]+` before loading `includes/{action}.ps1`. Module UI no longer depends on AJAX for tab rendering.
- **Win11 troubleshooting:** run `php scripts/verify_system_status.php` — checks layout, registry, cache table, cache refresh/read, native payloads, storage/DB reports; on Windows also checks `shell_exec`, `.ps1` readability, and per-script `test_*.php` wrappers.
- **Not standard CRUD:** no `create.php` / `delete.php`; tab router only (`?tab=monitoring|php_settings|database`).

---

## 5. UI Behavior Requirements

- **Tabs:** Monitoring, PHP Settings, Database (`index.php` → `tabs/*.php`). Invalid `tab` query falls back to `monitoring`.
- **Cache display:** tabs render from `system_status.payload_json`; Chart.js gauges on Monitoring use embedded cached JSON (no live API calls on GET).
- **Refresh:** toolbar POST (`refresh_cache` + CSRF) runs `itm_system_status_refresh_all()` for **all** tabs; preserves active `?tab=` on redirect; shows **Last refreshed** from `updated_at` (`dd/mm/yyyy HH:MM`).
- **First visit:** when the active tab has no cache row, `index.php` auto-seeds that tab once on GET.
- **Empty cache:** warning banner on tab partials until Refresh or first-visit seed completes.
- **Monitoring:** cached `system_info`, `cpu_usage`, and `storage_report`; Sub Storage tree via `itm_system_status_render_storage_node()`.
- **Sub Storage:** file/byte totals exclude system placeholders `.htaccess`, `index.html`, and `AGENT_NOTES.md` (`itm_system_status_is_ignored_storage_file()`).
- **PHP Settings:** cached PHP core, limits, extensions (responsive `.ss-extensions-columns`); live detail via `scripts/system_status_phpinfo.php`.
- **Database:** cached MySQL status + `db_report` snapshot for active `DB_NAME`.
- **Tabs UI:** active tab uses `var(--accent)` background with white label text.
- **Layout:** shared `sidebar.php` / `header.php`; module-specific CSS in `index.php` (`.metrics-grid`, `.metrics-stack`, `.metric-card`, `.ss-storage-*`, `.status-badge`).
- **Responsive layout:** tab partials use CSS classes only (no layout inline styles). Breakpoints in `index.php`:
  - **≤575px:** info-table label column auto-width; Sub Storage summary/leaf grids stack to one column; storage meta wraps.
  - **≥768px:** `.ss-metric-span-wide` spans two grid columns; PHP extensions list uses two columns (`.ss-extensions-columns`).
  - **≥1024px:** `.ss-metric-span-full` spans three grid columns; extensions list uses three columns.
  - **Database table:** `.audit-table-wrap` horizontal scroll on narrow viewports (use the global `css/styles.css` rule — do not redeclare `.audit-table-wrap` in this module’s inline `<style>`); numeric columns use `.ss-table-num`.
  - **Dynamic only:** disk progress bar fill width remains inline (`width: N%`) because it is computed per drive.

---

## 6. API Actions (If Applicable)

Dispatcher: `scripts/system_status_api.php` (Admin session; JSON). Used for programmatic probes — **not** for module tab page loads after cache migration.

| action | Source |
|--------|--------|
| `system_info` | Windows: `includes/system_info.ps1`. Linux: `/proc` native |
| `cpu_usage` | Windows: `includes/cpu_usage.ps1`. Linux: `/proc/loadavg` |
| `ram_usage` | Windows: `includes/ram_usage.ps1`. Linux: `/proc/meminfo` |
| `disk_usage` | Windows: `includes/disk_usage.ps1`. Linux: `disk_*_space` |
| `uptime` | Windows: `includes/uptime.ps1`. Linux: `/proc/uptime` |
| `php_version` | Always native |
| `php_extensions` | Always native |
| `php_ini_values` | Always native |
| `mysql_status` | Always native |
| `mysql_version` | Always native |
| `mysql_databases` | Always native |
| `mysql_size` | Always native |

Documented in `scripts/api.php` (`itmDocSystemStatusApiActions()`). Catalogued in `scripts/scripts.php` and `scripts/SCRIPTS.md` → System Status scripts.

---

## 7. File Structure

| Path | Role |
|------|------|
| `index.php` | Admin gate, Refresh POST handler, cache load, tab router, shared CSS |
| `tabs/monitoring.php` | Cached system overview, CPU/RAM gauges, disk cards, Sub Storage tree |
| `tabs/php_settings.php` | Cached PHP core, limits, extensions; phpinfo link |
| `tabs/database.php` | Cached MySQL status + active `DB_NAME` table metrics |
| `AGENT_NOTES.md` | This file |

**Shared helpers**

| Path | Role |
|------|------|
| `includes/itm_system_status_cache.php` | Cache get/save/refresh; per-tab payload collectors |
| `includes/itm_system_status_native.php` | PHP/MySQL + Linux hardware JSON payloads |
| `includes/itm_system_status_powershell.php` | Windows hardware `shell_exec` runner; hardware-only action allowlist |
| `includes/itm_system_status_storage.php` | On-disk storage tree + active DB table report builders |
| `includes/*.ps1` | Windows hardware metrics (12 scripts; PHP/MySQL API actions no longer route here) |
| `scripts/system_status_api.php` | Admin JSON dispatcher (probes) |
| `scripts/system_status_phpinfo.php` | Admin-only full `phpinfo()` |
| `scripts/verify_system_status.php` | Regression runner (CLI + browser) |

---

## 8. Multi-Tenant Rules

Collected metrics are **system-wide** (hardware, PHP, MySQL) — not filtered by tenant for display. Admin gate is the access control. The Sub Storage tree lists all companies' Explorer/upload folders.

**Cache rows** are tenant-scoped: `UNIQUE (company_id, tab_key)` in `database.sql`. Refresh and tab reads use the active session `company_id`. When session `company_id` is missing or invalid (`<= 0`), the module falls back to `1` so admin diagnostics remain usable before company selection — intentional for this system-wide admin tool.

**Operational / security notes (cache fallback):**
- Access remains **admin-only** (`itm_is_admin()` gate on `index.php`, API, phpinfo script).
- Each fallback to `company_id = 1` logs `error_log()` with a correlation id (auditable; no raw session dump).
- Metrics displayed are system-wide; fallback affects cache row key only, not hardware/PHP/MySQL collection scope.

---

## 9. Audit Logging Requirements

`system_status` has `trg_system_status_audit_insert|update|delete` in `database.sql`. Refresh upserts log via UPDATE/INSERT triggers when `enable_audit_logs` is on.

---

## 10. Common Pitfalls

- **Stale tabs after deploy:** re-import `database.sql` (or create `system_status`) before Refresh works. [Valid]-[2026-07-15]
- **PowerShell on Linux:** hardware Refresh uses native `/proc` paths; never call `powershell.exe`. [Valid]-[2026-07-15]
- **`shell_exec` disabled on Windows:** Monitoring Refresh may record partial errors; PHP Settings and Database cache paths still work. [Valid]-[2026-07-15]
- **phpinfo link:** always live — not cached. [Valid]-[2026-07-15]
- **Storage parent totals:** nodes with children must include direct files in that folder — do not sum only child bytes. [Valid]-[2026-07-15]
- **System file exclusions:** Sub Storage file/byte totals skip `.htaccess`, `index.html`, and `AGENT_NOTES.md` (managed placeholders and agent docs, not user content). [Valid]-[2026-07-15]
- **README screenshot:** wait for `#system-info-content` populated (cached data) before capture. [Valid]-[2026-07-15]

---

## 11. Examples of Safe Code Patterns

### Cache read (index)

```php
$cacheCompanyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 1;
if ($cacheCompanyId <= 0) {
    $cacheCompanyId = 1;
}
$ssCache = itm_system_status_cache_get($conn, $active_tab, $cacheCompanyId);
$ssPayload = is_array($ssCache['payload'] ?? null) ? $ssCache['payload'] : null;
```

### Refresh all tabs (POST)

```php
itm_require_post_csrf();
$refreshResult = itm_system_status_refresh_all($conn, $cacheCompanyId);
```

### Admin gate (index)

```php
if (!isset($_SESSION['employee_id']) || !itm_is_admin($conn, $_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
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

Run verification when changing this module, `includes/itm_system_status_*.php`, `database.sql` `system_status`, or any `includes/*.ps1` metrics script.
