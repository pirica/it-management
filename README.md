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

- PHP 8.4+
- MySQL 8.0+
- Apache 2.4+
- No Composer required

## Security Checks

Run these scripts to audit baseline security coverage:

- CSRF handler coverage audit:
  - `php scripts/check_csrf_coverage.php`
- SQL injection static audit:
  - `php scripts/check_sql_injection_coverage.php`
