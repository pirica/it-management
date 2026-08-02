# Modules Overview

## Development guardrails

Module work must follow the wiki guardrails (also in repository `AGENTS.md`):

- [Foreign Keys & Display](Foreign-Keys) — labels, dropdowns, tenant-safe lookups
- [Import Excel (JSON endpoint)](Import-Excel) — `data-itm-db-import-endpoint` and `itm_handle_json_table_import`
- [IDF Synchronization](IDF-Synchronization) — rack/port/equipment table parity

## Standard CRUD vs non-CRUD modules

Most folders under `modules/` follow the flat CRUD pattern: `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, and often `list_all.php`, with company-scoped list/search/export behavior.

The modules below use **custom entry points** instead (or in addition). They are excluded from some smoke audits (`scripts/data/ui_configuration_excluded_modules.txt` and related lists) because they do not expose the standard index-table contract.

| Module | Type | Primary entry |
| --- | --- | --- |
| [Settings](#settings) | Configuration hub | `modules/settings/index.php` |
| [Budget report](#budget-report) | Read-only analytics | `modules/budget_report/index.php` |
| [IDFs](#idfs-non-crud-entry-points) | Rack / port workspace | `modules/idfs/index.php` → `view.php` |
| [Floor Plans](#floor-plans-entry-points) | Gallery + optional table CRUD | `modules/floor_plans/index.php` (gallery) |
| [Knowledge Base](#knowledge-base) | Support articles | `modules/knowledge_base/index.php` |
| [IT Settings](#it-settings) | Chatbot & Contact config | `modules/it_settings/index.php` |

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
| **Roles & Permissions** | Manage custom roles, hierarchies, and active counts via a 6-column RBAC matrix |
| **Email Management** | Configure company SMTP, inspect send logs, and manage automated alert rules |
| **Private Contacts** | User-private address book with UK localization, favorites, and secure share sessions |
| **Passwords** | Encrypted private password manager with folder organization and import/export tools |
| **Request Password** | Handle password reset workflow requiring applicant, HR, HOD, and ISM approvals |
| **Visitors Access Log** | Log manual visitor entries with immutable history rules |
| **Backup Tape Log** | Monthly server backup tracking with weekend highlighting |
| **Ops Report** | Daily hotel operations figures, walk-rounds, and guest feedback sections |
| **Calendar** | Aggregated planner showing events, alerts, ticket deadlines, and equipment expirations |
| **Alerts** | Manage global/private notifications; supports ICS calendar file imports |
| **Chatbot / Live Chat** | Floating tech assistance powered by a multi-tenant Knowledge Base |
| **License Management** | Manage software license keys, types, suppliers, quantities, and expirations |
| **Company Module Access** | Matrix enabling admins to turn specific modules on/off per company |
| **Bills & Invoices** | Financial accounts payable/receivable with manual posting to expenses |

## Equipment

Track IT assets and related details, with support for image uploads and switch port integration.

## Settings

**Sidebar:** ⚙️ Settings → `modules/settings/`

Single-screen module (`modules/settings/index.php` only). No `create.php` / `edit.php` / `view.php` wrappers.

| Area | What it does |
| --- | --- |
| **UI configuration** | Per-company toggles: table actions, + New button, export toolbar, back/save alignment (`ui_configuration` table, via `itm_get_ui_configuration()`) |
| **Sidebar** | Show/hide and reorder sidebar sections/items (`employee_sidebar_preferences`) |
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

IDF metadata can be managed from the list screen, but the main value is the **rack workspace**. This folder uses bespoke rack/port workflows — do not refactor to generic CRUD without an explicit request.

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

### Employee Type (`modules/employee_type/`)

Tenant lookup for `employees.employee_type_id` (`name_type` labels such as **Team member** and **Internship**). Standard CRUD.

### Birthdays (`modules/birthdays/`)

Read-only monthly birthday list from `employees.birthday` with month and employment-status filters.

### Resignations (`modules/resignations/`)

Read-only **Weekly Resignations Report** from `employees.termination_date` with week/month/year selectors, employment-status and employee-type multi-select filters, search, and Excel/PDF export.

## Budgeting

CRUD modules for source data: annual/monthly budgets, forecasts, expenses, GL accounts, cost centers, and categories. For the read-only comparison screen, use [Budget report](#budget-report) (`modules/budget_report/index.php`).

## Knowledge Base

Manage IT support articles, manuals, and troubleshooting guides. These articles are used by the **IT Support Chatbot** to assist users.

## IT Settings

Configure IT department contact information, hours of operation, and escalation procedures. This data is used by the chatbot for escalation and contact queries.

## Audit Logs

Traceable INSERT/UPDATE/DELETE history when audit logging is enabled in Settings.

## Roles & Permissions

Manage tenant role configurations and dynamic RBAC matrices. Administrators can create, edit, and reorder roles, while configuring granular view/add/edit/delete/import/export permissions. The Admin role operates on a system-wide wildcard.

## Email Management

Configure SMTP profiles, track complete outbound/inbound delivery logs, and establish automated notification rules for expiration events (e.g. warranties or software licenses). Logs are private-data exempt from standard audit trail triggers.

## Private Contacts

A secure, user-scoped contact directory with UK-focused localization. Private fields are vault-encrypted. Supports QR-based temporary secure share links.

## Passwords

A secure credentials manager. Password entries and folder structures are scoped strictly to the owning employee and encrypted at rest using the master vault key.

## Request Password

A multi-stage password reset request workflow requiring Applicant, HR, HOD, and ISM approvals. Integrates signature blocks and alert notifications.

## Visitors Access Log

Tracks manual visitor logging entries with strict historical immutability rules. Records created prior to the current day are locked to preserve audit integrity.

## Backup Tape Log

A monthly grid system to track physical server backup tape usage. Derives days of the week, highlights Sundays, and restricts critical action cells to administrators.

## Ops Report

A daily hotel operations report that aggregates figures, walk-rounds, food & beverage outlets, and guest feedback. Restricts editing on legacy reports (D-2) to administrators.

## Calendar, Alerts & Events

An integrated planning system. The calendar module aggregates data from alerts, events, ticket due dates, and equipment expirations. Supports importing third-party calendar feeds via ICS files.

## Chatbot & Knowledge Base

A floating IT support chatbot widget powered by tenant-scoped knowledge base articles. Standardizes input escaping and CSRF validation, and escalates to IT contacts on keyword triggers.

## License Management

Track software licenses with dedicated fields for license keys, types, suppliers, quantities, prices, and purchase/expiry dates.

## Company Module Access

An administrator matrix allowing modules to be enabled or disabled per company tenant, controlling sidebar and dashboard visibility.

## Bills & Invoices

Accounts payable and accounts receivable workflow modules supporting line items, tax rates, payment allocations, and manual posting to budget expenses.
