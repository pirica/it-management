# SSO / LDAP Login

LDAP single sign-on (v1) lets employees authenticate against a company directory instead of a local password hash. **SAML 2.0** is available when `companies.sso_provider = saml` — see `docs/SSO_SAML.md`.

## Scope (v1)

- **Provider:** LDAP bind + search (`companies.sso_provider = ldap`) or SAML 2.0 (`saml`).
- **Provisioning:** match existing `employees` rows; optional **JIT** when `companies.sso_jit_enabled = 1` creates employee + home `employee_companies` grant on first LDAP login.
- **Match order:** `sso_subject` (LDAP DN), then `work_email`, then `username` (tenant-scoped, active employment).
- **Config storage:** encrypted JSON on `companies.sso_config_json_encrypted` via `includes/itm_ldap_auth.php`.

## Schema

| Table | Column | Purpose |
|-------|--------|---------|
| `companies` | `sso_enabled` | Toggle LDAP login for the tenant |
| `companies` | `sso_jit_enabled` | When `1`, create employee on first successful LDAP match miss |
| `companies` | `sso_provider` | Default `ldap` |
| `companies` | `sso_config_json_encrypted` | AES-256-CBC JSON (host, port, bind DN/password, base DN, filter, attribute map) |
| `employees` | `sso_subject` | Stable LDAP DN after first successful login |

Fresh installs: `db/01_schema.sql`. Existing databases: `db/migrations/company_sso.sql` (destructive — back up first).

## Admin configuration

Administrators configure SSO on the company edit form:

- [modules/companies/edit.php](http://localhost/it-management/modules/companies/edit.php) (Admin session — open in a new browser tab)

Fields: enable LDAP SSO, **JIT provision new LDAP users**, host, port, base DN, service bind DN/password, user filter (`%username%` placeholder), username attribute, email attribute.

## User login flow

1. [login.php](http://localhost/it-management/login.php) shows **Sign in with SSO** when any company has `sso_enabled = 1` (or the session company has SSO).
2. Link targets [sso-ldap.php](http://localhost/it-management/sso-ldap.php) or [sso-saml.php](http://localhost/it-management/sso-saml.php) based on `sso_provider`.
3. User submits LDAP username/password; `itm_ldap_auth_attempt()` validates against the directory and matches an employee.
4. On success, `itm_sso_finalize_employee_login_session()` sets the same session keys as password login (`employee_id`, `login_employee_id`, `company_id` via `itm_switch_active_company_session`, etc.).

## Helpers (`includes/itm_ldap_auth.php`)

| Function | Role |
|----------|------|
| `itm_ldap_encrypt_config` / `itm_ldap_decrypt_config` | Config JSON at rest |
| `itm_ldap_auth_attempt` | LDAP connect, bind, search, user bind, employee match |
| `itm_ldap_match_or_provision_employee` | Match existing employee; JIT create when `sso_jit_enabled` |
| `itm_ldap_resolve_jit_default_role_id` / `itm_ldap_resolve_jit_default_access_level_id` | Prefer User/Employee/Staff role and access level over Admin for JIT rows |
| `itm_sso_resolve_company_for_login` | Resolve company from `company_id`, incode, or first SSO-enabled tenant |
| `itm_sso_finalize_employee_login_session` | Session stamping after SSO success |

## Regression

```bash
php scripts/verify_sso_ldap.php
```

Browser catalog: [verify_sso_ldap.php?run=1](http://localhost/it-management/scripts/verify_sso_ldap.php?run=1)

Checks: schema columns, encrypt/decrypt round-trip, `ldap` extension presence (N/A when missing), entry files, JIT provisioning path (`itm_ldap_match_or_provision_employee` with `sso_jit_enabled = 1`).

## Operations notes

- Map directory users to ITM employees before SSO (username or work email alignment).
- After first successful login, `employees.sso_subject` is stored for faster DN-based matching.

## PHP `ldap` extension

**Required for live LDAP SSO:** [sso-ldap.php](http://localhost/it-management/sso-ldap.php) calls `ldap_connect()` / `ldap_bind()` via `itm_ldap_auth_attempt()`. When the extension is missing, users see **LDAP extension is not loaded on this server.** (`includes/itm_ldap_auth.php` → `itm_ldap_extension_available()` probes `function_exists('ldap_connect')`).

**Apache vs CLI:** LDAP login is a **web** request — enable `ldap` in the **`php.ini` used by Apache** (the SAPI that serves `http://localhost/it-management/`), not only the CLI binary used for `php scripts/verify_sso_ldap.php`. On Laragon/Dunebox, Apache and CLI usually share the same PHP 7.4.33 folder; if SSO fails in the browser but `php -m` shows `ldap` in a terminal, check which `php.ini` Apache loads (`phpinfo()` → *Loaded Configuration File*).

| Environment | Enable `ldap` |
|-------------|----------------|
| **Dunebox** | `extension=ldap` in `D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.ini` (canonical template: `scripts/data/php.ini.dunebox-7.4.template`). Copy `php_ldap.dll` into `ext\` from Laragon portable if missing. Re-run `powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1` after template changes. **Restart Apache** after editing `php.ini`. |
| **Laragon portable** | Uncomment or add `extension=ldap` in `bin\php\php-7.4.33-nts-Win32-vc15-x64\php.ini` (same folder as `php.exe`). Confirm `ext\php_ldap.dll` exists. Restart Apache from Laragon. |
| **Linux / CI** | Install `php-ldap` (or enable `extension=ldap` in the distro `php.ini` for the **Apache** SAPI). |

**Verify (PowerShell — use full PHP path on Dunebox):**

```powershell
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" -m | findstr /i ldap
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" -r "echo function_exists('ldap_connect') ? 'ldap ok' : 'ldap missing';"
```

**Regression:** [verify_sso_ldap.php?run=1](http://localhost/it-management/scripts/verify_sso_ldap.php?run=1) reports **N/A** when `ldap` is not loaded (encrypt/decrypt, schema, JIT helpers still run). A **PASS** on the extension probe means the current CLI binary has `ldap`; confirm the **Apache** binary matches before testing [sso-ldap.php](http://localhost/it-management/sso-ldap.php) in a browser.
