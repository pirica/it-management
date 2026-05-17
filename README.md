# IT Management System

A complete IT Asset Management System built with PHP and MySQL, multi-company support.

## Features

- ✅ Complete CRUD operations across modules
- ✅ GitHub Copilot-inspired light/dark theme
- ✅ Equipment management with photo uploads
- ✅ Printer and workstation tracking
- ✅ Ticket management system
- ✅ Responsive design
- ✅ API

## Screenshots

Captured from a local Laragon-style install at `http://localhost/it-management/` (default light theme after sign-in).

**Dashboard** — tenant overview with quick stats and settings shortcut.

![Dashboard overview](docs/readme/dashboard.png)

**Equipment** — module list with search, sort, and table tools (export / import).

![Equipment module list](docs/readme/equipment.png)

**IDF rack** — visual rack layout with positions, port grid, and linked device management.

![IDF rack view](docs/readme/idf.png)

**Rack planner** — drag-and-drop rack elevation with patch panels, switches, and servers by RU.

![Rack planner](docs/readme/rack_planner.png)

## Architecture

High-level request flow from web entry points through shared core into company-scoped MySQL data and audit logging.

![Architecture overview](docs/readme/architecture.png)

**Database schema** — core table relationships for the company-scoped multi-tenant data model.

![Database schema overview](docs/readme/database-diagram.png)

## Installation

1. Extract the project files into your web root.
2. Import `database.sql` into MySQL.
3. Update database credentials in `config/config.php`.
4. Create an `images/` directory for equipment uploads.
5. Create a `tickets_photos/` directory for ticket uploads.
6. Create a `backups/` directory for backup files.
7. Open `http://localhost/it-management/` in your browser.

## Modules

- Equipment — Manage IT equipment with Switch Port Manager
- IDFs — Rack layout, positions, ports, and cable links
- IPAM — VLANs, IP subnets (CIDR), and IP addresses linked to equipment (includes **Network Discovery** TCP scan under IP Subnets)
- Rack planner — Visual rack elevation and component placement
- Printers — Track printers and supplies
- Workstations — Manage workstations
- Tickets — Support ticket system
- Inventory — Track supplies
- Users — User management
- Departments — Department management
- Employees — Employee tracking
- Companies — Multi-company support
- Budgeting — Annual/Monthly Budgets, Forecasts, Expenses and Reports
- Audit Logs


## System Requirements

- PHP 7.4.33
- MySQL 8.0+
- MySQLi implementation (no PDO)
- Apache 2.4+
- No Composer required

## PHP 7.4.33 Compatibility

- The codebase is maintained to run on PHP 7.4.33.
- Compatibility validation should include:
  - syntax linting all PHP files with a PHP 7.4.33 runtime, and
  - running baseline security audits:
    - `php scripts/check_csrf_coverage.php`
    - `php scripts/check_sql_injection_coverage.php`

## Security Checks

Run these scripts to audit baseline security coverage:

- CSRF handler coverage audit:
  - `php scripts/check_csrf_coverage.php`
- SQL injection static audit:
  - `php scripts/check_sql_injection_coverage.php`

## Database Analyze Troubleshooting (phpMyAdmin)

If phpMyAdmin returns an error when using **Analyze table** at the database level, run:

- `php scripts/analyze_database_health.php`

This helper runs `ANALYZE TABLE` per base table and prints table-specific warnings/errors so you can quickly identify which table is failing.

If a table reports `doesn't exist in engine`, rebuild only that table from `database.sql`:

- `php scripts/repair_table_from_schema.php --table=<table_name>`

Then re-run:

- `php scripts/analyze_database_health.php`

## Production Deployment Note

- Keep `debug.php` for development/troubleshooting only.
- Before any production release, remove or block access to `debug.php` to avoid exposing sensitive system and database information.

## Network Discovery & IP2WHOIS

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

## Secrets Management (Required)

Move secrets out of source control immediately. `config/config.php` currently defines DB credentials and API key constants inline, which is risky for leaks and difficult rotation. Use environment variables (or a server-local config file excluded from git) and fail fast when missing.

```php
define('MAILERLITE_API_KEY', 'YOUR_MAILERLITE_API_KEY_HERE');
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
