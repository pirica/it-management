# SSO / SAML Login

SAML 2.0 single sign-on complements LDAP SSO. Each company selects one provider via `companies.sso_provider` (`ldap` or `saml`).

## Scope

- **SP-initiated login:** HTTP-Redirect AuthnRequest to IdP SSO URL (`sso-saml.php`).
- **ACS:** HTTP-POST response handler (`sso-saml-acs.php`) validates signature (IdP X.509 cert), extracts attributes, matches employee via shared `itm_ldap_match_or_provision_employee()` (JIT when `sso_jit_enabled = 1`).
- **Config storage:** encrypted JSON on `companies.sso_config_json_encrypted` via `includes/itm_saml_auth.php` (same column as LDAP; provider determines encrypt/decrypt helper).

## Schema

| Table | Column | Purpose |
|-------|--------|---------|
| `companies` | `sso_enabled` | Toggle SSO for the tenant |
| `companies` | `sso_provider` | `ldap` (default) or `saml` |
| `companies` | `sso_jit_enabled` | JIT provisioning (LDAP + SAML) |
| `companies` | `sso_config_json_encrypted` | Provider-specific encrypted JSON |
| `employees` | `sso_subject` | Stable IdP subject / NameID after first login |

Fresh installs: `db/01_schema.sql`. Existing databases use company SSO migrations under `db/migrations/`.

## Admin configuration

Administrators configure SAML on the company edit form:

- [modules/companies/edit.php](http://localhost/it-management/modules/companies/edit.php) (Admin session — open in a new browser tab)

Fields: SSO provider **SAML 2.0**, IdP entity ID, IdP SSO URL, IdP X.509 certificate, SP entity ID (defaults to app base URL), username/email attribute URIs. ACS URL is shown read-only (`itm_saml_acs_url()`).

## User login flow

1. [login.php](http://localhost/it-management/login.php) **Sign in with SSO** links to `sso-saml.php` when `sso_provider = saml`, otherwise `sso-ldap.php`.
2. `sso-saml.php` builds AuthnRequest and redirects to IdP.
3. IdP POSTs SAMLResponse to `sso-saml-acs.php`.
4. `itm_saml_auth_attempt()` validates response and matches employee.
5. `itm_sso_finalize_employee_login_session()` stamps session (same as password/LDAP login).

## Helpers (`includes/itm_saml_auth.php`)

| Function | Role |
|----------|------|
| `itm_saml_encrypt_config` / `itm_saml_decrypt_config` | Config JSON at rest |
| `itm_saml_redirect_login_url` | HTTP-Redirect AuthnRequest URL |
| `itm_saml_parse_response` | Decode + verify SAMLResponse XML |
| `itm_saml_auth_attempt` | Full login attempt → employee row |
| `itm_saml_acs_url` | Assertion Consumer Service URL for IdP metadata |

## Regression

`php scripts/verify_sso_ldap.php` includes SAML schema/config checks when `includes/itm_saml_auth.php` is present.

Browser: [verify_sso_ldap.php?run=1](http://localhost/it-management/scripts/verify_sso_ldap.php?run=1)

## Operations notes

- Configure IdP with ACS URL from company edit form and SP entity ID.
- SAML signature verification uses IdP certificate from company config; rotate cert in admin UI when IdP rotates keys.
- LDAP remains available for tenants with `sso_provider = ldap`. See `docs/SSO_LDAP.md`.
