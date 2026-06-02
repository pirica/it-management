# Modules Overview

## Development guardrails

Module work must follow the wiki guardrails (also in repository `AGENTS.md`):

- [Foreign Keys & Display](Foreign-Keys) — labels, dropdowns, tenant-safe lookups
- [Import Excel (JSON endpoint)](Import-Excel) — `data-itm-db-import-endpoint` and `itm_handle_json_table_import`
- [IDF Synchronization](IDF-Synchronization) — rack/port/equipment table parity (protection zone)

## Standard CRUD vs non-CRUD modules

Most folders under `modules/` follow the flat CRUD pattern: `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, and often `list_all.php`, with company-scoped list/search/export behavior.

The modules below use **custom entry points** instead (or in addition). They are excluded from some smoke audits (`scripts/data/ui_configuration_excluded_modules.txt` and related lists) because they do not expose the standard index-table contract.

| Module | Type | Primary entry |
| --- | --- | --- |
| [Settings](#settings) | Configuration hub | `modules/settings/index.php` |
| [Budget report](#budget-report) | Read-only analytics | `modules/budget_report/index.php` |
| [IDFs](#idfs-non-crud-entry-points) | Rack / port workspace | `modules/idfs/index.php` → `view.php` |
| [Floor Plans](#floor-plans-entry-points) | Gallery + optional table CRUD | `modules/floor_plans/index.php` (gallery) |

## Module list

| Module | Description |
| --- | --- |
| **Equipment** | Manage IT equipment with Switch Port Manager |
| **IDFs** | IDF registry plus rack visualizer — [entry points](#idfs-non-crud-entry-points) |
| **IPAM** | VLANs, IP subnets (CIDR), and IP addresses linked to equipment; includes **Network Discovery** TCP scan under IP Subnets |
| **Rack planner** | Visual rack elevation and component placement |
| **Floor Plans** | Gallery-first file manager; table view via `list_all.php` — [entry points](#floor-plans-entry-points) |
| **Printers** | Track printers and supplies |
| **Workstations** | Manage workstations |
| **Tickets** | Support ticket system |
| **Inventory** | Track supplies |
| **Users** | User management |
| **Departments** | Department management |
| **Employees** | Employee tracking |
| **Companies** | Multi-company support |
| **Settings** | System UI, sidebar, backups, and maintenance — [entry points](#settings) |
| **Budgeting** | Annual/Monthly Budgets, Forecasts, Expenses (CRUD modules) |
| **Budget report** | Period comparison report (read-only) — [entry points](#budget-report) |
| **Audit Logs** | Change audit trail |

## Equipment

Track IT assets and related details, with support for image uploads and switch port integration.

## Settings

**Sidebar:** ⚙️ Settings → `modules/settings/`

Single-screen module (`modules/settings/index.php` only). No `create.php` / `edit.php` / `view.php` wrappers.

| Area | What it does |
| --- | --- |
| **UI configuration** | Per-company toggles: table actions, + New button, export toolbar, back/save alignment (`ui_configuration` table, via `itm_get_ui_configuration()`) |
| **Sidebar** | Show/hide and reorder sidebar sections/items (`user_sidebar_preferences`) |
| **Equipment types** | Edit display emoji for equipment types (tenant-scoped) |
| **Database maintenance** | Verify/create system tables and columns from schema helpers |
| **Backup & restore** | Create, download, delete, and import SQL dumps under `backups/` (role-restricted) |

Audit logging and error-reporting defaults for the app are also controlled from here when those fields are enabled for the tenant.

## Budget report

**Sidebar:** 📑 Budget Report → `modules/budget_report/`

Read-only finance summary (`modules/budget_report/index.php` only). It aggregates **budget**, **forecast**, and **expense** source tables; it is not a CRUD table module.

| Control | Purpose |
| --- | --- |
| `year` | Report year (GET) |
| `month` | `0` = full year; `1`–`12` = single month mode |
| `cost_center_id` | Optional filter |
| `gl_account_id` | Optional filter |

The screen compares current period totals to the previous month and the same month in the prior year. **Import Excel** is intentionally rejected (JSON response explains that the view is computed, not a direct table import).

Related CRUD modules for maintaining source data: `annual_budgets`, `monthly_budgets`, `forecast_revisions`, `expenses`, `gl_accounts`, `cost_centers`, `budget_categories`.

## IDFs (non-CRUD entry points)

**Sidebar:** 🗄️ IDFs → `modules/idfs/`

IDF metadata can be managed from the list screen, but the main value is the **rack workspace**. This folder is a **protection zone** in `AGENTS.md` — do not refactor to generic CRUD without an explicit request.

| Entry | URL / path | Role |
| --- | --- | --- |
| **IDF list** | `modules/idfs/index.php` | Search/sort IDFs; create/edit IDF records; open rack |
| **Rack visualizer** | `modules/idfs/view.php?id={idf_id}` | Positions, port grid, links, move/copy/delete (primary UI) |
| **Device / cable flows** | `modules/idfs/device.php` | Port editing, create cable link, linked equipment context |
| **JSON APIs** | `modules/idfs/api/*.php` | POST/AJAX for positions, ports, links, regen (used by visualizer) |

Typical flow: list → **View** (🔎) → `view.php` → open position/device modals → APIs persist changes under [IDF Synchronization](IDF-Synchronization) rules.

**Rack planner** (`modules/rack_planner/`) is a separate standard CRUD module for planning rack *layouts*; **IDFs** are live infrastructure records tied to equipment and switch ports.

## Floor Plans entry points

**Sidebar:** Reference Data → 🗺️ Floor Plans → `modules/floor_plans/`

Hybrid module: data lives in `floor_plans` (and related folder/tag tables), but the **default UX is a gallery**, not a classic index table.

| Entry | File | Role |
| --- | --- | --- |
| **Gallery (default)** | `index.php` → `gallery_index_view.php` | Folders, tags, drag-and-drop moves, upload |
| **Table / export view** | `list_all.php` → `index.php` (`$crud_action = list_all`) | Standard sortable table, bulk actions, 📗/📄/📥 tools |
| **Upload** | `create.php` → `create_upload_view.php` | New file upload form |
| **Edit metadata** | `edit.php` → `edit_form_view.php` | Rename, tags, IT Location link |
| **Preview / detail** | `view.php` → `view_detail.php` | File preview and metadata |
| **Delete** | `delete.php` | POST delete handler (returns to gallery or list) |

Storage path: `floor_plans/{company_id}/`. Full behavior: [Floor Plans Gallery](Floor-Plans).

Wrappers (`create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) set `$crud_action` and `require 'index.php'` — same pattern as other modules, but `index` renders the gallery unless `list_all` is selected.

## IPAM & network discovery

VLANs, subnets, and IP addresses. **IP Subnets → Search → Network Discovery** scans an IPv4 range (up to 255 addresses) via TCP connect probes. See [Network Discovery & IP2WHOIS](Network-Discovery).

## Printers

Manage printer inventory and supply status.

## Workstations

Track workstation records and assignments.

## Tickets

Create and manage support tickets, including photo attachments in `tickets_photos/`.

## Inventory

Track consumables and stock levels.

## Users, departments, employees, companies

User access, organizational structure, employee records, and multi-company data partitioning (`company_id` scoping).

## Budgeting

CRUD modules for source data: annual/monthly budgets, forecasts, expenses, GL accounts, cost centers, and categories. For the read-only comparison screen, use [Budget report](#budget-report) (`modules/budget_report/index.php`).

## Audit Logs

Traceable INSERT/UPDATE/DELETE history when audit logging is enabled in Settings.
