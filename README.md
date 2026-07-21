<h1 align="center"><a href="https://github.com/pirica/it-management">IT Management System</a></h1>

<p align="center">A complete IT Asset Management System built with PHP and MySQL, multi-company support.</p>

<h2 align="center">Features</h2>

- ✅ Complete CRUD operations across modules
- ✅ GitHub Copilot-inspired light/dark theme
- ✅ Equipment management with photo uploads
- ✅ Printer and workstation tracking
- ✅ Ticket management system
- ✅ Floor Plans gallery (nested folders, tags, image/PDF/CAD uploads)
- ✅ Divisional Organizational Structure (Org Chart) with drag-and-drop
- ✅ Responsive design
- ✅ Bookmarks management (Private/Shared, Folders tree, Drag-and-drop)
- ✅ Private Contacts (User-scoped, UK localization, multi-field CRUD)
- ✅ API (with comprehensive PHP examples)
- ✅ Alerts — Global and private alert management
- ✅ Email Management — SMTP profiles, send logs, and automated expiry alert rules
- ✅ Roles & Permissions — dual-pane role sidebar and six-column RBAC matrix (View, Add, Edit, Delete, Import, Export)
- ✅ System Status — Real-time server monitoring (CPU, RAM, Disk, PHP, MySQL)
- ✅ Employee Type lookup and Weekly Resignations report (from `employees.termination_date`)
- ✅ Bulk Import — Centralized Excel/CSV import for Assets and Employees
- ✅ IT Support Chatbot — Automated technical assistance powered by a multi-tenant Knowledge Base
<!-- [<img src="docs/readme/org_chart.png" width="20" alt="Org Chart" />](docs/readme/org_chart.png) -->
<h2 align="center">Login</h2>

<p align="center"><strong>Login</strong> — sign in with username and password; CSRF-protected form with light/dark toggle.</p>

<p align="center"><img src="docs/readme/demo_login.png" alt="Login page" /></p>

<p align="center"><strong>Dashboard</strong> — company-scoped overview with quick stats (equipment, tickets, employees).</p>

<p align="center"><img src="docs/readme/demo_dashboard.png" alt="Dashboard with TechCorp Global" /></p>

<p align="center"><strong>Departments</strong> — full CRUD list with search, sort, pagination, bulk actions, and export tools.</p>

<p align="center"><img src="docs/readme/demo_departments.png" alt="Departments module list" /></p>

<p align="center"><strong>Equipment</strong> — asset tracking with type, manufacturer, location, and status columns.</p>

<p align="center"><img src="docs/readme/demo_equipment.png" alt="Equipment module list" /></p>

<p align="center"><strong>License Management</strong> — software licenses with type, supplier, quantity, purchase/expiry dates, and price.</p>

<p align="center"><img src="docs/readme/demo_license_management.png" alt="License Management module list" /></p>

<p align="center"><strong>Email Management</strong> — send logs, SMTP configurations with default transport, and automated alert rules.</p>

<p align="center"><img src="docs/readme/demo_emails.png" alt="Email Management module" /></p>

<p align="center"><strong>Roles & Permissions</strong> — role sidebar with hierarchy order and a six-column permission matrix for tenant RBAC (admins edit; other signed-in users browse read-only).</p>

<p align="center"><img src="docs/readme/roles_permissions.png" alt="Roles and Permissions dashboard" /></p>

<p align="center"><strong>Private Contacts</strong> — user-scoped contacts with UK localization, photo uploads, and favorites.</p>

<p align="center"><img src="docs/readme/private_contacts.png" alt="Private Contacts module" /></p>

<h2 align="center">Screenshots</h2>

<p align="center">Captured from a local Laragon-style install at <code>http://localhost/it-management/</code> (default light theme after sign-in).</p>

<p align="center"><strong>Dashboard</strong> — tenant overview with quick stats and settings shortcut.</p>

<p align="center"><img src="docs/readme/dashboard.png" alt="Dashboard overview" /></p>

<p align="center"><strong>Equipment</strong> — module list with search, sort, and table tools (export / import).</p>

<p align="center"><img src="docs/readme/equipment.png" alt="Equipment module list" /></p>

<p align="center"><strong>License Management</strong> — tenant-scoped license records with Type and Supplier labels, dd/mm/yyyy dates, and standard CRUD tools.</p>

