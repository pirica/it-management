# Security & Audits

Baseline security scripts and deployment practices (mirrors [README.md](https://github.com/pirica/it-management/blob/master/README.md)).

## Security checks

Run from the repository root with PHP 7.4.33 (MySQLi enabled):

```bash
php scripts/check_csrf_coverage.php
php scripts/check_sql_injection_coverage.php
php scripts/check_fk_label_search_coverage.php
```

Review findings before deploying. Keep PHP and MySQL patched, use least-privilege DB credentials in production, and restrict upload directory permissions with MIME validation.

## Pre-PR CI quartet

Before publishing a branch or opening a pull request, run all four smoke-workflow jobs locally (same commands as GitHub Actions). See `scripts/SCRIPTS.md` → Smoke tests.

```bash
bash scripts/smoke_test.sh
MYSQL_PORT=3306 bash scripts/verify_database_sql_import.sh
php scripts/verify_crud_fk_label_search.php
php scripts/run_tier2_checks.php
ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php
```

On Dunebox (Windows), use `MYSQL_PORT=3307` and the full PHP 7.4.33 binary path from `AGENTS.md`.

## Penetration test report

Canonical assessment: `docs/report.md` (findings ITM-PENTEST-001–022).

Regression verifier:

```bash
php scripts/verify_pentest_report.php
```

Browser (Administrator session): [verify_pentest_report.php?run=1](http://localhost/it-management/scripts/verify_pentest_report.php?run=1)

| Label | Meaning |
|-------|---------|
| `[PASS]` | Remediated finding — fix still in place |
| `[INFO]` | Documented posture or positive control (e.g. default seed credentials with `must_change_password` gate) |
| `[OPEN]` | Regression drift — a formerly remediated fix is missing |
| `[FAIL]` | Report and repository out of sync |

Exit code **0** means every finding still matches `docs/report.md` — not that all risks are closed.

## PHP 7.4.33 compatibility

- The codebase is maintained for PHP 7.4.33.
- After PHP changes: lint touched files (`php -l`) and run the audit scripts above.

## Production deployment note

- Keep `scripts/debug.php` for development/troubleshooting only.
- Block access to `scripts/system_status_phpinfo.php` in production to avoid exposing sensitive system and database information.

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

Move secrets out of source control. `config/config.php` loads database credentials and optional API keys from environment variables (see `.env.example`). Do not commit production values in tracked PHP.

Example env loading (MailerLite / onboarding approval HMAC):

```php
$itm_mailerlite_api_key = trim((string)getenv('MAILERLITE_API_KEY'));
define('MAILERLITE_API_KEY', $itm_mailerlite_api_key);
```

### Environment variables (recommended)

The application reads optional settings from a project-root `.env` file (see `itm_load_dotenv_file()` in `config/config.php`) and from the process environment. Database credentials are loaded into `DB_HOST`, `DB_PORT` (default `3306` when empty), `DB_USER`, `DB_PASS`, and `DB_NAME`, then used by `itm_mysqli_connect()`.

Copy `.env.example` to `.env` and set database keys there (do not commit `.env`). `DB_CONNECTION` is not used.

**Security-related variables:**

| Variable | Purpose |
|----------|---------|
| `ITM_REQUEST_PASSWORD_APPROVAL_SECRET` | HMAC secret for Request Password email approval links |
| `ITM_MAINTENANCE_TOKEN` | Optional token for no-auth maintenance scripts |
| `MAILERLITE_API_KEY` | MailerLite integration (fallback email) |
| `IP2WHOIS_API_KEY` / `ITM_IP2WHOIS_API_KEY` | Network Discovery hosted-domain lookups |

Set in Apache vhost (or systemd/container runtime) when needed:

```apache
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_USER root
SetEnv DB_PASS change_me
SetEnv DB_NAME itmanagement
SetEnv ITM_APP_URL https://itm.example.com/app/
SetEnv ITM_ALLOWED_HOSTS itm.example.com,www.itm.example.com
SetEnv IP2WHOIS_API_KEY your_ip2whois_key
SetEnv ITM_REQUEST_PASSWORD_APPROVAL_SECRET your_long_random_secret
```

Optional IP2WHOIS alias:

```apache
SetEnv ITM_IP2WHOIS_API_KEY your_ip2whois_key
```

`config/config.php` already consumes `DB_*`, `ITM_APP_URL`, `ITM_ALLOWED_HOSTS`, and `IP2WHOIS_API_KEY` / `ITM_IP2WHOIS_API_KEY` for database access, URL hardening, and Network Discovery. CLI scripts may use separate `ITM_DB_*` overrides (see `scripts/idfs_sync_human_test.php`); the web app uses `DB_*` from `.env`.

### Alternative: server-local config file

If environment variables are not available, load a separate PHP config file from outside the repo (or ignored by git), and terminate app startup if the file or required values are missing.

## Related documentation

- [Network Discovery & IP2WHOIS](Network-Discovery) — `IP2WHOIS_API_KEY` / `ITM_IP2WHOIS_API_KEY`
- [Installation](Installation)
- [Foreign Keys & Display](Foreign-Keys) — CSRF and prepared statements in modules
