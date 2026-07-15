# AGENT_NOTES.md - System Status Tabs

## 1. Module Purpose
Contains the HTML/PHP partial templates representing the sub-panels (Monitoring, PHP Settings, and Database metrics) of the administrator diagnostics dashboard.

## 2. Key Tables
- **system_status** — reads and decodes the `payload_json` field to display cached server data on the UI.

## 3. Required Relationships
- Integrates with parent session settings to resolve caching slots per `$company_id`.

## 4. Business Rules (Critical for Agents)
- **Zero Live Calls on GET**: Tab views must render exclusively using the cached `$ssPayload` array. They must never trigger live shell exec, database size, or directory scanning actions during standard page loads.
- **Responsive Layouts**: Templates must strictly adhere to the project-wide CSS guidelines for standard columns and grid classes.

## 5. UI Behavior Requirements
- **database.php** — Lists DB tables, row counts, and sizes using the custom scroll class `.audit-table-wrap`.
- **monitoring.php** — Shows CPU/RAM metrics and provides the folder traversal tree for **Sub Storage**.
- **php_settings.php** — Renders the loaded extensions panel inside responsive columns (`.ss-extensions-columns`).

## 6. API Actions (If Applicable)
- None (handled by parent `index.php` and `scripts/system_status_api.php`).

## 7. File Structure
- **database.php** — Database metrics tab.
- **monitoring.php** — Server health and disk storage tab.
- **php_settings.php** — PHP details and extensions tab.
- **index.html** — Directory listing prevention.

## 8. Multi-Tenant Rules
- Display metrics are system-wide, but the cache row is saved/loaded scoped to the active tenant.

## 9. Audit Logging Requirements
- None on view (writes/refreshes trigger parent audit updates).

## 10. Common Pitfalls
- Writing inline HTML styles inside templates instead of relying on the global variables and utility classes defined in `css/styles.css` and `modules/system_status/index.php`. [Valid]-[2026-07-15]
