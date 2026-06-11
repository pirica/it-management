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

<p align="center"><strong>Private Contacts</strong> — user-scoped contacts with UK localization, photo uploads, and favorites.</p>

<p align="center"><img src="docs/readme/private_contacts.png" alt="Private Contacts module" /></p>

<h2 align="center">Screenshots</h2>

<p align="center">Captured from a local Laragon-style install at <code>http://localhost/it-management/</code> (default light theme after sign-in).</p>

<p align="center"><strong>Dashboard</strong> — tenant overview with quick stats and settings shortcut.</p>

<p align="center"><img src="docs/readme/dashboard.png" alt="Dashboard overview" /></p>

<p align="center"><strong>Equipment</strong> — module list with search, sort, and table tools (export / import).</p>

<p align="center"><img src="docs/readme/equipment.png" alt="Equipment module list" /></p>

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

<h2 align="center">Architecture</h2>

<p align="center">High-level request flow from web entry points through shared core into company-scoped MySQL data and audit logging.</p>

<p align="center"><img src="docs/readme/architecture.png" alt="Architecture overview" /></p>

<p align="center"><strong>Database schema</strong> — core table relationships for the company-scoped multi-tenant data model.</p>

<p align="center"><img src="docs/readme/database-diagram.png" alt="Database schema overview" /></p>

<h2 align="center">API & Examples</h2>

The system includes a variety of JSON and HTML-based endpoints for integration. To help developers get started, a collection of standalone PHP scripts is available in the `api-examples/` directory:

- **Authentication:** Examples for capturing `PHPSESSID` and `csrf_token` via simulated login.
- **Bulk Import:** How to use the `import_excel_rows` API for multiple modules.
- **CRUD Operations:** Scripts for editing, viewing, deleting, and archiving records.
- **List Filtering:** Examples of fetching and parsing filtered results (e.g., Open tickets).

Full API documentation is available in the `scripts/api.php` file (viewable in the browser as <code>/scripts/api.php</code>).

<h2 align="center">Installation</h2>

1. Extract the project files into your web root.
2. Import `database.sql` into MySQL.
3. Update database credentials in `config/config.php`.
4. Create an `images/` directory for equipment uploads.
5. Create a `tickets_photos/` directory for ticket uploads.
6. Create a `backups/` directory for backup files.
7. Create a `floor_plans/` directory for floor plan file uploads (company subfolders are created automatically).
8. Open `http://localhost/it-management/` in your browser.

For an existing database, apply the Floor Plans tables from `database.sql` (`floor_plan_folders`, `floor_plan_tags`, `floor_plans`, `floor_plan_item_tags`) if they are not already present.

<h2 align="center">Modules</h2>


- Equipment — Manage IT equipment with Switch Port Manager
- IDFs — Rack layout, positions, ports, and cable links
- IPAM — VLANs, IP subnets (CIDR), and IP addresses linked to equipment (includes **Network Discovery** TCP scan under IP Subnets)
- Rack planner — Visual rack elevation and component placement
- Floor Plans — Image/PDF/CAD gallery with nested folders, tags, optional IT Location link, and drag-and-drop moves ([details](#floor-plans-gallery))
- Explorer — Advanced web-based file explorer with Common, Department, and Private storage areas
- Printers — Track printers and supplies
- Workstations — Manage workstations
- Tickets — Support ticket system
- Inventory — Track supplies
- Users — User management
- Departments — Department management
- Contacts — Resume of all contacts with inline editing
- Private Contacts — Private, user-scoped contact management with photo uploads and detailed fields
- Employees — Employee tracking with hierarchy and reporting lines
- Org Chart — Visual, interactive organizational structure diagram
- Companies — Multi-company support
- Budgeting — Annual/Monthly Budgets, Forecasts, Expenses and Reports
- Planning — Shared Calendar and Events management
- Visitors Access Log — Track manual entry logs of visitors with inline editing and auto-timestamps
- Backup Tape Log — Monthly grid view to track server backup tapes with auto-populated day/tape names and restricted ISM review.
- Audit Logs — Essential for compliance and debugging
- Passwords — Secure private password manager with vault encryption, folder hierarchy, and Password generator with csv and others import/export
- Bookmarks — Shared Bookmarks and private, with csv and others import/export    
- Alerts — Manage global and private alerts with ICS import support





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

**Setup:** import the Floor Plans section from `database.sql` on existing databases. If tables are missing, the gallery shows an explicit migration message instead of a generic company error.

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

If a table reports `doesn't exist in engine`, rebuild only that table from `database.sql`:

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
