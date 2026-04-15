# IT Management System

A complete IT Asset Management System built with PHP and MySQL, featuring a GitHub Copilot-inspired UI theme with light and dark modes.

## Features

- ✅ Complete CRUD operations across modules
- ✅ GitHub Copilot-inspired light/dark theme
- ✅ Equipment management with photo uploads
- ✅ Printer and workstation tracking
- ✅ Ticket management system
- ✅ Responsive design
- ✅ MySQLi implementation (no PDO)

## Installation

1. Extract the project files into your web root.
2. Import `database.sql` into MySQL.
3. Update database credentials in `config/config.php`.
4. Create an `images/` directory for equipment uploads.
5. Create a `tickets_photos/` directory for ticket uploads.
6. Create a `backups/` directory for backup files.
7. Open `http://localhost/it-management/` in your browser.

## Modules

- Equipment — Manage IT equipment
- Printers — Track printers and supplies
- Workstations — Manage workstations
- Tickets — Support ticket system
- Inventory — Track supplies
- Users — User management
- Departments — Department management
- Employees — Employee tracking
- Companies — Multi-company support

## System Requirements

- PHP 7.4.33
- MySQL 8.0+
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

## Production Deployment Note

- Keep `debug.php` for development/troubleshooting only.
- Before any production release, remove or block access to `debug.php` to avoid exposing sensitive system and database information.

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
