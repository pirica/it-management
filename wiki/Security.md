# Security & Audits

Baseline security scripts and deployment practices (mirrors [README.md](../README.md)).

## Security checks

Run from the repository root with PHP 7.4.33 (MySQLi enabled):

```bash
php scripts/check_csrf_coverage.php
php scripts/check_sql_injection_coverage.php
```

Review findings before deploying. Keep PHP and MySQL patched, use least-privilege DB credentials in production, and restrict upload directory permissions with MIME validation.

## PHP 7.4.33 compatibility

- The codebase is maintained for PHP 7.4.33.
- After PHP changes: lint touched files (`php -l`) and run both audit scripts above.

## Production deployment note

- Keep `debug.php` for development/troubleshooting only.
- Before any production release, remove or block access to `debug.php` to avoid exposing sensitive system and database information.

## Database analyze troubleshooting (phpMyAdmin)

If phpMyAdmin returns an error when using **Analyze table** at the database level, run:

```bash
php scripts/analyze_database_health.php
```

This helper runs `ANALYZE TABLE` per base table and prints table-specific warnings/errors.

If a table reports `doesn't exist in engine`, rebuild only that table from `db/01_schema.sql` or extract DDL from `db/01_schema.sql`:

```bash
php scripts/repair_table_from_schema.php --table=<table_name>
```

Then re-run:

```bash
php scripts/analyze_database_health.php
```

## Secrets management (required)

Move secrets out of source control. `config/config.php` may define DB credentials and API key constants inline, which is risky for leaks and rotation. Use environment variables (or a server-local config file excluded from git) and fail fast when required values are missing.

Example inline constant pattern (replace with env loading in production):

```php
define('MAILERLITE_API_KEY', 'YOUR_MAILERLITE_API_KEY_HERE');
```

### Environment variables (recommended)

The application reads optional settings from a project-root `.env` file (see `itm_load_dotenv_file()` in `config/config.php`) and from the process environment. Database credentials are still defined as constants in `config/config.php` unless you customize that file for your deployment.

Set in Apache vhost (or systemd/container runtime) when needed:

```apache
SetEnv ITM_APP_URL https://itm.example.com/app/
SetEnv ITM_ALLOWED_HOSTS itm.example.com,www.itm.example.com
SetEnv IP2WHOIS_API_KEY your_ip2whois_key
```

Optional IP2WHOIS alias:

```apache
SetEnv ITM_IP2WHOIS_API_KEY your_ip2whois_key
```

`config/config.php` already consumes `ITM_APP_URL`, `ITM_ALLOWED_HOSTS`, and `IP2WHOIS_API_KEY` / `ITM_IP2WHOIS_API_KEY` for URL hardening and Network Discovery. There is no `ITM_API_KEY` or `ITM_DB_*` loader in the current runtime—do not document or require those names unless you add matching code first.

### Alternative: server-local config file

If environment variables are not available, load a separate PHP config file from outside the repo (or ignored by git), and terminate app startup if the file or required values are missing.

## Related documentation

- [Network Discovery & IP2WHOIS](Network-Discovery) — `IP2WHOIS_API_KEY` / `ITM_IP2WHOIS_API_KEY`
- [Installation](Installation)
- [Foreign Keys & Display](Foreign-Keys) — CSRF and prepared statements in modules