<p align="center"><img src="docs/readme/license_management.png" alt="License Management module list" /></p>

<p align="center"><strong>Private Contacts</strong> — user-scoped contacts with UK localization, photo uploads, and favorites.</p>

<p align="center"><img src="docs/readme/private_contacts.png" alt="Private Contacts module" /></p>

<p align="center"><strong>IDF rack</strong> — visual rack layout with positions, port grid, and linked device management.</p>

<p align="center"><img src="docs/readme/idf.png" alt="IDF rack view" /></p>

<p align="center"><strong>Rack planner</strong> — drag-and-drop rack elevation with patch panels, switches, and servers by RU. Add from list or from Equipments & Catalogs.</p>

<p align="center"><img src="docs/readme/rack_planner.png" alt="Rack planner" /></p>

<p align="center"><strong>Floor Plans</strong> — gallery with nested folders, tags, and uploads (images, PDF, AutoCAD); optional link to IT Locations; drag-and-drop to move files and folders (see <a href="#floor-plans-gallery">Floor Plans gallery</a>).</p>

<p align="center"><img src="docs/readme/floor_plans.png" alt="Floor Plans gallery" /></p>

<p align="center"><strong>Org Chart</strong> — visual, interactive organizational structure diagram with drag-and-drop reporting lines and manager management.</p>

<p align="center"><img src="docs/readme/org_chart.png" alt="Org Chart" /></p>

<p align="center"><strong>System Status</strong> — Admin-only server dashboard with monitoring gauges, PHP settings, and database metrics.</p>

<p align="center"><img src="docs/readme/system_status.png" alt="System Status monitoring tab" /></p>

<p align="center"><strong>Roles & Permissions</strong> — Admin matrix for role-level CRUD flags (company module access remains the first visibility gate).</p>

<p align="center"><img src="docs/readme/roles_permissions.png" alt="Roles and Permissions dashboard" /></p>

<p align="center"><strong>Reports Hub</strong> — visual dashboard with key metrics for assets, tickets, HR, and network devices.</p>

<p align="center"><img src="docs/readme/reports_hub.png" alt="Reports Hub dashboard" /></p>

<p align="center"><strong>Request Password</strong> — user password reset requests with multi-stage approval workflow (HR, HOD, and ISM).</p>

<p align="center"><img src="docs/readme/request_password.png" alt="Request Password module list" /></p>

<p align="center"><img src="docs/readme/request_password_view.png" alt="Request Password module view" /></p>

<p align="center"><strong>Bulk Import</strong> — Centralized dashboard for importing Assets and Employees via Excel or CSV with template support.</p>

<p align="center"><img src="docs/readme/import_dashboard.png" alt="Bulk Import dashboard" /></p>

<h2 align="center">Architecture</h2>

<p align="center">High-level request flow from web entry points through shared core into company-scoped MySQL data and audit logging.</p>

<p align="center"><img src="docs/readme/architecture.png" alt="Architecture overview" /></p>

<p align="center"><strong>Database schema</strong> — core table relationships for the company-scoped multi-tenant data model.</p>

<p align="center">
  <a href="https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/database-diagram.svg" target="_blank">
    <img src="docs/readme/database-diagram.png" alt="Database schema overview" />
  </a>
</p>


<h2 align="center">Database Structure Overview</h2>

<p align="center">Fresh import of <code>db/</code> split bundle provisions <strong>126 tables</strong> and approximately <strong>3,085 sample rows</strong> (literal seed data plus derived rows such as <code>company_module_access</code> and <code>employee_sidebar_preferences</code>). The schema supports multi-company SaaS, modular feature expansion, and granular access control.</p>

<h3 align="center">High-level summary</h3>

| Metric | Value |
| --- | --- |
| **Tables** | 126 |
| **Sample rows** | ~3,085 (from <code>db/</code> split bundle) |
| **Module folders** | 125 under <code>modules/</code> |
| **Registry entries** | 149 in <code>modules_registry</code> (catalog slugs; not 1:1 with table count) |
| **Company × module matrix** | 745 rows (5 seed companies × registry modules) |
| **Sidebar preferences** | 540 rows (5 companies × 108 default sidebar items) |
| **Functional domains** | 12 (see breakdown below) |

<h3 align="center">Domain breakdown</h3>

<p>Tables are grouped by business domain. Each row lists primary tables; companion reference tables and junction tables are included where they belong to the same workflow.</p>

