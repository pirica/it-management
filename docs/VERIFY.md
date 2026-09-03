## Scope

Code-only review of the IT Management System (~2,660 PHP files, ~270 module folders, 248 `CREATE TABLE` entries in `db/01_schema.sql`). The **original register** below used static reading of PHP/JS/SQL only — no PHPUnit or `scripts/verify_*` suites as evidence.

**Follow-up code verification (2026-09-03):** each **Status** row in **Security findings (code-based)** was re-checked against the current repository (static source read). `.env` HTTP deny was also checked via `php scripts/verify_pentest_report.php` (`ITM-PENTEST-023`) and optional `curl http://localhost/it-management/.env` → **403** when Apache honors root `.htaccess`. See **Code verification audit (2026-09-03)** below.

---

## Executive summary

This is a large, procedural PHP 7.4 multi-tenant ITSM/asset platform with serious security investment in the central bootstrap (`config/config.php`), layered access control, and several high-risk areas (file storage, public share links, partner APIs) that show deliberate hardening in code.

The dominant engineering risk is **scale + duplication**: scaffold CRUD logic is copied across six entry files per module (~1,000+ lines per `index.php`), while `config/config.php` and `includes/ui_config.php` act as heavy global bootstraps. Security and behavior drift across modules is the main long-term threat—not a single missing prepared statement.

---

## Architecture (from code)

| Layer | Role |
|--------|------|
| `config/config.php` | DB, session, auth gate, CSRF, encryption helpers, loads ~40 `includes/*` on every web request |
| `includes/` | Shared business logic (RBAC, explorer ACL, hotel distribution, vault, API limits, UI config) |
| `modules/<slug>/` | Flat CRUD: `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` |
| `booking/` | Separate guest portal (own bootstrap, session keys) |
| `js/` | Vanilla UI (chatbot, explorer, appointments, webmail, etc.) |

**Request flow:** `config.php` → session + audit MySQL vars → login redirect → company context → `itm_enforce_module_access_or_exit()` → module PHP.

---

## What the code does well

**Authentication & session** (`login.php`, `config/config.php`)

- Prepared statements for credential lookup; employment-status gating.
- Session regeneration after successful login.
- Per-IP and per-identifier login rate limits (`attempts` table).
- Legacy MD5/SHA1 password migration to `password_hash()` on login.
- Session cookies: `HttpOnly`, `SameSite=Lax`, path scoped to app base, optional `Secure`.

**CSRF** (`config/config.php`)

