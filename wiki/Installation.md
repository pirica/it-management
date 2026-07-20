# Installation

## System requirements

- PHP 7.4.33
- MySQL 8.0+
- MySQLi extension (no PDO)
- Apache 2.4+
- No Composer required

## Steps

1. Extract the project files into your web root.
2. Import `database.sql` into MySQL, **or** run `bash scripts/import_database_split.sh` for the generated `db/` split (order `01_schema` → `03_data` → `02_triggers` — see `db/AGENT_NOTES.md`).
3. Update database credentials in `config/config.php` (prefer environment variables — see [Security & Audits](Security#secrets-management-required)).
4. Create an `images/` directory for equipment uploads.
5. Create a `tickets_photos/` directory for ticket uploads.
6. Create a `backups/` directory for backup files.
7. Create a `floor_plans/` directory for floor plan file uploads (company subfolders are created automatically).
8. Open `http://localhost/it-management/` in your browser.

## Existing databases

For an existing database, apply the Floor Plans tables from `database.sql` if they are not already present:

- `floor_plan_folders`
- `floor_plan_tags`
- `floor_plans`
- `floor_plan_item_tags`

See [Floor Plans Gallery](Floor-Plans) for migration behavior when tables are missing.

## PHP 7.4.33 compatibility

The codebase is maintained to run on PHP 7.4.33. Validation should include:

- Syntax linting all touched PHP files with a PHP 7.4.33 runtime (`php -l`)
- Baseline security audits:
  - `php scripts/check_csrf_coverage.php`
  - `php scripts/check_sql_injection_coverage.php`

## Configure database connection

Edit `config/config.php` and set:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

## Troubleshooting

- Verify DB credentials and MySQL server status.
- Confirm `images/`, `tickets_photos/`, `backups/`, and `floor_plans/` are writable by the web server.
- Check PHP and Apache error logs (`error_log.txt` in the project root when enabled).
- Clear browser cache if UI assets appear stale.
- Database analyze issues in phpMyAdmin: see [Database analyze troubleshooting](Security#database-analyze-troubleshooting-phpmyadmin) in Security.
