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
| [Explorer](#explorer) | Multi-tenant file storage | `modules/explorer/index.php` |
| [Calendar](#calendar-alerts--events) | Aggregated planner | `modules/calendar/index.php` |
| [Reports Hub](#reports-hub) | Saved views & scheduled reports | `modules/reports/index.php` |
| [Guest booking portal](#guest-booking-portal) | Public hotel booking | `booking/` (no employee session) |
| [Appointments](#appointments) | Self-service IT visit booking | `modules/appointments/index.php` |

## Module list

| Module | Description |
| --- | --- |
| **Equipment** | Manage IT equipment with Switch Port Manager |
| **Equipment type facades** | Filtered equipment views: `is_printer`, `is_workstation`, `is_server`, `is_switch`, and related `modules/is_*/` folders |
| **IDFs** | IDF registry plus rack visualizer — [entry points](#idfs-non-crud-entry-points) |
| **IPAM** | VLANs, IP subnets (CIDR), and IP addresses linked to equipment; includes **Network Discovery** TCP scan under IP Subnets and standalone `modules/network_discovery/` |
| **Rack planner** | Visual rack elevation and component placement |
| **Floor Plans** | Gallery-first file manager; table view via `list_all.php` — [entry points](#floor-plans-entry-points) |
| **Explorer** | Secure multi-tenant file storage (`files/{company_id}/`) — [entry points](#explorer) |
| **Tickets** | Support ticket system |
| **Inventory** | `inventory_categories` and `inventory_items` CRUD modules |
| **Departments** | Department management |
| **Employees** | Employee tracking and login (no separate `modules/users/` folder) |
| **Companies** | Multi-company support |
| **Settings** | System UI, sidebar, backups, and maintenance — [entry points](#settings) |
| **Budgeting** | Annual/Monthly Budgets, Forecasts, Expenses (CRUD modules) |
| **Budget report** | Period comparison report (read-only) — [entry points](#budget-report) |
| **Audit Logs** | Change audit trail |
| **Roles & Permissions** | Manage custom roles, hierarchies, and active counts via a 6-column RBAC matrix |
| **Email Management** | Configure company SMTP, inspect send logs, and manage automated alert rules |
| **Private Contacts** | User-private address book with vault encryption, favorites, and secure share sessions |
| **Passwords** | Encrypted private password manager with folder organization and import/export tools |
| **Request Password** | Handle password reset workflow requiring applicant, HR, HOD, and ISM approvals |
| **Visitors Access Log** | Log manual visitor entries with immutable history rules |
| **Backup Tape Log** | Monthly server backup tracking with weekend highlighting |
| **Ops Report** | Daily hotel operations figures, walk-rounds, and guest feedback sections |
| **Calendar** | Aggregated planner showing events, alerts, ticket deadlines, and equipment expirations |
| **Alerts** | Manage global/private notifications; supports ICS calendar file imports |
| **Chatbot** | Floating IT support widget powered by tenant-scoped Knowledge Base articles |
| **Live Chat** | Agent conversations and waiting-room flows (`modules/live_chat/`, `live_chat_conversations/`) |
| **Appointments** | Self-service IT visit booking with weekly slot modal — [entry points](#appointments) |
| **Problem Management** | Root-cause investigations, known errors, and master ticket rollup (`modules/problems/`) |
| **Saved report views** | Custom filter/column presets from tickets, equipment, and expenses lists |
| **API v2** | Paid-tier JSON REST gateway with scoped integration keys (`modules/api_v2/router.php`) |
| **Hotel booking distribution** | Partner channel API for inventory, ARI, and reservations (`modules/hotel_booking_api/`) |
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

## CAPEX and OPEX reports

**Sidebar:** Budgeting → [CAPEX](http://localhost/it-management/modules/capex/index.php) (`modules/capex/`) and [OPEX](http://localhost/it-management/modules/opex/index.php) (`modules/opex/`).

Same computed rollup as Budget Report, filtered by `budget_categories.category_kind`:

| Module | `category_kind` | Typical seed GL |
| --- | --- | --- |
| CAPEX | `capex` | 7100 Capital IT Equipment |
| OPEX | `opex` | 6100 maintenance, 6200 licensing |

Assign kind on [Budget Categories](http://localhost/it-management/modules/budget_categories/index.php) (column `category_kind`); GL accounts link via `gl_accounts.category_id`. Regression: `php scripts/verify_capex_opex.php`.

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

VLANs, subnets, and IP addresses. **IP Subnets → Search → Network Discovery** scans an IPv4 range (up to 255 addresses) via TCP connect probes. A standalone **Network Discovery** module (`modules/network_discovery/`) also provides profile-based staging. See [Network Discovery & IP2WHOIS](Network-Discovery).

## Equipment type facades

Printer, workstation, server, switch, and related device classes are **filtered views** of the equipment table — not separate modules:

| Facade | Path |
| --- | --- |
| Printers | `modules/is_printer/` |
| Workstations | `modules/is_workstation/` |
| Servers | `modules/is_server/` |
| Switches | `modules/is_switch/` |

See `README.md` for the full facade list (`is_router`, `is_firewall`, `is_access_point`, and others).

## Tickets

Create and manage support tickets, including photo attachments in `tickets_photos/`.

## Inventory

Consumables and stock via **`inventory_categories`** and **`inventory_items`** CRUD modules.

## Departments, employees, companies

Organizational structure, employee records, and multi-company data partitioning (`company_id` scoping). Login and user management live under **`modules/employees/`** (there is no `modules/users/` folder).

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

Configure SMTP profiles (`email_smtp_configurations`), automated alert rules (`email_alert_rules`), and inspect outbound/inbound delivery logs (`emails`). The **`emails`** send log is private-data exempt from audit triggers; SMTP profiles and alert rules **are** audited.

## Private Contacts

A secure, user-scoped contact directory. Private PII fields are vault-encrypted at rest. Supports QR-based temporary secure share links.

## Passwords

A secure credentials manager. Password entries and folder structures are scoped strictly to the owning employee and encrypted at rest using the master vault key.

## Request Password

A multi-stage password reset request workflow requiring Applicant, HR, HOD, and ISM approvals. Integrates signature blocks and alert notifications.

## Visitors Access Log

Tracks manual visitor logging entries with strict historical immutability rules. Records created prior to the current day are locked to preserve audit integrity.

## Backup Tape Log

A monthly grid system to track physical server backup tape usage. Derives days of the week, highlights Sundays, and restricts critical action cells to administrators.

## Ops Report

A daily hotel operations report that aggregates figures, walk-rounds, food & beverage outlets, and guest feedback. Non-admins may edit **today and yesterday** only; dates older than D-2 are read-only unless the actor is an administrator.

## Calendar, Alerts & Events

An integrated planning system. The calendar module aggregates data from alerts, events, ticket due dates, and equipment expirations. Supports importing third-party calendar feeds via ICS files.

## Chatbot

A floating IT support chatbot widget (`js/chatbot.js`) powered by tenant-scoped Knowledge Base articles. Standardizes input escaping and CSRF validation, and escalates to IT contacts on keyword triggers.

## Live Chat

Agent conversations, waiting-room flows, and peer **Chat with** threads (`modules/live_chat/`, `modules/live_chat_conversations/`). Separate from the Knowledge Base chatbot widget.

## Explorer

**Sidebar:** Explorer → `modules/explorer/`

Secure multi-tenant file storage anchored at `files/{company_id}/` with `Common/`, `Departments/{dept_id}/`, `Private/{username}_{employee_id}/`, and `Trash/` segments. Private paths require vault unlock. See `AGENTS.md` Explorer module section for ACL and upload hardening rules.

## Appointments

**Sidebar:** Planning → Appointments → `modules/appointments/`

Employee self-service IT visit booking (visit reason, weekly slot modal, in-person or remote). Configuration lives in `modules/appointment_settings/`. API: `modules/appointments/api.php` (`week_slots`, `schedule`).

## Reports Hub

**Sidebar:** Reports → `modules/reports/`

Aggregated reporting including saved filter/column presets from tickets, equipment, and expenses (`modules/saved_report_views/`). Supports scheduling and temporary public share links.

## Guest booking portal

Public hotel guest booking at `booking/` (no employee session). Separate from employee modules and from the **Hotel booking distribution** partner API (`modules/hotel_booking_api/`).

## License Management

Track software licenses with dedicated fields for license keys, types, suppliers, quantities, prices, and purchase/expiry dates.

## Company Module Access

An administrator matrix allowing modules to be enabled or disabled per company tenant, controlling sidebar and dashboard visibility.

## Bills & Invoices

Accounts payable and accounts receivable workflow modules supporting line items, tax rates, payment allocations, and manual posting to budget expenses.