- Session token + double-submit cookie fallback; `hash_equals` validation.
- JSON APIs accept `X-CSRF-Token` (e.g. [chat API](http://localhost/it-management/modules/knowledge_base/chat_api.php)).

**Defense in depth**

1. Company module access (`includes/itm_company_module_access.php`) — opt-out per tenant.
2. RBAC (`includes/itm_role_module_permissions.php`) — per-module CRUD flags; admins bypass.
3. Module-specific gates (explorer ACL, vault, admin-only company admin).

**Explorer** (`modules/explorer/api.php`, `file.php`)

- Path normalization, `..` rejection, segment ACL for Private/Departments.
- Vault gate for private paths; profile-photo cross-company rules in `file.php`.

**Quick-add API** (`modules/select_options_api.php`, `includes/itm_select_options_policy.php`)

- Table whitelist + blocklist; identifier validation; RBAC `create`; CSRF; company module gate.

**Outbound webhook SSRF** (`includes/itm_hotel_booking_distribution_webhooks.php`)

- Blocks localhost/metadata/private IPs; validates URL scheme/host before delivery.

**Short URLs** (`modules/short-url/go.php`, `includes/itm_short_url.php`)

- Rate limit, expiry, password gate, interstitial, destination policy before redirect.

**Upload hardening** (`includes/bootstrap_helpers.php`, `config/config.php`)

- Managed `.htaccess` per policy (`upload`, `deny_http`, `deny_all`); empty `index.html` on segments.

**Security headers** (`includes/itm_security_headers.php`)

- `nosniff`, `SAMEORIGIN`, CSP, HSTS on HTTPS (CSP allows `unsafe-inline` — acknowledged in comments).

---

## Security findings (code-based)

**Status column:** `OPEN` = not remediated in repository at review time; `FIXED` = remediated in code (regression verifier or static check when noted).

### High / should fix

| Issue | Where | Notes | Status |
|--------|--------|--------|--------|
| **Stored / DOM XSS in webmail preview** | `js/webmail-compose.js` assigns `bodyEl.innerHTML = data.body_html` | Any HTML returned by the preview API is rendered unsanitized. Expected for webmail, but malicious inbound mail becomes active XSS in the admin UI. | OPEN |
| **DOM XSS in CMDB impact graph** | `js/itm-cmdb-impact-graph.js` builds `innerHTML` from `n.ci_type_icon`, `n.name`, `n.ci_type_name` without escaping | If CI names/icons are user-controlled, this is XSS on graph views. | OPEN |
| **Tenant error display toggle** | `config/config.php` + `ui_configuration.enable_all_error_reporting` | Per-employee setting can turn on `display_errors` globally for that user’s requests — leaks paths, queries, stack traces in production if mis-set. | OPEN |
| **`.env` not blocked in root `.htaccess`** | `.htaccess` at repo root | Root `.htaccess` now denies HTTP access via `<Files ".env">` + `Require all denied` (Apache 2.4) / `Deny from all` (2.2). Regression: `ITM-PENTEST-023` in `php scripts/verify_pentest_report.php`. | FIXED |

### Medium

| Issue | Where | Notes | Status |
|--------|--------|--------|--------|
| **Free-tier API = unlimited + session** | `includes/itm_api_rate_limit.php` | Authenticated Free tier has no hourly cap; enables scraping/automation at session cost. By design in code, but operationally heavy. | OPEN |
| **Dynamic SQL still widespread** | Scaffold `index.php` (e.g. `modules/departments/index.php`) | Sort columns whitelisted; search uses `mysqli_real_escape_string` + `LIKE` — safer than raw input, but not prepared statements; FK search helpers add more dynamic SQL. | OPEN |
| **Runtime schema migration on config load** | `includes/ui_config.php` `itm_ensure_ui_configuration_table()` | First `itm_get_ui_configuration()` per request can run `SHOW TABLES/COLUMNS` and many `ALTER TABLE` paths — risky on large DBs and blurs DDL vs `db/migrations/`. | OPEN |
| **Vault session key derivation** | `includes/itm_vault_unlock.php` | `$_SESSION['vault_key'] = hash('sha256', $masterKey)` — deterministic from master key; session theft yields decrypt capability until lock. | OPEN |
| **Encryption IV** | `config/config.php` `itm_encrypt()` | Uses `openssl_random_pseudo_bytes` instead of `random_bytes()` (PHP 7.4 has both). | OPEN |
| **Dev session hijack tool** | `scripts/bypass_login.php` | CLI-only, admin-gated, but writes real `sess_*` with vault unlocked — dangerous if session files are readable on shared hosting. | OPEN |

### Low / design tradeoffs

- **CSP `unsafe-inline`** — weakens XSS mitigation; inline scripts everywhere.
- **Legacy login password paths** — MD5/SHA1 verify still accepted once, then rehash (good migration, but extends weak-hash window).
- **`APP_ENV` constant `'production'`** in `config/config.php` — not wired to error display; actual behavior driven by UI flag.
- **Hotel guest login sets `$_SESSION['company_id']`** (`booking/auth/login.php`) — same session namespace as employee app; separate entry points reduce risk, but shared browser profile could confuse tenant context.

### Positive security patterns observed

- `select_options_api.php`: whitelist + RBAC before `DESCRIBE`/`INSERT`.
- `visitors_access_log/index.php`: field whitelist on AJAX inline edit + “today only” mutation guard + `company_id` on reads.
- `companies/view.php`: admin-only; cross-company view intentional for admins.
- `private_contacts/view.php`, `passwords/view.php`: scoped by `employee_id`, not only `company_id`.
- No `include($_GET…)` style dynamic includes found in application PHP.

---

## Code verification audit (2026-09-03)

Static re-read of sources on `origin/master` after the **Status** column and `.env` **FIXED** update. **Verdict** confirms whether the register **Status** still matches code. Regression: [verify_pentest_report.php?run=1](http://localhost/it-management/scripts/verify_pentest_report.php?run=1) (Administrator session) → `[PASS] ITM-PENTEST-023`.

**Summary:** all register **Status** values match the codebase. Three **High** items remain **OPEN** (webmail XSS, CMDB graph XSS, per-user `display_errors`). One **High** item is **FIXED** (root `.env` HTTP deny). Six **Medium** items remain **OPEN** (by design or structural tradeoff).

### High / should fix — verification

| Issue | Status | Code evidence | Verdict |
|--------|--------|---------------|---------|
| **Stored / DOM XSS in webmail preview** | OPEN | `js/webmail-compose.js` line 142: `bodyEl.innerHTML = data.body_html` (from/to use `textContent`; body is raw HTML). | **Confirmed OPEN** |
| **DOM XSS in CMDB impact graph** | OPEN | `js/itm-cmdb-impact-graph.js` lines 119–122: `icon`, `name`, `typeName` concatenated into `innerHTML` without escaping. | **Confirmed OPEN** |
| **Tenant error display toggle** | OPEN | `config/config.php` lines 649–653: when `enable_all_error_reporting === 1`, sets `display_errors` to `1` and logs to `error_log.txt`. Default `0` in `itm_ui_config_defaults()`. | **Confirmed OPEN** (default off; mis-set UI flag still risky) |
| **`.env` not blocked in root `.htaccess`** | FIXED | Root `.htaccess`: `<Files ".env">` + `Require all denied` / `Deny from all`; [verify_pentest_report.php](http://localhost/it-management/scripts/verify_pentest_report.php?run=1) `ITM-PENTEST-023`. | **Confirmed FIXED** |

### Medium — verification

| Issue | Status | Code evidence | Verdict |
|--------|--------|---------------|---------|
| **Free-tier API = unlimited + session** | OPEN | `includes/itm_api_rate_limit.php`: `itm_api_tier_is_unlimited('Free')`; session resolve on Free tier. | **Confirmed OPEN** (by design) |
| **Dynamic SQL still widespread** | OPEN | e.g. `modules/departments/index.php` lines 814–821: `mysqli_real_escape_string` + `LIKE` on whitelisted columns. | **Confirmed OPEN** |
| **Runtime schema migration on config load** | OPEN | `includes/ui_config.php` `itm_ensure_ui_configuration_table()` (~1652): `SHOW TABLES`, `CREATE TABLE IF NOT EXISTS`, per-column `ALTER TABLE` on first load (request-static cache after success). | **Confirmed OPEN** |
| **Vault session key derivation** | OPEN | `includes/itm_vault_unlock.php` line 64: `$_SESSION['vault_key'] = hash('sha256', $masterKey)`. | **Confirmed OPEN** (documented tradeoff) |
| **Encryption IV** | OPEN | `config/config.php` `itm_encrypt()` line 2454: `openssl_random_pseudo_bytes` for IV. | **Confirmed OPEN** |
| **Dev session hijack tool** | OPEN | `scripts/bypass_login.php`: CLI-only exit for non-CLI; `itm_is_admin()` required (~line 79); writes real `sess_*` with vault unlocked. | **Confirmed OPEN** (dev tool; risky if session files readable) |

### Low / design tradeoffs — spot-check

| Item | Verdict |
|------|---------|
| CSP `unsafe-inline` | **Confirmed** — `includes/itm_security_headers.php` lines 114–115. |
| Legacy MD5/SHA1 login | **Confirmed** — `login.php` lines 173–174 before bcrypt rehash. |
| `APP_ENV` not driving errors | **Confirmed** — `config/config.php` line 97 constant `'production'`; errors driven by `enable_all_error_reporting`. |
| Guest portal sets `$_SESSION['company_id']` | **Confirmed** — `booking/auth/login.php` line 28. |

### Positive patterns — spot-check

| Claim | Verdict |
|-------|---------|
| `modules/select_options_api.php` whitelist + RBAC + CSRF | **Confirmed** — `so_require_valid_csrf_token()`, `itm_require_crud_role_module_permission(..., 'create', ...)`. |
| `modules/visitors_access_log/index.php` field whitelist + today guard | **Confirmed** — `$allowedFields`, `val_is_today()`, `company_id` on queries. |
| `modules/companies/view.php` admin-only | **Confirmed** — `itm_is_admin()` at line 10. |
| `modules/private_contacts/view.php` `employee_id` scope | **Confirmed** — `WHERE id = ? AND employee_id = ?`. |
| No `include($_GET…)` in application PHP | **Confirmed** — no matches in `*.php`. |
| `js/chatbot.js` escapes before `innerHTML` | **Confirmed** — `escapeHtml()` then markdown replace. |

### Frontend table cross-check

Matches **Frontend (sampled)** above: webmail and CMDB graph remain **risk**; chatbot and appointment use escaping.

---

## Multi-tenancy & IDOR (sampled from code)

**Good:** Most scaffold `view.php` files use `WHERE id = ? AND company_id = ?` (e.g. bookmarks, equipment-style modules).

**Exceptions (intentional or worth knowing):**

- `modules/companies/view.php` — any company by id, **admin only**.
- `modules/schema_migrations/view.php` — global migration history, no `company_id`.
- `modules/private_contacts/view.php` — `employee_id` scoping (correct for vault data).
- Employee label lookups sometimes use `WHERE id = ?` without `company_id` (e.g. `modules/vault_org_recovery/view.php`) — usually display-only for known FK ids.

**Company switch:** `itm_ensure_company_context_employee_session()` in `config/config.php` remaps admin to tenant seed admin — important for cross-company admin behavior.

---

## Maintainability & quality

**Strengths**

- Clear “why” comments on security-sensitive paths.
- Central helpers for RBAC, explorer paths, CSRF, rate limits, audit session vars.
- mysqlnd fallback in `itm_mysqli_stmt_fetch_assoc()` for hosts without `mysqli_stmt_get_result`.

**Weaknesses**

1. **Massive duplication** — e.g. `modules/workstation_ram/index.php` (~1,071 lines) mirrors `create.php`/`edit.php`/etc.; `modules/departments/index.php` ~1,165 lines. Fixes must be replicated 6× per module.
2. **Fat bootstrap** — `config/config.php` (~2,479 lines) loads hotel distribution, finance, tickets, LDAP, etc. on every request regardless of module.
3. **Mixed SQL style** — prepared statements in auth and many mutations; `mysqli_query` + string building for list/search/count in scaffold modules.
4. **RBAC exemptions** — `itm_crud_rbac_exempt_module_slugs()` lists tickets, equipment, passwords, explorer, etc.; each must maintain its own guards (`modules/tickets/index.php` calls `itm_require_role_module_permission` explicitly).
5. **248 tables** — high coupling; `includes/ui_config.php` alone ~3,576 lines with sidebar discovery and schema ensure logic.

---

## Performance (code paths)

- Per-request: `itm_get_ui_configuration()` static cache helps; first call still may run `itm_ensure_ui_configuration_table()`.
- IPAM migration/backfill can run on first request per session (`config/config.php` lines 617–634).
- List queries: `SELECT *` + PHP pagination; no obvious query result streaming.
- Explorer trash listing uses recursive directory iteration — potential cost on large trees.

---

## Frontend (sampled)

| Area | Assessment |
|------|------------|
| `js/chatbot.js` | `escapeHtml()` before `innerHTML`; markdown `**` after escape — reasonable |
| `js/appointment.js` | Uses `escapeHtml()` for dynamic strings |
| `js/webmail-compose.js` | Raw HTML injection for email body — **risk** |
| `js/itm-cmdb-impact-graph.js` | Unescaped names in `innerHTML` — **risk** |
| `js/company-module-access-matrix.js` | Checkbox HTML with numeric `moduleId` — low risk |

---

## Database layer (from `db/01_schema.sql` footprint)

- 248 tables — broad domain: ITSM, finance, hotel, RBAC, vault, distribution APIs, search index, share sessions, etc.
- Application expects `utf8mb4` (`mysqli_set_charset` in `config.php`).
- Audit via MySQL session variables (`@app_employee_id`, `@app_company_id`) set on each request.
- `itm_run_query()` parses SQL for PHP-side audit logging in addition to triggers — dual audit path.

---

## Prioritized recommendations (code changes only)

1. **Sanitize or sandbox webmail HTML** — CSP sandbox iframe or strict server-side HTML cleaner before preview; don’t assign raw `body_html` to `innerHTML`.
2. **Escape CMDB graph node fields** — use `textContent` or shared `escapeHtml` for `name`, `typeName`, icons in `itm-cmdb-impact-graph.js`.
3. ~~**Deny `.env` at web root**~~ — **FIXED:** root `.htaccess` `<Files ".env">` deny; regression `ITM-PENTEST-023` (`php scripts/verify_pentest_report.php`).
4. **Split error reporting** — never tie `display_errors` to per-user UI config in production; keep logging-only toggle.
5. **Reduce bootstrap weight** — lazy-require hotel/finance/distribution includes only from modules that need them.
6. **Consolidate scaffold CRUD** — without going full MVC: shared `includes/crud_index_logic.php` included by thin `index.php` wrappers to stop 6-copy drift.
7. **Move `ui_configuration` DDL** entirely to `db/migrations/`; remove runtime `ALTER` from `itm_ensure_ui_configuration_table()` after migration coverage.
8. **Replace `openssl_random_pseudo_bytes`** with `random_bytes()` in `itm_encrypt()`.
9. **Audit RBAC-exempt modules** — confirm every slug in `itm_crud_rbac_exempt_module_slugs()` still calls explicit permission checks on all mutating paths.

---

## Manual verification entry points

Open in a **new browser tab** (Admin session unless noted):

| Area | Link |
|------|------|
| Login / rate limit | [login.php](http://localhost/it-management/login.php) |
| Explorer ACL | [modules/explorer/index.php](http://localhost/it-management/modules/explorer/index.php) |
| Select quick-add API (via UI) | Any CRUD with ➕ dropdowns, e.g. [modules/departments/index.php](http://localhost/it-management/modules/departments/index.php) |
| RBAC matrix | [modules/roles_permissions/index.php](http://localhost/it-management/modules/roles_permissions/index.php) |
| Company module gate | [modules/company_module_access/index.php](http://localhost/it-management/modules/company_module_access/index.php) |
| Visitors inline edit | [modules/visitors_access_log/index.php](http://localhost/it-management/modules/visitors_access_log/index.php) |
| Chatbot API (session) | [modules/knowledge_base/chat_api.php](http://localhost/it-management/modules/knowledge_base/chat_api.php) |
| API v2 (key auth) | [modules/api_v2/router.php](http://localhost/it-management/modules/api_v2/router.php) |
| Scripts catalog (admin) | [scripts/scripts.php](http://localhost/it-management/scripts/scripts.php) |
| Guest portal | [booking/auth/login.php](http://localhost/it-management/booking/auth/login.php) |

---

## Bottom line

The codebase shows **mature security thinking** in the shared layer (auth, CSRF, module access, explorer, webhook SSRF, upload policies). The main gaps found in **application JS** (webmail preview, CMDB graph) and **operational toggles** (display_errors via UI config) are fixable without architectural rewrites. Root `.env` HTTP deny is **FIXED** in repository `.htaccess` (`ITM-PENTEST-023`).

The largest structural risk is **maintaining ~270 near-copy modules and a monolithic bootstrap** — future bugs will likely come from **inconsistent copies**, not from missing security primitives in `config.php`.

The **original register** did not run the application or automated suites. The **2026-09-03 verification pass** re-read sources statically and ran [verify_pentest_report.php?run=1](http://localhost/it-management/scripts/verify_pentest_report.php?run=1) for `ITM-PENTEST-023` (optional HTTP `.env` probe → 403). For a deeper follow-up, scope a single area (e.g. IDOR across all `view.php`, or `modules/equipment/` + IDF sync) with the same method.
