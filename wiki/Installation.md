# Installation

## System requirements

- PHP 7.4.33
- MySQL 8.0+
- MySQLi extension (no PDO)
- Apache 2.4+
- No Composer required

Fresh import creates **248 tables** from `db/01_schema.sql` (verify with `php scripts/count_db_tables.php` or `php scripts/verify_database_schema.php`).

## Steps

1. Extract the project files into your web root.
2. Import `db/` into MySQL, **or** run `bash scripts/import_database_split.sh` for the generated `db/` split (order `01_schema` → `02_data` → `03_triggers` — see `db/AGENT_NOTES.md`). On Dunebox the import scripts default to **`MYSQL_PORT=3307`**; set `MYSQL_PORT=3306` when MySQL listens on the standard port (Laragon, GitHub Actions CI).
3. Copy `.env.example` to `.env` and set database credentials (see **Configure database connection** below). Prefer `.env` over editing `config/config.php`. For local dev, add **`ITM_DEV=1`** and **`APP_ENV=development`** (see **Application environment profile** below).
4. Create an `images/` directory for equipment uploads.
5. Create a `tickets_photos/` directory for ticket uploads.
6. Create a `backups/` directory for backup files.
7. Create a `floor_plans/` directory for floor plan file uploads (company subfolders are created automatically).
8. Create a `files/` directory for Explorer tenant storage (`files/{company_id}/` with managed `.htaccess` on each segment — see `AGENTS.md` Explorer section).
9. Open [http://localhost/it-management/](http://localhost/it-management/) in your browser.

## Seed logins

After a fresh `db/` import, sign in with a seed admin (password **`Admin`** for all):

| Company | Username |
|---------|----------|
| 1 (TechCorp Global) | `Admin` |
| 2–5 | `Admin2` … `Admin5` |

Seed admins and `demo1`–`demo5` have **`must_change_password = 1`** — first password login redirects to `force-password-change.php`. Optional `.env` `ITM_SKIP_FORCE_PASSWORD_CHANGE=1` for local dev only.

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
- `DB_PORT` — optional; default `3306` when empty. Used when `DB_HOST` has no port suffix. **Match `.env` `DB_PORT` to your MySQL listener.**
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

`DB_CONNECTION` and other Laravel-style keys are **not** read.

### Dunebox (primary Windows dev — Nelson)

| Setting | Value |
|---------|-------|
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3307` |
| `DB_USER` | `root` |
| `DB_PASS` | `secret` |
| `DB_NAME` | `itmanagement` |
| PHP CLI | `D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe` |

### Laragon (alternate)

`DB_HOST=127.0.0.1`, `DB_USER=root`, `DB_PASS=itmanagement`, `DB_NAME=itmanagement` (MySQL on port **3306**).

See `.env.example` for commented examples (including a non-default port).

## Application environment profile

Optional keys **`ITM_DEV`** and **`APP_ENV`** label local vs production deployments. Loaded in `config/config.php` into the PHP constant `APP_ENV` (`development` or `production`; default **production** when unset).

**Local example** (add to `.env` after database keys):

```env
# Local dev profile
ITM_DEV=1
APP_ENV=development
```

Full copy-paste template: `docs/examples/env.development.sample`. Canonical reference: `docs/ENV.md`.

**Verify:** `php -r 'require "config/config.php"; echo APP_ENV, "\n";'` → `development`.

**Does not auto-enable on-screen PHP errors.** Verbose errors still use Settings → UI Configuration → **enable all error reporting** per employee (default off). Use `ITM_DEV` to mark the host as development; toggle error display in Settings when debugging.

Production: `APP_ENV=production` or omit both keys. Do not set `ITM_DEV=1` on production servers.

## Troubleshooting

- Verify DB credentials, `DB_PORT`, and MySQL server status (wrong port often shows “connection refused”).
- Confirm `images/`, `tickets_photos/`, `backups/`, `floor_plans/`, and `files/` are writable by the web server.
- Check PHP and Apache error logs (`error_log.txt` in the project root when enabled).
- Clear browser cache if UI assets appear stale.
- Database analyze issues in phpMyAdmin: see [Database analyze troubleshooting](Security#database-analyze-troubleshooting-phpmyadmin) in Security.
