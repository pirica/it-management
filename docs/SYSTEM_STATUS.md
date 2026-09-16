# System Status Diagnostics

An Admin-only real-time diagnostic dashboard providing hardware telemetry (CPU, RAM, disk usage), on-disk directory storage maps, PHP runtime settings, and MySQL database performance metrics.

| Aspect | Detail |
|-------|--------|
| **Registry Slug** | `system_status` (System module; enabled/disabled per-company via Company Module Access) |
| **Access Gate** | Restricted exclusively to administrators (`itm_is_admin()` helper). Non-admins are redirected back to the employee dashboard. |
| **Telemetry caching** | High-performance snapshot cache stored in the `system_status` table. Interactive views load from cache; updates are triggered on-demand via a POST Refresh action. |

---

## 1. Architectural Design & Telemetry Harvesting

The System Status module utilizes a hybrid native and subprocess execution pipeline:

```
                  +----------------------------------+
                  |  Admin triggers On-Demand POST   |
                  +-----------------+----------------+
                                    |
                                    v
                  +-----------------+----------------+
                  | itm_system_status_refresh_all()  |
                  +-----------------+----------------+
                                    |
            +-----------------------+-----------------------+
            | (Operating System OS Detection)               |
            |                                               |
            v (Windows OS)                                  v (Linux OS)
+-----------+-----------+                      +------------+------------+
|  Executes PowerShell   |                      | Parses native /proc files|
|  includes/*.ps1       |                      | (loadavg, meminfo, etc.)|
+-----------+-----------+                      +------------+------------+
            |                                               |
            +-----------------------+-----------------------+
                                    |
                                    v
                  +-----------------+----------------+
                  | Upserts telemetry payload as     |
                  | JSON into `system_status` table  |
                  +----------------------------------+
```

### A. Core Telemetry Source Mappings
- **PHP & MySQL Metrics:** Always harvested using native internal PHP wrappers (`ini_get()`, `get_loaded_extensions()`, mysqli) across both Linux and Windows environments — never delegating to shell scripts.
- **Windows Hardware Telemetry:** Executed via `shell_exec` invoking twelve optimized PowerShell scripts situated under `includes/*.ps1` (e.g., `cpu_usage.ps1`, `disk_usage.ps1`, `mysql_status.ps1`). These scripts output formatted JSON parsed directly by the caching manager.
- **Linux Hardware Telemetry:** Read directly from native kernel files (such as `/proc/loadavg` and `/proc/meminfo`) or system utilities to bypass execution overhead.

### B. Security Subprocess Hardening
- **PowerShell Execution Guard:** Subprocess execution utilizes `itm_system_status_run_powershell_action()`. The dispatcher sanitizes parameters against a strict alphanumeric allowlist `[a-z0-9_]+` and maps inputs directly to static files under `includes/{action}.ps1`.
- **System Command Shielding:** Dynamic values are never parsed or interpolated inside the system shell context, mitigating risk of RCE.

---

## 2. Telemetry Caching Layer

To guarantee rapid page loads and prevent performance bottlenecks during real-time queries, system telemetry is heavily cached.

### A. The `system_status` Cache Table
- Schema holds three persistent categories mapped by the composite unique key `(company_id, tab_key)`:
  - `monitoring` (CPU, RAM, disk, Sub Storage maps)
  - `php_settings` (Limits, core options, extensions list)
  - `database` (MySQL global variables, table sizes, indexes)
- Standard audit triggers `trg_system_status_audit_insert|update|delete` log all cache mutations to the `audit_logs` table.

### B. Auto-Seeding & POST Refresh Workflow
- **Auto-Seeding:** If an administrator visits a tab with an empty cache entry, the system automatically initializes a single-tab refresh on GET to guarantee a seamless layout display.
- **POST Refresh-All:** Triggered via the **Refresh** control (🔄) on the toolbar. This performs a CSRF-protected POST routing to `itm_system_status_refresh_all()`.
- **Atomic Upsert:** The refresh collector harvests data for **all** tabs simultaneously and upserts the cache in a single transaction. It preserves the active `?tab=` on redirect and stamps `updated_at` (displayed as **Last refreshed** in the toolbar).

---

## 3. Storage & Directory Parsing (Sub Storage)

The **Monitoring** tab features a specialized **Sub Storage** directory analyzer that parses directories under the repository root to compute real-time disk footprints:

### A. Directory Tree Walking
- Built via `itm_system_status_render_storage_node()`, it maps physical directory segments on-disk to company entities (`companies`, `departments`, `employees`).
- Node weights are aggregated cumulatively — meaning parent directories sum both child folder weights and direct files residing immediately inside that parent path.

### B. System File Exclusions
To maintain audit integrity and isolate user content, the storage walking engine explicitly ignores system-specific metadata, managed placeholders, and developer notes:
- **Ignored list:** `.htaccess` (policy files), `index.html` (directory-listing preventers), and `AGENT_NOTES.md` (agent developer logs) are bypassed via `itm_system_status_is_ignored_storage_file()`.

---

## 4. Multi-Tenant Partitioning & Access Control

- **Administrative Authorization:** The entire System Status directory and its companions (`scripts/system_status_api.php`, `scripts/system_status_phpinfo.php`) are locked under `itm_is_admin()`. Unprivileged users are bounced back to the dashboard or blocked with an HTTP 403.
- **Tenant Isolation:** Cache rows are unique-keyed by `company_id`. To preserve developer ease-of-use before a home company is selected, the system implements a graceful fallback:
  - If `company_id` is missing or `<= 0`, it defaults to company `1` (TechCorp Global).
  - Every fallback triggers an `error_log()` entry containing a unique correlation ID to preserve auditing trails without dumping plaintext session scopes.
  - Telemetry metrics displayed (disk totals, MySQL tables, CPU) remain global and system-wide — only the cache key is partitioned.

---

## 5. UI Architecture and Layout Standards

The interface is completely modular and built without inline style directives to support responsive rendering:

### Grid Breakpoints
- **Mobile (≤575px):** Telemetry tables expand to full-width; Sub Storage tree leaves stack in a single column; meta text wraps dynamically.
- **Tablet (≥768px):** `.ss-metric-span-wide` elements span two grid columns; the PHP extensions list splits into two responsive columns (`.ss-extensions-columns`).
- **Desktop (≥1024px):** `.ss-metric-span-full` elements span three columns; the PHP extensions list utilizes three columns.
- **Database Metrics Table:** Constrained inside `.audit-table-wrap` for horizontal scroll support. Direct column metrics use `.ss-table-num` text-align formatting.

---

## 6. Diagnostic and Testing Runbooks

### Local Verification
To execute the system status verifier and test the complete caching pipeline, Native/Linux loaders, and PowerShell integrations:

```bash
# General CLI verification
php scripts/verify_system_status.php

# On Windows Dunebox/Laragon (using absolute PHP 7.4.33 path):
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/verify_system_status.php
```

### Windows Telemetry Setup
If Windows hardware statistics (CPU/RAM/Disk) display empty cache indicators or errors:
1. Ensure `shell_exec` is enabled in `php.ini`.
2. Confirm the execution policy allows reading the PowerShell wrappers by running the setup script:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1
   ```
3. Test individual PowerShell script outputs:
   ```bash
   php scripts/test_cpu_usage.php
   php scripts/test_ram_usage.php
   ```