#### Core system and access control

`companies`, `employees`, `employee_companies`, `employee_roles`, `role_hierarchy`, `role_module_permissions`, `role_assignment_rights`, `system_access`, `company_module_access`, `employee_sidebar_preferences`, `audit_logs`, `registration_invitations`, `modules_registry`, `ui_configuration`, `settings`, `access_levels`

**Purpose:** Identity, RBAC, multi-tenant isolation, per-company module toggles, sidebar layout, audit trail, and system-wide UI configuration.

**Modules:** `companies`, `employees`, `employee_companies`, `employee_roles`, `role_hierarchy`, `role_module_permissions`, `role_assignment_rights`, `system_access`, `company_module_access`, `employee_sidebar_preferences`, `audit_logs`, `registration_invitations`, `modules_registry`, `settings`, `ui_configuration`, `access_levels`

#### Tickets and support workflow

`tickets`, `ticket_categories`, `ticket_priorities`, `ticket_statuses`, `attempts`, `alerts`

**Purpose:** Helpdesk lifecycle with categorisation, priority, status, login-attempt tracking, and global/private alerts (ICS import supported).

**Modules:** `tickets`, `ticket_categories`, `ticket_priorities`, `ticket_statuses`, `attempts`, `alerts`

#### HR and employee management

`employees`, `employee_positions`, `employee_statuses`, `employee_assignment_history`, `employee_onboarding_requests`, `employee_system_access`, `departments`, `assignment_types`

**Purpose:** Employee lifecycle, onboarding, departmental structure, assignment history, and system-access records.

**Modules:** `employees`, `employee_positions`, `employee_statuses`, `employee_assignment_history`, `employee_onboarding_requests`, `employee_system_access`, `departments`, `assignment_types`, `contacts`, `org_chart` (visual hierarchy from `employees.reports_to`), `birthdays` (read-only monthly view from `employees.birthday`)

#### Finance, budgeting, and approvals

`annual_budgets`, `monthly_budgets`, `budget_categories`, `cost_centers`, `gl_accounts`, `forecast_revisions`, `forecast_revisions_status`, `expenses`, `approvals`, `approvals_stage`, `approvers`, `approver_type`

**Purpose:** Budget planning, forecasting, expense tracking, and multi-stage approval workflows for forecast revisions.

**Modules:** `annual_budgets`, `monthly_budgets`, `budget_categories`, `cost_centers`, `gl_accounts`, `forecast_revisions`, `forecast_revisions_status`, `expenses`, `approvals`, `approvals_stage`, `approvers`, `approver_type`, `budget_report` (read-only reporting view)

#### Inventory, assets, and procurement

`equipment`, `equipment_types`, `equipment_statuses`, `equipment_environment`, `manufacturers`, `suppliers`, `supplier_statuses`, `inventory_categories`, `inventory_items`, `warranty_types`, `catalogs`, `license_management`, `license_types`, `patches_updates`, `patches_updates_level`, `patches_updates_status`

**Purpose:** Asset management, procurement catalogues, software license tracking, warranty and patch tracking, and consumable inventory.

**Modules:** `equipment`, `equipment_types`, `equipment_statuses`, `equipment_environment`, `manufacturers`, `suppliers`, `supplier_statuses`, `inventory_categories`, `inventory_items`, `warranty_types`, `catalogs`, `license_management`, `expiring` (read-only dashboard for warranty/certificate/alert end dates), `patches_updates`, `patches_updates_level`, `patches_updates_status` — **`license_types`** is a seed-only lookup (no separate CRUD module)

**Equipment-type facades** (filter views delegating to `equipment`): `is_printer`, `is_workstation`, `is_server`, `is_switch`, `is_router`, `is_firewall`, `is_access_point`, `is_cctv`, `is_phone`, `is_pos`, `is_port_patch_panel`, `is_other`

**Workstation reference data:** `workstation_device_types`, `workstation_modes`, `workstation_office`, `workstation_os_types`, `workstation_os_versions`, `workstation_ram`, `printer_device_types`

#### Networking, cabling, and IPAM

`racks`, `rack_statuses`, `rack_planner`, `idfs`, `idf_device_type`, `idf_links`, `idf_ports`, `idf_positions`, `switch_ports`, `switch_port_types`, `switch_status`, `switch_port_numbering_layout`, `vlans`, `ip_subnets`, `ip_addresses`, `cable_colors`, `equipment_fiber`, `equipment_fiber_count`, `equipment_fiber_patch`, `equipment_fiber_rack`, `equipment_poe`, `equipment_rj45`, `rj45_speed`

