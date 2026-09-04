# Security and platform hardening backlog

Living register of **still-valid** items migrated from the retired GitHub Wiki page `TO-DO-3.md` (removed during the wiki sync — scratchpad retirement, not “all fixed”). For penetration-test status use [`docs/report.md`](report.md) and [`verify_pentest_report.php?run=1`](http://localhost/it-management/scripts/verify_pentest_report.php?run=1) (Admin session). For product/feature gaps use [`docs/PRODUCT_GAPS_AND_MISSING.md`](PRODUCT_GAPS_AND_MISSING.md).

**Last validated against codebase:** 2026-09-04 (items **#1**, **#2**, **#10**, and **#12** closed).

**Environment drift audit:** [check_env_vars_in_use.php?run=1](http://localhost/it-management/scripts/check_env_vars_in_use.php?run=1) (Admin session) — re-run after changing `.env.example` or `getenv()` reads. Canonical catalog: [`docs/ENV.md`](ENV.md).

| Category | Count (2026-09-04) | Notes |
|----------|-------------------|--------|
| **IN USE + DOCUMENTED** | **33** | Keys in `.env.example` and read in app/scripts (includes optional Mailpit, SNMP, CSAT, Booking.com sandbox) |
| **DOCUMENTED IN .env.example ONLY** | **0** | Would fail `--strict` |
| **IN CODE — app/runtime, not in .env.example** | **0** | Item **#12** closed |
| **IN CODE — tooling / CI / scripts only** | **46** | Example credentials, PHPUnit memory, import CLI overrides, screenshot helpers (`itm_env_vars_audit_known_tooling_vars()`) |
| **IN CODE — OS / host** | **2** | `PATH`, `WINDIR` |

Default run is **informational** (exit `0`). `php scripts/check_env_vars_in_use.php --strict` exits `1` when example-only keys exist or app/runtime keys are undocumented — **not** wired into smoke CI today; use before tightening `.env.example` policy.

---

## Status legend

| Status | Meaning |
|--------|---------|
| **Open** | Not implemented; spot-check confirms the gap |
| **Partial** | Related work exists but the backlog item is not complete |
| **Done** | Shipped; item can be removed on next review |

---

## P1 — Fix now (small effort, real risk)

### 1. Hash integration API keys (`ui_configuration`)

**Status:** **Done**

**Problem:** Paid-tier integration keys on `ui_configuration` were stored in plaintext column `api_key`. Lookup compared raw strings. `?api_key=` in query strings was accepted (access logs, proxies, browser history). Hotel distribution channels already use `api_key_hash` + `api_key_prefix` on `hotel_booking_distribution_channels` — integration keys should follow the same pattern.

**Shipped (2026-09-04):**

- `db/01_schema.sql` + `db/migrations/ui_configuration_api_key_hash.sql` — `api_key_prefix` (16 chars) + `api_key_hash` (SHA-256 hex); plaintext `api_key` cleared on save/generate
- `includes/itm_api_rate_limit.php` — `itm_api_hash_api_key()`, prefix-scoped lookup + verify; `X-API-Key` header and POST `api_key` only (query string rejected); legacy plaintext rows authenticate until re-saved/generated
- `modules/settings/index.php` — prefix display, one-time reveal after generate/save, replace/clear flows
- `AGENTS.md` — API keys and rate limits section updated

**Regression:** `php scripts/verify_api_key_hashing.php` — exit `0`. Browser: [verify_api_key_hashing.php?run=1](http://localhost/it-management/scripts/verify_api_key_hashing.php?run=1) (Administrator session). Also: `php scripts/apitest_tier_basic.php`, `php scripts/verify_api_v2.php`, PHPUnit `ApiRateLimitTest`.

**Manual:** [Settings → API Access](http://localhost/it-management/modules/settings/index.php) (Admin) — paid tier generate/save shows prefix + one-time full key reveal.

---

### 2. Authenticated vault encryption (AES-256-GCM)

**Status:** **Done**

**Problem:** `itm_encrypt()` / `itm_decrypt()` in `config/config.php` used AES-256-CBC with random IV and **no authentication tag**. Tampered ciphertext was not cryptographically detected.

**Shipped (2026-09-04):**

- `config/config.php` — `itm_encrypt()` writes **`v2:`** + base64(`iv` ‖ 16-byte `tag` ‖ `ciphertext`) with **AES-256-GCM**; `itm_decrypt()` routes `v2:` to `itm_decrypt_v2_gcm()` and legacy base64 CBC to `itm_decrypt_v1_cbc()` unchanged
- `docs/VAULT.md` — ciphertext format table (v2 GCM vs v1 CBC)
- PHPUnit `ItmEncryptDecryptTest` — v2 prefix, legacy v1 read, tamper rejection

**Regression:** `php scripts/verify_vault_gcm.php` — exit `0`. Browser: [verify_vault_gcm.php?run=1](http://localhost/it-management/scripts/verify_vault_gcm.php?run=1) (Administrator session). Also: `ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php --filter ItmEncryptDecrypt`.

**Note:** Master-key rotation / re-encryption pipelines unchanged — they call `itm_encrypt()` and therefore emit v2 on rewrite without separate migration.

---

### 3. Remove last `escape_sql()` POST paths

**Status:** **Done**

**Problem:** Deprecated `escape_sql()` survived on a few create handlers. Not known exploitable today (values were escaped), but it bypassed the prepared-statement contract enforced elsewhere.

**Touch points (spot-check 2026-09-02):**

- `modules/tickets/create.php` — ticket create/edit INSERT/UPDATE now use `mysqli_prepare` + `bind_param`
- `modules/inventory_items/create.php` — inventory create/edit INSERT/UPDATE prepared
- `modules/equipment/create.php` — `equipment_persist_row_prepared()` for equipment save; switch/idf port sync UPDATEs prepared

**Acceptance:** Convert these POST insert/update paths to MySQLi prepared statements; `php scripts/check_sql_injection_coverage.php` remains exit `0`.

**Regression:** `php scripts/check_sql_injection_coverage.php`; `php -l` on the three `create.php` files; optional MBQA create smoke on tickets/inventory/equipment when MySQL is available.

---

## P2 — Hardening (this quarter)

### 4. Single RBAC chokepoint for create / edit / delete

**Status:** **Done** — `itm_crud_mutation_guard_entry()` on index routers and standalone entry files; `itm_crud_enforce_mutation_access()` canonical API; `itm_crud_soft_delete_sql_for_module()` for programmatic soft-delete paths.

**Problem:** RBAC gates for mutations lived in three placements: wrapper `delete.php`, `index.php` delete branch, or direct `itm_require_role_module_permission()`. New modules could skip a gate silently.

**Touch points:**

- `includes/itm_crud_mutation_bootstrap.php` — `itm_crud_mutation_guard_entry()` (POST-only; infers create/edit from record id when needed)
- `includes/itm_role_module_permissions.php` — `itm_crud_enforce_mutation_access()`, `itm_require_crud_role_module_permission()`
- `includes/itm_crud_audit_fields.php` — `itm_crud_soft_delete_sql_for_module()` (RBAC + soft-delete SQL outside index handlers)
- Apply: [apply_crud_rbac_guards.php?run=1&apply=1](http://localhost/it-management/scripts/apply_crud_rbac_guards.php?run=1&apply=1), [apply_crud_mutation_bootstrap.php?run=1&apply=1](http://localhost/it-management/scripts/apply_crud_mutation_bootstrap.php?run=1&apply=1) (Admin session)
- Static audit: [check_crud_rbac_coverage.php?run=1](http://localhost/it-management/scripts/check_crud_rbac_coverage.php?run=1)

**Acceptance:** One enforced entry path for create/edit/delete; `php scripts/check_crud_rbac_coverage.php` exit `0`.

---

### 5. Production go-live checklist as code

**Status:** **Open**

**Problem:** Dev affordances (`scripts/bypass_login.php`, seed `Admin`/`Admin` password, `ITM_SCRIPT_NO_AUTH` allowlist) have no technical guard preventing accidental production exposure.

**Touch points:**

- New: `scripts/check_prod_hardening.php` — fail when `bypass_login.php` is web-reachable in prod mode, seed admin still uses default password, `display_errors` on, or `error_log.txt` under web root
- Wire into `.github/workflows/smoke.yml` (optional prod profile) or document as pre-deploy gate in `handoff.md`

**Acceptance:** Script exit `1` on unsafe prod posture; catalog entry in `scripts/scripts.php` + `scripts/SCRIPTS.md`.

---

### 6. Restrict `display_errors` / web-root error log to development

**Status:** **Partial** — `ITM_DEV` / `APP_ENV` in `.env` label dev vs production (`docs/ENV.md`); **`display_errors` is not auto-gated** by those keys yet.

**Problem:** `config/config.php` sets `ini_set('display_errors', '1')` and `error_log` to `ROOT_PATH . 'error_log.txt'` when UI setting `enable_all_error_reporting` is on (~lines 651–653). In production, errors can leak to browsers and a world-readable path under the docroot.

**Acceptance:** Production profile logs outside document root; `display_errors` off unless explicit dev env gates it (e.g. wire `APP_ENV=development` or `ITM_DEV=1` to block browser display even when Settings toggle is on) **or** operator discipline on Settings toggle only.

---

### 7. Bind CSRF double-submit cookie to session token

**Status:** **Open**

**Problem:** `itm_sync_csrf_double_submit_cookie()` mirrors the raw session CSRF token into readable cookie `itm_csrf`. A subdomain-set cookie with the same value could satisfy `itm_validate_csrf_token()` when the session row is missing.

**Touch points:**

- `config/config.php` — `itm_sync_csrf_double_submit_cookie()`, `itm_validate_csrf_token()` (~lines 1141–1200)
- `config/AGENT_NOTES.md` — double-submit contract

**Acceptance:** Cookie value is `hash_hmac('sha256', session_token, app_secret)` (or equivalent); validation compares HMAC, not plaintext token copy.

---

## P3 — Structural (next 1–2 quarters)

### 8. Split `config/config.php`

**Status:** **Open**

**Problem:** Monolithic bootstrap (~2,480 lines): session, CSRF, audit vars, MySQL error translation, seed-FK remapping, JSON import handler, encryption helpers.

**Suggested split (no behavior change):** `config/bootstrap.php`, `includes/csrf.php`, `includes/db_errors.php`, `includes/json_import.php` — same function names, `config.php` requires them.

---

### 9. PHPStan level 2 in CI

**Status:** **Open**

**Problem:** No static analysis in `.github/workflows/smoke.yml`. PHP 8.x migration goal benefits from early `mysqli_stmt_bind_param` arity and coercion failures.

**Acceptance:** New smoke job or tier2 step: `phpstan analyse` at level 2; baseline file for legacy noise if needed.

---

### 10. Grow PHPUnit (pure-logic coverage)

**Status:** **Done**

**Problem:** Heavy regression lives in `scripts/verify_*.php` (often needs Apache + MySQL). Selected helpers (CSRF POST guard, audit SQL parser, `itm_encrypt` round-trip, Explorer path normalizers, ZIP slip guard) were script-tested only.

**Acceptance:** Move selected pure-logic checks into `phpunit/tests/Unit/` so `ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php` covers them in seconds without MySQL.

**Shipped (2026-09-04):**

| Area | PHPUnit class | Notes |
|------|---------------|--------|
| CSRF POST guard | `phpunit/tests/Unit/Security/CsrfPostGuardTest.php` | `itm_try_post_csrf()`, cookie params |
| Encrypt round-trip | `phpunit/tests/Unit/Security/ItmEncryptDecryptTest.php` | `itm_encrypt()` / `itm_decrypt()` |
| Explorer paths | `phpunit/tests/Unit/Includes/ExplorerNormalizePathTest.php` | `explorer_normalize_relative_path()`, extension allowlist |
| ZIP slip | `phpunit/tests/Unit/Security/ExplorerZipSlipTest.php` | `explorer_extract_zip_safely()` |
| Audit SQL parser | `phpunit/tests/Unit/Includes/AuditSqlParserTest.php` | `itm_parse_audit_sql()` |
| SQL CSV/tuple split | `phpunit/tests/Unit/CRUD/CRUDunittest.php` | extended `itm_split_sql_csv()` / tuple edge cases |

**Regression (no MySQL):**

```bash
ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php --filter 'CsrfPostGuard|ItmEncryptDecrypt|ExplorerNormalize|ExplorerZipSlip|AuditSqlParser|CRUDUnittest'
```

Browser filter on the same runner: [run_tests.php?run=1&mode=standard&skip_db=1&filter=CsrfPostGuard](http://localhost/it-management/scripts/run_tests.php?run=1&mode=standard&skip_db=1&filter=CsrfPostGuard) (Administrator session). See **`scripts/SCRIPTS.md` → PHPUnit test runner**.

**Follow-up (optional):** migrate additional `verify_*` pure-logic probes as new backlog items — this ticket scoped the helpers named in the original problem only.

---

### 11. Document delete-branch RBAC shape in `AGENTS.md`

**Status:** **Open**

**Problem:** With three gate placements (item 4), onboarding developers guess wrong. `AGENTS.md` does not yet point at the intended chokepoint.

**Acceptance:** One paragraph under RBAC / module consistency: canonical mutation guard, link to `itm_require_crud_role_module_permission()` and `php scripts/check_crud_rbac_coverage.php`. Update when item 4 lands.

---

### 12. Close `.env.example` drift for app/runtime env reads

**Status:** **Done**

**Problem:** `php scripts/check_env_vars_in_use.php --strict` reported app/runtime keys read in code but missing from `.env.example` (Mailpit, SNMP, CSAT HMAC, Booking.com sandbox URL) plus example-only / tooling keys (`ITM_API_V2_*`, `ITM_DIST_*`, PHPUnit memory, screenshot helpers, `MYSQL_BIN`). Unused `DB_CONNECTION` in `.env.example` also failed strict.

**Acceptance:** `php scripts/check_env_vars_in_use.php --strict` exits `0` or remaining keys are explicitly classified (tooling allowlist + `docs/ENV.md`).

**Shipped (2026-09-04):**

- `.env.example` — commented optional blocks for Mailpit, SNMP community, legacy CSAT secret, Booking.com sandbox; `MYSQL_BIN` comment next to `MYSQL_EXE`; removed unused `DB_CONNECTION`
- `scripts/lib/itm_env_vars_audit.php` — tooling allowlist for API v2 / hotel distribution example credentials, PHPUnit memory, hospitality screenshot helpers, `MYSQL_BIN`
- `docs/ENV.md` — optional-runtime and tooling-key tables; strict audit green
- `api-examples/AGENT_NOTES.md` — example env var contract

**Regression:** `php scripts/check_env_vars_in_use.php --strict` → exit `0` (33 matched, 0 app drift, 46 tooling). Browser: [check_env_vars_in_use.php?run=1&strict=1](http://localhost/it-management/scripts/check_env_vars_in_use.php?run=1&strict=1) (Administrator session).

---

## What NOT to change

| Topic | Reason |
|-------|--------|
| Migrate off MySQLi to PDO/OOP framework | Audit toolchain (`check_sql_injection_coverage.php`, CRUD helpers) is built around MySQLi procedural style |
| Vault re-encryption pipeline | Item 2 must stay backward-compatible; no pipeline rewrite without explicit request |
| IDF bespoke sync flows | Flagged fragile in `handoff.md`; out of scope unless requested |

---

## Suggested sequencing

1. ~~**todo item 3** — remove last `escape_sql()` POST paths~~ **Done** (prepared statements on tickets/inventory/equipment create).
2. ~~**#4** + **#11** — same PR (chokepoint + docs)~~ **Done** (mutation guard bootstrap + apply scripts + coverage audit).
3. **#5** + **#6** — prod hardening pair.

---

## Related docs

| Doc | Role |
|-----|------|
| [`docs/ENV.md`](ENV.md) | `.env` catalog, `ITM_DEV` / `APP_ENV`, drift audit |
| [`check_env_vars_in_use.php?run=1`](http://localhost/it-management/scripts/check_env_vars_in_use.php?run=1) | Env read vs `.env.example` scan (Admin session) |
| [`docs/VERIFY.md`](VERIFY.md) | Static security review register |
| [`docs/report.md`](report.md) | ITM-PENTEST-001–023 live pentest register |
| [`docs/security_assessment_report.md`](security_assessment_report.md) | Defensive architecture checklist |
| [`docs/PRODUCT_GAPS_AND_MISSING.md`](PRODUCT_GAPS_AND_MISSING.md) | Product/feature backlog (not security hardening) |
| [`docs/FEATURE_ROADMAP.md`](FEATURE_ROADMAP.md) | Historical roadmap; partially superseded |
