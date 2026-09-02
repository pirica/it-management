# Security and platform hardening backlog

Living register of **still-valid** items migrated from the retired GitHub Wiki page `TO-DO-3.md` (removed during the wiki sync — scratchpad retirement, not “all fixed”). For penetration-test status use [`docs/report.md`](report.md) and [`verify_pentest_report.php?run=1`](http://localhost/it-management/scripts/verify_pentest_report.php?run=1) (Admin session). For product/feature gaps use [`docs/PRODUCT_GAPS_AND_MISSING.md`](PRODUCT_GAPS_AND_MISSING.md).

**Last validated against codebase:** 2026-09-02.

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

**Status:** **Open**

**Problem:** Paid-tier integration keys on `ui_configuration` are stored in plaintext column `api_key`. Lookup compares raw strings. `?api_key=` in query strings is still accepted (access logs, proxies, browser history). Hotel distribution channels already use `api_key_hash` + `api_key_prefix` on `hotel_booking_distribution_channels` — integration keys should follow the same pattern.

**Touch points:**

- Schema: `db/01_schema.sql` — `ui_configuration.api_key` (add `api_key_hash`, `api_key_prefix`; migration in `db/migrations/`)
- Lookup: `includes/itm_api_rate_limit.php` — `itm_api_resolve_api_key_from_request()` (~lines 160–165 GET path; ~203/240 lookup queries)
- Settings UI: `modules/settings/` — key generate/save flows
- Regression: add `php scripts/verify_api_key_hashing.php` (match existing `verify_*` convention)

**Acceptance:** Store `hash('sha256', $key)` only; display prefix for UI; remove `$_GET['api_key']` — header `X-API-Key` and POST body only; re-issue flow for existing keys on next save.

---

### 2. Authenticated vault encryption (AES-256-GCM)

**Status:** **Open**

**Problem:** `itm_encrypt()` / `itm_decrypt()` in `config/config.php` use AES-256-CBC with random IV and **no authentication tag**. Tampered ciphertext is not cryptographically detected.

**Touch points:**

- `config/config.php` — `itm_encrypt()`, `itm_decrypt()` (~lines 2450–2477)
- All vault/SMTP/TOTP/LDAP/Stripe callers via `itm_encrypt()` (see `docs/VAULT.md`, `docs/report.md` cryptography section)

**Acceptance:** New writes use versioned prefix `v2:` + base64(iv ‖ tag ‖ ciphertext) with `aes-256-gcm`; `itm_decrypt()` reads legacy v1 (CBC) rows unchanged. Add `php scripts/verify_vault_gcm.php` round-trip + legacy read.

**Do not** change the vault re-encryption pipeline or master-key rotation flows without an explicit request — GCM must remain backward-compatible with existing rows.

---

### 3. Remove last `escape_sql()` POST paths

**Status:** **Open**

**Problem:** Deprecated `escape_sql()` survives on a few create handlers. Not known exploitable today (values are escaped), but it bypasses the prepared-statement contract enforced elsewhere.

**Touch points (spot-check 2026-09-02):**

- `modules/tickets/create.php` — multiple `escape_sql()` calls (~lines 365–371, 464)
- `modules/inventory_items/create.php` — `escape_sql()` (~lines 65–70)
- `modules/equipment/create.php` — additional `escape_sql()` usage (same pattern)

**Acceptance:** Convert these POST insert/update paths to MySQLi prepared statements; `php scripts/check_sql_injection_coverage.php` remains exit `0`.

---

## P2 — Hardening (this quarter)

### 4. Single RBAC chokepoint for create / edit / delete

**Status:** **Open** (audit net exists; chokepoint does not)

**Problem:** RBAC gates for mutations live in three placements: wrapper `delete.php`, `index.php` delete branch, or direct `itm_require_role_module_permission()`. New modules can skip a gate silently.

**Touch points:**

- `includes/itm_role_module_permissions.php` — `itm_require_crud_role_module_permission()`, `itm_require_role_module_permission()`
- Callers inside `itm_crud_build_soft_delete_sql()` paths or a shared pre-handler
- Static audit (keep): [`check_crud_rbac_coverage.php?run=1`](http://localhost/it-management/scripts/check_crud_rbac_coverage.php?run=1)

**Acceptance:** One enforced entry path for create/edit/delete; `php scripts/check_crud_rbac_coverage.php` still exit `0`.

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

**Status:** **Open**

**Problem:** `config/config.php` sets `ini_set('display_errors', '1')` and `error_log` to `ROOT_PATH . 'error_log.txt'` when UI setting `enable_all_error_reporting` is on (~lines 651–653). In production, errors can leak to browsers and a world-readable path under the docroot.

**Acceptance:** Production profile logs outside document root; `display_errors` off unless explicit dev env (e.g. `.env` `ITM_DEV=1`).

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

**Status:** **Partial**

**Problem:** Heavy regression lives in `scripts/verify_*.php` (often needs Apache + MySQL). Many PHPUnit tests exist today, but CSRF validation, SQL parser helpers, `itm_encrypt` round-trip, and path normalizers are still primarily script-tested.

**Acceptance:** Move selected pure-logic checks into `phpunit/tests/Unit/` so `ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php` covers them in seconds without MySQL.

---

### 11. Document delete-branch RBAC shape in `AGENTS.md`

**Status:** **Open**

**Problem:** With three gate placements (item 4), onboarding developers guess wrong. `AGENTS.md` does not yet point at the intended chokepoint.

**Acceptance:** One paragraph under RBAC / module consistency: canonical mutation guard, link to `itm_require_crud_role_module_permission()` and `php scripts/check_crud_rbac_coverage.php`. Update when item 4 lands.

---

## What NOT to change

| Topic | Reason |
|-------|--------|
| Migrate off MySQLi to PDO/OOP framework | Audit toolchain (`check_sql_injection_coverage.php`, CRUD helpers) is built around MySQLi procedural style |
| Vault re-encryption pipeline | Item 2 must stay backward-compatible; no pipeline rewrite without explicit request |
| IDF bespoke sync flows | Flagged fragile in `handoff.md`; out of scope unless requested |

---

## Suggested sequencing

1. **#1** and **#2** — one PR each with matching `verify_*` script.
2. **#3** — can ride with either P1 PR.
3. **#4** + **#11** — same PR (chokepoint + docs).
4. **#5** + **#6** — prod hardening pair.

---

## Related docs

| Doc | Role |
|-----|------|
| [`docs/report.md`](report.md) | ITM-PENTEST-001–022 register |
| [`docs/security_assessment_report.md`](security_assessment_report.md) | Defensive architecture checklist |
| [`docs/PRODUCT_GAPS_AND_MISSING.md`](PRODUCT_GAPS_AND_MISSING.md) | Product/feature backlog (not security hardening) |
| [`docs/FEATURE_ROADMAP.md`](FEATURE_ROADMAP.md) | Historical roadmap; partially superseded |
