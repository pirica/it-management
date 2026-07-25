# Installation

## System requirements

- PHP 7.4.33
- MySQL 8.0+
- MySQLi extension (no PDO)
- Apache 2.4+
- No Composer required

## Steps

1. Extract the project files into your web root.
2. Import `db/` into MySQL, **or** run `bash scripts/import_database_split.sh` for the generated `db/` split (order `01_schema` → `02_data` → `03_triggers` — see `db/AGENT_NOTES.md`).
3. Copy `.env.example` to `.env` and set database credentials (see **Configure database connection** below). Prefer `.env` over editing `config/config.php`.
4. Create an `images/` directory for equipment uploads.
5. Create a `tickets_photos/` directory for ticket uploads.
6. Create a `backups/` directory for backup files.
7. Create a `floor_plans/` directory for floor plan file uploads (company subfolders are created automatically).
8. Open `http://localhost/it-management/` in your browser.

## Existing databases

For an existing database, apply the Floor Plans tables from `db/01_schema.sql` if they are not already present:

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

Copy `.env.example` to `.env` in the project root (same folder as `config/`). `config/config.php` loads it via `itm_load_dotenv_file()` and connects with `itm_mysqli_connect()` in `includes/bootstrap_helpers.php`.

Set:

- `DB_HOST` — hostname or `host:port` (e.g. `127.0.0.1:3307`)
- `DB_PORT` — optional; default `3306` when empty. Used when `DB_HOST` has no port suffix.
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

`DB_CONNECTION` and other Laravel-style keys are **not** read.

**Laragon default:** `DB_HOST=127.0.0.1`, `DB_USER=root`, `DB_PASS=itmanagement`, `DB_NAME=itmanagement` (MySQL on port 3306).

See `.env.example` for commented examples (including a non-default port).

## Troubleshooting

- Verify DB credentials, `DB_PORT`, and MySQL server status (wrong port often shows “connection refused”).
- Confirm `images/`, `tickets_photos/`, `backups/`, and `floor_plans/` are writable by the web server.
- Check PHP and Apache error logs (`error_log.txt` in the project root when enabled).
- Clear browser cache if UI assets appear stale.
- Database analyze issues in phpMyAdmin: see [Database analyze troubleshooting](Security#database-analyze-troubleshooting-phpmyadmin) in Security.
