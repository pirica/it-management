# Environment variables (`.env`)

Project-root `.env` is optional but recommended for local and production deployments. `config/config.php` loads it via `itm_load_dotenv_file()` before database connection and defines application constants.

**Templates:** copy one of:

| File | Use |
|------|-----|
| `.env.example` | Full catalog with comments |
| `docs/examples/env.development.sample` | Local Laragon / Dunebox |
| `docs/examples/env.production.sample` | Production-style |

**Drift audit:** `php scripts/check_env_vars_in_use.php` — [check_env_vars_in_use.php?run=1](http://localhost/it-management/scripts/check_env_vars_in_use.php?run=1) (Admin session). Run after changing `.env.example` or `getenv()` reads in code.

**HTTP hardening:** root `.htaccess` denies web access to `.env` (`ITM-PENTEST-023` — `php scripts/verify_pentest_report.php`). Do not commit `.env`; restrict file permissions on the server.

**Security code review register:** static findings and **Status** (`OPEN`/`FIXED`/`INFO`) for `display_errors`, `bypass_login.php`, and `.env` HTTP deny — [docs/VERIFY.md](VERIFY.md) (distinct from live pentest [docs/report.md](report.md)).

---

## Database

Loaded into `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` and used by `itm_mysqli_connect()`.

| Variable | Notes |
|----------|--------|
| `DB_HOST` | Hostname or `host:port` (e.g. `127.0.0.1:3307`) |
| `DB_PORT` | Optional; default **3306** when empty. Explicit `DB_PORT` wins when `DB_HOST` has no port suffix |
| `DB_USER` | MySQL user |
| `DB_PASS` | MySQL password |
| `DB_NAME` | Database name (canonical import: `itmanagement`) |

`DB_CONNECTION` and other Laravel-style keys are **not** read.

**Dunebox (Windows):** `DB_PORT=3307`, password often `secret`. **Laragon:** port **3306**, password often `itmanagement`. Match **`DB_PORT`** to your MySQL listener and to `MYSQL_PORT` when running `bash scripts/import_database_split.sh`.

---

## Application environment profile

Labels the deployment for `config/config.php` (PHP constant `APP_ENV`). Does **not** replace per-employee error display settings.

| Variable | Values | Effect |
|----------|--------|--------|
| `APP_ENV` | `development` or `production` | Sets `APP_ENV` constant (invalid values → `production`) |
| `ITM_DEV` | `1`, `true`, `yes`, `on` (case-insensitive) | When `APP_ENV` is **empty**, implies `development` |

When both are unset, default is **`production`**.

### Local development example

Add to your `.env` (same folder as `config/`):

```env
# Local dev profile
ITM_DEV=1
APP_ENV=development
```

Either line is enough for a dev label; using **both** is fine and matches [docs/examples/env.development.sample](examples/env.development.sample).

Verify from the repo root:

```bash
php -r 'require "config/config.php"; echo APP_ENV, "\n";'
```

Expected output: `development`.

### Production example

```env
ITM_DEV=0
APP_ENV=production
```

Or omit both keys (defaults to production). See [docs/examples/env.production.sample](examples/env.production.sample).

### Relationship to PHP error display

**`ITM_DEV` / `APP_ENV` do not enable `display_errors`.**

Verbose PHP errors follow **Settings → UI Configuration → enable all error reporting** per employee (`ui_configuration.enable_all_error_reporting`, default **off**). When enabled, `config/config.php` sets `display_errors=1` and logs to `error_log.txt` under the project root for that user's requests only.

For local troubleshooting:

1. Set `ITM_DEV=1` / `APP_ENV=development` in `.env` (deployment label).
2. Optionally enable **enable all error reporting** in Settings for your Admin user, or read `error_log.txt`.

---

## Other common keys

See `.env.example` for the full list. Frequently used:

| Variable | Purpose |
|----------|---------|
| `ITM_APP_URL` | Canonical public base URL (Host-header hardening) |
| `ITM_ALLOWED_HOSTS` | Allowed `Host` values when `ITM_APP_URL` is empty |
| `ITM_SKIP_FORCE_PASSWORD_CHANGE` | `1` = skip first-login password gate (local dev only) |
| `ITM_REQUEST_PASSWORD_APPROVAL_SECRET` | HMAC for Request Password email approvals |
| `ITM_MAINTENANCE_TOKEN` | Optional token for no-auth maintenance scripts |
| `PHP_EXE` / `MYSQL_EXE` | Windows CLI paths (Dunebox / Laragon) |

Integration keys (`MAILERLITE_API_KEY`, `IP2WHOIS_API_KEY`, `RESEND_API_KEY`, `NVD_API_KEY`, etc.) are documented in `.env.example` and module docs (`docs/EMAIL_MANAGEMENT.md`, Network Discovery in README).

---

## Apache `SetEnv` alternative

Production may set variables in the vhost instead of `.env`:

```apache
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_USER root
SetEnv DB_PASS change_me
SetEnv DB_NAME itmanagement
SetEnv APP_ENV production
SetEnv ITM_APP_URL https://itm.example.com/app/
```

Process environment wins over `.env` when the variable is already set before `itm_load_dotenv_file()` runs.