**Purpose:** Datacentre and network topology — racks, IDF layouts, switch ports, fibre/RJ45/POE attributes, VLANs, and IP address management (includes **Network Discovery** TCP scan under IP Subnets).

**Modules:** `racks`, `rack_statuses`, `rack_planner`, `idfs`, `idf_device_type`, `idf_links`, `idf_ports`, `idf_positions`, `switch_ports`, `switch_port_types`, `switch_status`, `switch_port_numbering_layout`, `vlans`, `ip_subnets`, `ip_addresses`, `cable_colors`, `equipment_fiber`, `equipment_fiber_count`, `equipment_fiber_patch`, `equipment_fiber_rack`, `equipment_poe`, `equipment_rj45`, `rj45_speed`

#### Floor plans and location mapping

`floor_plans`, `floor_plan_folders`, `floor_plan_tags`, `floor_plan_item_tags`, `floor_designer`, `floor_designer_points`, `it_locations`, `location_types`

**Purpose:** Visual mapping of physical spaces, nested drawing galleries, interactive floor designer points, and IT location hierarchy.

**Modules:** `floor_plans` (gallery UI for folders/tags/files), `floor_designer`, `floor_designer_points`, `it_locations`, `location_types` — see [Floor Plans gallery](#floor-plans-gallery)

#### Password vault

`password_entries`, `password_folders`, `password_share_sessions`

**Purpose:** User-private encrypted password vault with folder hierarchy and optional temporary share sessions (no seed rows in a fresh install).

**Modules:** `passwords` (UI for both tables; entries encrypted per user vault key)

#### Notes, bookmarks, and personal productivity

`notes`, `note_labels`, `note_share_sessions`, `bookmarks`, `bookmark_folders`, `bookmark_share_sessions`, `todo`, `todo_categories`, `todo_share_sessions`, `private_contacts`, `private_contact_share_sessions`

**Purpose:** Personal and shared productivity — labelled notes, hierarchical bookmarks, to-do lists, and user-scoped contacts (vault-encrypted PII with optional temporary QR/share links).

**Modules:** `notes`, `note_labels`, `bookmarks`, `bookmark_folders`, `todo`, `todo_categories`, `private_contacts`

#### Planning, calendar, and events

`events`, `event_categories`, `event_share_sessions`

**Purpose:** Company events and categories (vault-encrypted private fields where applicable); the **Calendar** module aggregates alerts, events, ticket due dates, and equipment expiry dates.

**Modules:** `events`, `event_categories`, `calendar` (aggregated read-only view)

#### Operations and file storage

`explorer`, `visitors_access_log`, `backup_tape_log`, `ops_report`

**Purpose:** Multi-tenant file explorer (`files/{company_id}/`), visitor access logging, monthly backup-tape grids, and daily hotel ops reports.

**Modules:** `explorer`, `visitors_access_log`, `backup_tape_log`, `ops_report`

<h3 align="center">Table count overview</h3>

| Category | Tables | Sample rows (approx.) |
| --- | ---: | ---: |
| Core system and access | 16 | ~1,300+ |
| Tickets and workflows | 6 | ~100 |
| HR and employees | 8 | ~70 |
| Finance and approvals | 12 | ~120 |
| Inventory and assets | 14 | ~300 |
| Networking and IPAM | 23 | ~400 |
| Floor plans and locations | 8 | ~60 |
| Password vault | 3 | 0 |
| Notes, bookmarks, productivity | 10 | ~5 |
| Planning and events | 3 | ~10 |
| Operations | 10 | ~15 |
| Workstation reference | 7 | ~280 |
| **Total** | **126** | **~3,075** |

<h3 align="center">What this means</h3>

<p align="center">The database is deliberately modular rather than monolithic. It reflects a full enterprise operations platform — multi-company SaaS, infrastructure and asset management, helpdesk and workflows, budgeting with approvals, user personalisation, and secure password vaulting — not a simple single-table CRUD application.</p>

<p align="center">Every feature module under <code>modules/</code> maps to one or more tables (or read-only aggregates of existing tables). The global catalogue lives in <code>modules_registry</code>; per-tenant visibility is enforced through <code>company_module_access</code> and <code>has_module_access()</code> — see <a href="#company-module-access-management">Company Module Access Management</a>.</p>

<h2 align="center">Company Module Access Management</h2>

<p align="center">Per-company module visibility: administrators enable or disable modules per tenant. Central enforcement runs from <code>config/config.php</code> via <code>has_module_access()</code>; the admin matrix lists every registry row (including hidden and system modules).</p>

<h3 align="center">Architectural map</h3>

<p align="center"><img src="docs/readme/company-module-access-architecture.svg" alt="Company Module Access architectural map: UI surfaces, request bootstrap, and database tables" /></p>

<p align="center">UI entry points (<code>sidebar.php</code>, <code>dashboard.php</code>, <code>modules/company_module_access/</code>) call shared helpers in <code>includes/itm_company_module_access.php</code>. Enforcement gates every <code>modules/*</code> request after login. Data lives in <code>modules_registry</code> (global catalog) and <code>company_module_access</code> (per-company <code>enabled</code> flags). Fresh installs seed all company × module rows from <code>db/</code> split bundle; upgrades can run <code>php scripts/seed_company_module_access.php</code>.</p>

<h2 align="center">API & Examples</h2>

The system includes a variety of JSON and HTML-based endpoints for integration. To help developers get started, a collection of standalone PHP scripts is available in the `api-examples/` directory:

- **Authentication:** Examples for capturing `PHPSESSID` and `csrf_token` via simulated login.
- **Bulk Import:** How to use the `import_excel_rows` API for multiple modules.
- **CRUD Operations:** Scripts for editing, viewing, deleting, and archiving records.
- **List Filtering:** Examples of fetching and parsing filtered results (e.g., Open tickets).

Full API documentation is available in the `scripts/api.php` file (viewable in the browser as <code>/scripts/api.php</code>).

<h2 align="center">Installation</h2>

1. Extract the project files into your web root.
2. Import `db/` into MySQL (or run `bash scripts/import_database_split.sh` for the generated `db/` split — see `db/AGENT_NOTES.md`).
3. Update database credentials in `config/config.php`.
4. Create an `images/` directory for equipment uploads.
5. Create a `tickets_photos/` directory for ticket uploads.
6. Create a `backups/` directory for backup files.
7. Create a `floor_plans/` directory for floor plan file uploads (company subfolders are created automatically).
8. Open `http://localhost/it-management/` in your browser.

For an existing database, apply the Floor Plans tables from `db/01_schema.sql` (`floor_plan_folders`, `floor_plan_tags`, `floor_plans`, `floor_plan_item_tags`) if they are not already present.

<h2 align="center">Modules</h2>

<p align="center">122 module folders under <code>modules/</code> (127 registry entries — some tables share a UI module or are reference-only). Paths follow <code>modules/&lt;slug&gt;/</code>. Standard CRUD modules include <code>index.php</code>, <code>create.php</code>, <code>edit.php</code>, <code>delete.php</code>, <code>view.php</code>, and <code>list_all.php</code>.</p>

<h3 align="center">Core and administration</h3>

| Module | Path | Summary |
| --- | --- | --- |
| Companies | `modules/companies/` | Multi-company tenant management |
| Users | `modules/employees/` | User accounts and authentication |
| Employee Companies | `modules/employee_companies/` | User-to-company membership |
| Employee Roles | `modules/employee_roles/` | Role definitions per company |
| Role Hierarchy | `modules/role_hierarchy/` | Role ordering and inheritance |
| Role Module Permissions | `modules/role_module_permissions/` | Per-role module CRUD rights |
| Role Assignment Rights | `modules/role_assignment_rights/` | Who may assign which roles |
| System Access | `modules/system_access/` | System-level access records |
| Company Module Access | `modules/company_module_access/` | Per-company module enable/disable matrix ([architectural map](#company-module-access-management)) |
| Roles & Permissions | `modules/roles_permissions/` | Role sidebar and six-column RBAC permission matrix |
| Settings | `modules/settings/` | UI configuration, API tier, and global toggles |
| UI Configuration | `modules/ui_configuration/` | Per-user layout and integration settings |
| Access Levels | `modules/access_levels/` | Access-level reference data |
| Modules Registry | `modules/modules_registry/` | Global module catalogue |
| Audit Logs | `modules/audit_logs/` | Compliance and change-history centre |
| Registration Invitations | `modules/registration_invitations/` | Self-service registration invites |
| Employee Sidebar Preferences | `modules/employee_sidebar_preferences/` | Per-user sidebar layout overrides |

<h3 align="center">Assets, equipment, and inventory</h3>

| Module | Path | Summary |
| --- | --- | --- |
| Equipment | `modules/equipment/` | Core asset records with Switch Port Manager |
| Equipment Types / Statuses / Environment | `modules/equipment_types/`, `equipment_statuses/`, `equipment_environment/` | Reference data for assets |
| Catalogs | `modules/catalogs/` | Product catalogue (models, prices, images) for procurement and rack planner |
| Manufacturers / Suppliers | `modules/manufacturers/`, `suppliers/`, `supplier_statuses/` | Vendor reference data |
| License Management | `modules/license_management/` | Software licenses (type, supplier, quantity, dates, price) |
| Inventory | `modules/inventory_categories/`, `inventory_items/` | Consumable and supply tracking |
| Warranty Types | `modules/warranty_types/` | Warranty classification |
| Patches & Updates | `modules/patches_updates/`, `patches_updates_level/`, `patches_updates_status/` | Patch/update tracking on equipment |
| Expiring | `modules/expiring/` | Read-only dashboard for upcoming warranty, certificate, and alert expirations |
| **Type facades** | `modules/is_printer/`, `is_workstation/`, `is_server/`, `is_switch/`, `is_router/`, `is_firewall/`, `is_access_point/`, `is_cctv/`, `is_phone/`, `is_pos/`, `is_port_patch_panel/`, `is_other/` | Filtered equipment views by device class |
| **Workstation refs** | `modules/workstation_device_types/`, `workstation_modes/`, `workstation_office/`, `workstation_os_types/`, `workstation_os_versions/`, `workstation_ram/`, `printer_device_types/` | Workstation and printer attribute lookups |

<h3 align="center">Networking, racks, and IPAM</h3>

| Module | Path | Summary |
| --- | --- | --- |
| IDFs | `modules/idfs/` | Rack layout, positions, ports, and cable links |
| IDF sub-modules | `idf_device_type/`, `idf_links/`, `idf_ports/`, `idf_positions/` | IDF reference and port/link data |
| Racks / Rack Statuses | `modules/racks/`, `rack_statuses/` | Physical rack records |
| Rack Planner | `modules/rack_planner/` | Drag-and-drop rack elevation (syncs prices to catalogs/equipment/IDF positions) |
| Switch Ports | `modules/switch_ports/`, `switch_port_types/`, `switch_status/`, `switch_port_numbering_layout/` | Switch port inventory and status |
| Cabling attributes | `modules/cable_colors/`, `equipment_fiber/`, `equipment_fiber_count/`, `equipment_fiber_patch/`, `equipment_fiber_rack/`, `equipment_poe/`, `equipment_rj45/`, `rj45_speed/` | Fibre, RJ45, POE, and colour reference |
| IPAM | `modules/vlans/`, `ip_subnets/`, `ip_addresses/` | VLANs, CIDR subnets, and IP inventory (**Network Discovery** under IP Subnets) |

<h3 align="center">Floor plans and locations</h3>

| Module | Path | Summary |
| --- | --- | --- |
| Floor Plans | `modules/floor_plans/` | Image/PDF/CAD gallery with nested folders, tags, and drag-and-drop ([details](#floor-plans-gallery)) |
| Floor Designer | `modules/floor_designer/`, `floor_designer_points/` | Interactive floor layout points |
| IT Locations / Location Types | `modules/it_locations/`, `location_types/` | Site and location hierarchy |

<h3 align="center">HR, contacts, and org structure</h3>

| Module | Path | Summary |
| --- | --- | --- |
| Departments | `modules/departments/` | Department management |
| Employees | `modules/employees/` | Employee records, import, and reporting lines |
| Employee refs | `employee_positions/`, `employee_statuses/`, `employee_assignment_history/`, `employee_onboarding_requests/`, `employee_system_access/`, `assignment_types/` | Positions, status, history, onboarding, and access |
| Request Password | `modules/request_password/` | User password reset requests with HR/HOD workflow |
| Contacts | `modules/contacts/` | Company-wide contact directory with inline editing |
| Private Contacts | `modules/private_contacts/` | User-scoped vault-encrypted contacts with photo uploads and temporary QR/share links |
| Org Chart | `modules/org_chart/` | Visual hierarchy with drag-and-drop reporting lines |
| Birthdays | `modules/birthdays/` | Read-only monthly birthday list from employee records |

<h3 align="center">Finance and budgeting</h3>

| Module | Path | Summary |
| --- | --- | --- |
| Annual / Monthly Budgets | `modules/annual_budgets/`, `monthly_budgets/` | Budget planning by period |
| Budget Categories / Cost Centers / GL Accounts | `budget_categories/`, `cost_centers/`, `gl_accounts/` | Financial reference data |
| Forecast Revisions | `forecast_revisions/`, `forecast_revisions_status/` | Forecast versioning |
| Expenses | `modules/expenses/` | Expense tracking |
| Approvals workflow | `approvals/`, `approvals_stage/`, `approvers/`, `approver_type/` | Multi-stage approval for forecast revisions |
| Budget Report | `modules/budget_report/` | Read-only budget reporting view |

<h3 align="center">Tickets, alerts, and operations</h3>

| Module | Path | Summary |
| --- | --- | --- |
| Tickets | `modules/tickets/` | Support ticket system |
| Ticket refs | `ticket_categories/`, `ticket_priorities/`, `ticket_statuses/` | Category, priority, and status lookups |
| Attempts | `modules/attempts/` | Login attempt tracking |
| Alerts | `modules/alerts/` | Global and private alerts with ICS import |
| Visitors Access Log | `modules/visitors_access_log/` | Manual visitor entry logs with immutability rules |
| Backup Tape Log | `modules/backup_tape_log/` | Monthly server backup tape grid |
| Ops Report | `modules/ops_report/` | Daily hotel operations report (figures, F&B, walk-round, guest experience) |
| Bulk Import | `modules/import/` | Centralized Excel/CSV import for Assets and Employees |

<h3 align="center">Productivity, files, and planning</h3>

| Module | Path | Summary |
| --- | --- | --- |
| Explorer | `modules/explorer/` | Multi-tenant file explorer (Common, Departments, Private, Trash) |
| Passwords | `modules/passwords/` | Encrypted private vault with folders and import/export |
| Bookmarks | `modules/bookmarks/`, `bookmark_folders/` | Private/shared links with folder tree and drag-and-drop |
| Notes | `modules/notes/`, `note_labels/` | Labelled personal notes |
| To-Do | `modules/todo/`, `todo_categories/` | Global and private tasks |
| Calendar | `modules/calendar/` | Aggregated view of alerts, events, tickets, and equipment expiries |
| Events | `modules/events/`, `event_categories/` | Company events and categories |
| System Status | `modules/system_status/` | Admin-only server monitoring (CPU, RAM, disk, PHP, MySQL) |

<h2 align="center">Floor Plans gallery</h2>

Reference Data → **Floor Plans** (`modules/floor_plans/`) stores building layouts and drawings per company.

| Capability | Details |
| --- | --- |
| **Files** | Images (JPEG, PNG, GIF, WebP), PDF, AutoCAD (DWG, DXF, DWF, DWS); 20 MB per file |
| **Folders** | Nested folder tree; create, rename, delete (empty only), and **move** into another folder or root |
| **Tags** | Comma-separated tags on upload; shared tag list per company |
| **IT Locations** | Optional nullable link from each file to an IT Location (`it_location_id`) |
| **Moves** | Drag file cards onto folders (or **Unfiled**); drag folders onto another folder or **All files (root)** |
| **Views** | Gallery index (default), table view (`list_all.php`), file detail/preview |
| **Storage** | `floor_plans/{company_id}/` (see `FLOOR_PLAN_*` constants in `config/config.php`) |

**Move folder:** open a folder in the sidebar → **Move folder** → choose **Move into** (target folder or **— Root —**), or drag the folder in the tree. The module blocks moving a folder into itself or its subfolders and rejects duplicate names at the same level.

**Setup:** import the Floor Plans section from `db/01_schema.sql` on existing databases. If tables are missing, the gallery shows an explicit migration message instead of a generic company error.

<h2 align="center">System Requirements</h2>

- PHP 7.4.33
- MySQL 8.0+
- MySQLi implementation (no PDO)
- Apache 2.4+
- 📦 Dependencies:
Zero external dependencies — no composer/npm packages, no CVE exposure.

<h2 align="center">PHP 7.4.33 Compatibility</h2>

- The codebase is maintained to run on PHP 7.4.33.
- Compatibility validation should include:
  - syntax linting all PHP files with a PHP 7.4.33 runtime, and
  - running baseline security audits:
    - `php scripts/check_csrf_coverage.php`
    - `php scripts/check_sql_injection_coverage.php`

<h2 align="center">Security Checks</h2>

Run these scripts to audit baseline security coverage:

- CSRF handler coverage audit:
  - `php scripts/check_csrf_coverage.php`
- SQL injection static audit:
  - `php scripts/check_sql_injection_coverage.php`

<h2 align="center">Database Analyze Troubleshooting (phpMyAdmin)</h2>

If phpMyAdmin returns an error when using **Analyze table** at the database level, run:

- `php scripts/analyze_database_health.php`

This helper runs `ANALYZE TABLE` per base table and prints table-specific warnings/errors so you can quickly identify which table is failing.

If a table reports `doesn't exist in engine`, rebuild only that table from `db/01_schema.sql`:

- `php scripts/repair_table_from_schema.php --table=<table_name>`

Then re-run:

- `php scripts/analyze_database_health.php`

<h2 align="center">Production Deployment Note</h2>

- Keep `scripts/debug.php` for development/troubleshooting only.
- Before any production release, remove or block access to `scripts/debug.php` to avoid exposing sensitive system and database information.

<h2 align="center">Network Discovery & IP2WHOIS</h2>

**IP Subnets** → **Search** → **Network Discovery** scans an IPv4 range (up to 255 addresses) using TCP connect probes (no shell/`exec`). Responding hosts can be added to the **IP Addresses** inventory when they match a company subnet.

### Optional: hosted domains (IP2WHOIS)

When `IP2WHOIS_API_KEY` is configured, each live host is looked up via the IP2WHOIS **Hosted Domains** API:

```bash
curl "https://domains.ip2whois.com/domains?ip=8.8.8.8&key=YOUR_KEY"
```

The scan log and results table show returned domain names. On **Add to inventory**, the first domain may be used as the IP row `hostname` when equipment has no hostname.

### Configure the API key

1. Copy `.env.example` to `.env` in the project root.
2. Set your key (do not commit `.env`):

```env
IP2WHOIS_API_KEY=your_license_key_here
```

3. Restart Apache/PHP so `config/config.php` reloads environment values.

Alternatively set a server environment variable (Apache example):

```apache
SetEnv IP2WHOIS_API_KEY your_license_key_here
```

Alias: `ITM_IP2WHOIS_API_KEY` is also accepted.

If the key is missing, discovery still runs; IP2WHOIS steps are skipped and noted in the activity log.

Register / plans: [IP2WHOIS](https://www.ip2whois.com/register) — free tier limits apply (domains per query depends on plan).

<h2 align="center">Secrets Management (Required)</h2>

Move secrets out of source control immediately. `config/config.php` currently defines DB credentials and API key constants inline, which is risky for leaks and difficult rotation. Use environment variables (or a server-local config file excluded from git) and fail fast when missing.

```php
define('RESEND_API_KEY', 're_xxxxxxxxx');
```

### Example: environment variables (recommended)

Set environment variables in Apache vhost (or systemd/container runtime):

```apache
SetEnv ITM_DB_HOST localhost
SetEnv ITM_DB_NAME itmanagement
SetEnv ITM_DB_USER root
SetEnv ITM_DB_PASS change_me
SetEnv ITM_API_KEY change_me
SetEnv IP2WHOIS_API_KEY your_ip2whois_key
```

Then load and validate them in `config/config.php`:

```php
$itmDbHost = getenv('ITM_DB_HOST') ?: '';
$itmDbName = getenv('ITM_DB_NAME') ?: '';
$itmDbUser = getenv('ITM_DB_USER') ?: '';
$itmDbPass = getenv('ITM_DB_PASS') ?: '';
$itmApiKey = getenv('ITM_API_KEY') ?: '';

if ($itmDbHost === '' || $itmDbName === '' || $itmDbUser === '' || $itmApiKey === '') {
    http_response_code(500);
    exit('Configuration error: required environment variables are missing.');
}
```

### Alternative: server-local config file

If environment variables are not available, load a separate PHP config file from outside the repo (or ignored by git), and terminate the app startup if the file or required values are missing.
