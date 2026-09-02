# Security Penetration Test Report

**Application:** IT Management System (multi-tenant PHP/MySQL)  
**Assessment date:** 2026-08-31  
**Assessment type:** Read-only static analysis and configuration review (no destructive testing, no code or data modifications)  
**Repository scope:** Full project tree (`config/`, `includes/`, `modules/`, `scripts/`, `booking/`, `js/`, `db/`, root entry points)

---

## Executive Summary

The IT Management System is a large procedural PHP application (~2,643 PHP files, 272+ module entry points) with substantial defensive engineering: prepared-statement static auditing, CSRF coverage checks, upload-directory hardening, multi-tenant `company_id` enforcement in central bootstrap, role/module access gates, and targeted regression scripts for high-risk areas (Explorer ACL, QR share, API v2, hotel distribution).

**Overall posture:** Moderate — strong baseline controls for a legacy PHP codebase, but **deployment hygiene** risks remain (default seed credentials, DB-password-derived encryption keys). Request Password email approvals (ITM-PENTEST-001–003) and verbose PHP error display defaults (ITM-PENTEST-006) were remediated. No confirmed remote code execution or unauthenticated full-application takeover was identified in this read-only review; previously reported cancellation-policy RCE paths appear mitigated in current `includes/itm_hotel_booking.php`.

**Primary risks:** Credential and secrets compromise via default accounts or database credential leakage; phishing via intentional short-link redirects. Request Password email approvals now use POST+CSRF confirm flow with designated-approver binding (ITM-PENTEST-001–003 remediated).

---

## Scope

| Area | Coverage |
|------|----------|
| Authentication | `login.php`, `logout.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `sso-ldap.php`, `sso-saml.php`, `scripts/bypass_login.php` |
| Session handling | `config/config.php` session bootstrap, cookie flags, fixation controls |
| Authorisation / RBAC | `config/config.php`, `includes/itm_role_module_permissions.php`, `includes/itm_company_module_access.php`, per-module guards |
| Input / SQL | Static scan (`scripts/check_sql_injection_coverage.php`), representative module review |
| CSRF | Static scan (`scripts/check_csrf_coverage.php`), form/AJAX patterns |
| File upload / Explorer | `modules/explorer/api.php`, `modules/explorer/file.php`, `includes/bootstrap_helpers.php` |
| Public / partner APIs | `modules/api_v2/router.php`, `modules/hotel_booking_api/api.php`, `scripts/api.php`, `scripts/openapi.php` |
| Guest / unauthenticated surfaces | `booking/`, `ticket-survey.php`, `modules/*/join.php` (82 files), `go.php`, `modules/short-url/go.php`, QR share |
| Cryptography / secrets | `config/config.php` (`itm_encrypt`), SMTP/TOTP/LDAP/Stripe key helpers, vault design |
| Configuration / deployment | `.env.example`, `db/02_data.sql` seeds, `.htaccess`, maintenance-token bypass |
| Administrative / diagnostic | `modules/system_status/`, `scripts/system_status_phpinfo.php`, MBQA runners |

**Out of scope for dynamic exploitation:** Live HTTP probing against a running server, brute-force attacks, and any mutating or load-based testing (per engagement rules).

---

## Testing Methodology

1. **Architecture reconnaissance** — mapped bootstrap (`config/config.php`), auth bypass flags, public constants (`ITM_*_PUBLIC`), and API routers.
2. **Static analysis (automated)** — executed read-only repository scripts:
   - `php scripts/check_sql_injection_coverage.php` → **PASS** (2,643 files, 0 high-confidence findings)
   - `php scripts/check_csrf_coverage.php` → **PASS** (documented exemptions for CLI/QA scripts only)
3. **Manual source review** — authentication, approval workflows, encryption key derivation, upload validation, public join/share tokens, webhook signature verification, security headers, error-handling defaults.
4. **Cross-reference** — prior internal notes (`docs/security-validated-findings.md`, `docs/security_assessment_report.md`) verified against current code; stale/theoretical items re-validated or excluded.
5. **Safe proof-of-concept** — logical PoCs derived from code (token forgery formulas, header absence, config flags); no live exploitation or data modification.
6. **Regression verifier** — `php scripts/verify_pentest_report.php` (static line-level checks for ITM-PENTEST-001–022; PHPUnit: `--filter PentestReport`). Browser: [verify_pentest_report.php?run=1](http://localhost/it-management/scripts/verify_pentest_report.php?run=1) (Administrator session).

---

## Regression verifier output (`verify_pentest_report.php`)

Exit code **0** means every finding still matches `docs/report.md` — **not** that all risks are closed.

| Label | Meaning | Findings |
|-------|---------|----------|
| **`[PASS]`** | **Remediated** — documented fix still in place | 001–003, 006–007, 010, 012–014 |
| **`[OPEN]`** | **Documented gap** — weakness or misconfiguration still present (do **not** treat as secure) | 004–005, 008–011, 015–016 |
| **`[INFO]`** | **Informational / positive control** — defensive measure confirmed working | 017–022 |
| **`[FAIL]`** | Report and repository out of sync, or a regression check broke | — |

Examples:

- `[OPEN] ITM-PENTEST-004: seed Admin bcrypt…` — default `Admin` password still in `db/02_data.sql` (expected until seeds change).
- `[OPEN] ITM-PENTEST-005: DB_PASS-derived encryption keys…` — integration secrets still keyed from `DB_PASS` (documented, not remediated).
- `[INFO] ITM-PENTEST-017: check_sql_injection_coverage.php passes` — static SQLi gate is effective.

---

## Risk Summary

| Severity      | Count |
| ------------- | ----- |
| Critical      | 0     |
| High          | 1     |
| Medium        | 6     |
| Low           | 3     |
| Informational | 6     |

---

## Findings

### ITM-PENTEST-001 Hardcoded HMAC secret for password-request email approvals

**Status:** **Remediated** — secret loaded from `.env` `ITM_REQUEST_PASSWORD_APPROVAL_SECRET` via `itm_request_password_approval_secret()`  
**Date updated:** 2026-09-02  
**Verification:** **Remediated** — no `request_password_secret_key_2024` in `modules/request_password/index.php` or `scripts/verify_request_password.php`; helper `getenv('ITM_REQUEST_PASSWORD_APPROVAL_SECRET')` at `includes/itm_request_password_approval.php` line **10**; approval handler uses helper at `modules/request_password/index.php` lines **58–65** and email token build at lines **119–125**. Regression: `php scripts/verify_pentest_report.php`, `php scripts/verify_request_password.php` (requires non-empty env).

**Severity:** High  
**OWASP Category:** A02:2021 – Cryptographic Failures / A07:2021 – Identification and Authentication Failures  
**Affected Component:** Request Password module — email approval links  
**Affected File:** `modules/request_password/index.php`  
**Affected Function:** Inline approval handler (`$_GET['approval_api']`)  
**Affected Parameter:** `token`, `id`, `target`, `decision`

**Description:**  
HR/HOD approve/decline links use `hash_hmac('sha256', $recordId . $target . $decision, $secret)`. **Previously** the secret was hardcoded in source (`request_password_secret_key_2024`). **Now** `$secret` comes from `ITM_REQUEST_PASSWORD_APPROVAL_SECRET` in project root `.env` through `itm_request_password_approval_secret()` in `includes/itm_request_password_approval.php`. Rotate the env value per environment; never commit production values.

**Evidence (current — remediated):**

```10:16:includes/itm_request_password_approval.php
        $env = getenv('ITM_REQUEST_PASSWORD_APPROVAL_SECRET');
        if ($env !== false && $env !== '') {
            return (string) $env;
        }

        return '';
```

```58:65:modules/request_password/index.php
    $secret = itm_request_password_approval_secret();
    if ($secret === '') {
        ...
    }
    $expectedToken = hash_hmac('sha256', $recordId . $target . $decision, $secret);
```

**Proof of Concept (safe, offline):** With `ITM_REQUEST_PASSWORD_APPROVAL_SECRET` set in `.env`, generate tokens only on hosts that hold the env value — not from repository source alone.

**Impact:** Forging approval tokens requires the deployment secret (`.env` or process env), not cloned PHP source.

**Attack Scenario:** Attacker with **only** repo access cannot derive valid tokens without the production env value.

**Recommendation:** Keep a long random secret per environment; restrict `.env` file permissions; rotate after staff changes. *(Remediated for hardcoded source secret; ITM-PENTEST-002/003 also remediated.)*

---

### ITM-PENTEST-002 State-changing GET endpoint for password-request approvals (CSRF)

**Date updated:** 2026-09-02  
**Status:** **Remediated** — email links open a GET confirmation page (`approval_confirm`); state change requires POST `approval_submit` with `itm_require_post_csrf()`.  
**Verification:** **Remediated** — no `approval_api` GET handler in `modules/request_password/index.php`; GET `approval_confirm` and POST `approval_submit` with CSRF at `rp_process_approval_decision()` / `itm_request_password_approval_render_confirm_form()` in `includes/itm_request_password_approval.php`. Regression: `php scripts/verify_pentest_report.php`, `php scripts/verify_request_password.php`.

**Severity:** High  
**OWASP Category:** A01:2021 – Broken Access Control  
**Affected Component:** Request Password — email approval API  
**Affected File:** `modules/request_password/index.php`, `includes/itm_request_password_approval.php`  
**Affected Function:** Approval confirm + POST handler (formerly `approval_api` GET)  
**Affected Parameter:** HTTP method GET with `decision=approve|decline` *(historical)*

**Description:**  
Previously, approvals were applied via **idempotent GET requests** without CSRF tokens. Email links now land on a read-only confirmation page; the approver must submit a POST form protected by CSRF before `request_password` is updated.

**Evidence (remediated):** Email URLs use `approval_confirm=1`; `UPDATE request_password` runs only inside POST `approval_submit` after `itm_require_post_csrf()`.

**Impact (historical):** Integrity violation — password-reset workflow bypass or denial without approver intent.

**Recommendation:** *(Implemented)* POST with CSRF for approvals; designated approver verification (ITM-PENTEST-003).

---

### ITM-PENTEST-003 Missing approver identity and role verification on email approvals

**Date updated:** 2026-09-02  
**Status:** **Remediated** — HMAC tokens bind designated `approver` employee id; `itm_request_password_approval_employee_may_act()` gates GET confirm and POST apply.  
**Verification:** **Remediated** — `itm_request_password_approval_sign_token()` / `itm_request_password_approval_verify_token()` include approver employee id in payload (`includes/itm_request_password_approval.php`); `modules/request_password/index.php` calls `itm_request_password_approval_employee_may_act()` before confirm render and before `UPDATE`. Regression: `php scripts/verify_pentest_report.php`, `php scripts/verify_request_password.php`.

**Severity:** High  
**OWASP Category:** A01:2021 – Broken Access Control  
**Affected Component:** Request Password — approval handler  
**Affected File:** `modules/request_password/index.php`, `includes/itm_request_password_approval.php`  
**Affected Function:** `itm_request_password_approval_employee_may_act()`  
**Affected Parameter:** `approver` query/body field + session `employee_id`

**Description:**  
Previously, after HMAC validation, the handler updated approval status using only `company_id` from the session without verifying the session user was the designated HR/HOD approver. Tokens and handlers now require the logged-in employee to match the tenant `approvers` row for HRD/HOD Approval.

**Impact (historical):** Horizontal privilege escalation within tenant — any staff with a leaked token could approve sensitive password-reset workflows.

**Recommendation:** *(Implemented)* Bind tokens to approver employee ID; verify `$_SESSION['employee_id']` matches designated approver before `UPDATE`.

---

### ITM-PENTEST-004 Default and demo credentials in database seed data

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** (documented — not remediated) — expect **`[OPEN]`** in `php scripts/verify_pentest_report.php`. Seed Admin bcrypt hash at `db/02_data.sql` line **868** (`username` `Admin` for company 1); demo accounts block comment line **2261**, `INSERT` at lines **2262–2278** (`demo1`–`demo5`, password equals username per comment).

**Severity:** High  
**OWASP Category:** A07:2021 – Identification and Authentication Failures  
**Affected Component:** Authentication — seed employees  
**Affected File:** `db/02_data.sql`  
**Affected Function:** Seed `INSERT` blocks  
**Affected Parameter:** N/A

**Description:**  
Fresh imports seed administrator accounts (`Admin`, `Admin2`–`Admin5`) with a **known bcrypt hash** (documented in `AGENTS.md` as password `Admin`). Additional demo accounts (`demo1`–`demo5`) use passwords equal to usernames per seed comments.

**Evidence:** `db/02_data.sql` lines ~868–872 (Admin bcrypt `$2y$10$uICOCOSxZPMi8xEcyJKTju...`); demo block ~2261–2269.

**Proof of Concept (safe):** After default import, authenticate at [login.php](http://localhost/it-management/login.php) with username `Admin` / password `Admin`.

**Impact:** Full administrative compromise on deployments that retain default seeds without mandatory password rotation.

**Attack Scenario:** External attacker scans internet-facing install, attempts default `Admin`/`Admin`.

**Recommendation:** Force password change on first login, remove demo accounts from production bundles, and document secure provisioning. *(Documentation only.)*

---

### ITM-PENTEST-005 Server-side secrets encrypted with keys derived from database password

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** (documented — not remediated) — expect **`[OPEN]`** in `php scripts/verify_pentest_report.php`. `hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . …)` in `includes/itm_email.php` lines **10–12** (`itm_smtp_encryption_key`), `includes/itm_totp_helpers.php` lines **260–262**, `includes/itm_ldap_auth.php` lines **7–9**, `includes/itm_stripe_checkout.php` lines **7–9**, `includes/itm_hotel_booking_distribution_secrets.php` lines **7–8**, `includes/itm_webhook_queue.php` lines **7–9**.

**Severity:** Medium  
**OWASP Category:** A02:2021 – Cryptographic Failures  
**Affected Component:** SMTP, TOTP, LDAP, Stripe, webhooks, hotel distribution secrets  
**Affected File:** `includes/itm_email.php`, `includes/itm_totp_helpers.php`, `includes/itm_ldap_auth.php`, `includes/itm_stripe_checkout.php`, `includes/itm_hotel_booking_distribution_secrets.php`, `includes/itm_webhook_queue.php`  
**Affected Function:** `*_encryption_key()` helpers  
**Affected Parameter:** `DB_PASS` environment value

**Description:**  
Multiple sensitive columns are encrypted with AES-256-CBC via `itm_encrypt()`, using keys derived as `hash('sha256', DB_PASS . 'salt', true)`. Compromise of the database **or** `.env` `DB_PASS` allows offline decryption of SMTP passwords, TOTP seeds, LDAP bind passwords, Stripe keys, etc. Keys are not independent per secret class from an attacker’s perspective once `DB_PASS` is known.

**Evidence:**

```10:13:includes/itm_email.php
    function itm_smtp_encryption_key()
    {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_smtp_v1', true);
    }
```

Similar pattern in `itm_totp_encryption_key()` (`includes/itm_totp_helpers.php` lines 260–263) with fallback `'itmanagement'`.

**Proof of Concept (safe):** With `DB_PASS` from `.env`, recompute key and decrypt `email_smtp_configurations` ciphertext offline.

**Impact:** Credential disclosure, 2FA bypass (TOTP secret recovery), payment and integration abuse.

**Attack Scenario:** SQL/file read exposing `.env` or backup → decrypt all integration secrets.

**Recommendation:** Use a dedicated `ITM_APP_KEY` (or KMS) independent of DB credentials; consider envelope encryption per tenant. *(Documentation only.)*

---

### ITM-PENTEST-006 Verbose error display enabled by default

**Status:** **Remediated** (default off; admins may re-enable in Settings → UI Configuration)  
**Date updated:** 2026-09-02  
**Verification:** **Remediated** — `itm_ui_config_defaults()['enable_all_error_reporting']` at `includes/ui_config.php` line **1540**; bootstrap fallback `?? 0` at `config/config.php` line **651**; schema default `'0'` at `db/01_schema.sql` line **3716**; backfill `db/migrations/ui_configuration_error_reporting_default_off_dml.sql`. Regression: `php scripts/verify_pentest_report.php`.

**Severity:** Medium  
**OWASP Category:** A05:2021 – Security Misconfiguration  
**Affected Component:** Global bootstrap  
**Affected File:** `config/config.php`, `includes/ui_config.php`, `db/01_schema.sql`, `db/02_data.sql`  
**Affected Function:** Error reporting block; `itm_ui_config_defaults()`  
**Affected Parameter:** `enable_all_error_reporting` (default **`0`**)

**Description:**  
When `enable_all_error_reporting` is enabled, the application sets `display_errors=1` and logs to `error_log.txt` under the project root. This can expose stack traces, SQL fragments, and filesystem paths to end users. **At assessment time** the default was `1` for new `ui_configuration` rows; the codebase now defaults to **`0`** (schema, seeds, PHP fallbacks, and Settings checkbox).

**Evidence (current — remediated):**

```650:655:config/config.php
if (($ui_config['enable_all_error_reporting'] ?? 0) === 1) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . 'error_log.txt');
}
```

`itm_ui_config_defaults()['enable_all_error_reporting']` is `0`; `db/01_schema.sql` column default is `'0'`; `db/migrations/ui_configuration_error_reporting_default_off_dml.sql` backfills existing rows from `1` → `0`.

**Proof of Concept (safe):** With defaults unchanged after remediation, trigger a handled PHP notice on a module page — inline error output should **not** appear unless an admin enables **Enable all error reporting** in Settings.

**Impact:** Information disclosure aiding further attacks (path disclosure, query hints) when the flag is on.

**Attack Scenario:** Attacker probes malformed parameters to surface warnings on production only when verbose reporting was explicitly enabled.

**Recommendation:** Keep default `0` in production; log errors server-side only; block web access to `error_log.txt`. *(Remediated for default; log-file hardening remains optional.)*

---

### ITM-PENTEST-007 Missing standard HTTP security headers

**Status:** **Remediated** — `itm_send_security_headers()` in `includes/itm_security_headers.php`, invoked from `config/config.php` on every web request before `session_start()`.  
**Date updated:** 2026-09-02  
**Verification:** **Remediated** — expect **`[PASS]`** in `php scripts/verify_pentest_report.php`. Headers: `Content-Security-Policy` (pragmatic: `'self'`, `'unsafe-inline'`, `https://cdn.jsdelivr.net`), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security: max-age=31536000` when the request is HTTPS (including `X-Forwarded-Proto=https`).

**Severity:** Medium (was open; remediated in application bootstrap)  
**OWASP Category:** A05:2021 – Security Misconfiguration  
**Affected Component:** Global HTTP responses  
**Affected File:** `includes/itm_security_headers.php`, `config/config.php`  
**Affected Function:** `itm_send_security_headers()`, `itm_build_content_security_policy()`  
**Affected Parameter:** N/A

**Description:**  
Browser-facing responses now receive centralized security headers from bootstrap. CSP allows inline scripts/styles required by legacy module UI and scripts hosted on `cdn.jsdelivr.net` (Quill, html2canvas, jsPDF, Bootstrap bundle). Tighten CSP with nonces in a follow-up if inline handlers are reduced.

**Evidence (current — remediated):**

```php
// config/config.php — after bootstrap_helpers.php
require_once ROOT_PATH . 'includes/itm_security_headers.php';
if (PHP_SAPI !== 'cli') {
    itm_send_security_headers();
}
```

**Proof of Concept (safe):** `curl -sI http://localhost/it-management/login.php` — response includes `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Content-Security-Policy`. On HTTPS deployments, `Strict-Transport-Security` is also sent.

**Impact:** Reduced clickjacking, MIME sniffing, and referrer leakage; baseline XSS containment via CSP (inline scripts still permitted).

**Attack Scenario:** External sites cannot frame ITM in an iframe (`SAMEORIGIN` / `frame-ancestors 'self'`). Full XSS containment still depends on output encoding and CSRF — CSP is not strict nonce-only yet.

**Recommendation:** Keep headers in bootstrap; tighten CSP when modules drop inline `onclick` / `<script>` blocks. Reverse proxies may still add complementary headers — avoid duplicate/conflicting values.

---

### ITM-PENTEST-008 Maintenance / localhost authentication bypass for QA scripts

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — localhost / maintenance-token skip at `config/config.php` lines **543–547** (`$itmIsLocalhost`, `hash_equals($itmMaintToken, …)`); browser QA allowlist in `scripts/lib/itm_script_bootstrap.php` includes `module_browser_qa_runner.php` and `run_tests.php`. Regression: `php scripts/verify_pentest_report.php`.

**Severity:** Medium  
**OWASP Category:** A01:2021 – Broken Access Control  
**Affected Component:** Browser-accessible QA runners  
**Affected File:** `config/config.php`, `scripts/lib/itm_script_bootstrap.php`  
**Affected Function:** Maintenance auth gate (`ITM_MAINTENANCE_TOKEN`, localhost check)  
**Affected Parameter:** `token` query param, `HTTP_X_ITM_MAINTENANCE_TOKEN`

**Description:**  
`module_browser_qa_runner.php` and `run_tests.php` skip normal web authentication when the request originates from `127.0.0.1`/`::1` **or** when `ITM_MAINTENANCE_TOKEN` matches a supplied token. A leaked token on an internet-exposed host grants unauthenticated access to powerful QA tooling.

**Evidence:**

```541:547:config/config.php
        if ($itmIsLocalhost || ($itmMaintToken !== '' && hash_equals($itmMaintToken, (string)$itmProvidedToken))) {
            $itmSkipWebAuth = true;
        }
```

Allowlist: `scripts/lib/itm_script_bootstrap.php` → `module_browser_qa_runner.php`, `run_tests.php`.

**Proof of Concept (safe):** `GET http://localhost/it-management/scripts/run_tests.php?run=1` without session (localhost only).

**Impact:** Unauthenticated administrative QA, potential data mutation in test flows, information disclosure.

**Attack Scenario:** Production deploy exposes app to internet; `.env` or config leak reveals `ITM_MAINTENANCE_TOKEN`; attacker runs MBQA runner remotely.

**Recommendation:** Disable maintenance bypass in production builds; require admin session even on localhost for browser QA; use network ACLs. *(Documentation only.)*

---

### ITM-PENTEST-009 Short URL service enables open redirects (phishing risk)

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — `itm_short_url_normalize_destination()` at `includes/itm_short_url.php` line **112** (allows `http://` / `https://`); 302 redirect at `modules/short-url/go.php` line **62**; `'require_https_destination' => 0` default at `includes/itm_short_url.php` line **129**. Regression: `php scripts/verify_pentest_report.php`.

**Severity:** Medium  
**OWASP Category:** A10:2021 – Server-Side Request Forgery (redirect variant) / social engineering  
**Affected Component:** Short URL module  
**Affected File:** `modules/short-url/go.php`, `includes/itm_short_url.php`  
**Affected Function:** `itm_short_url_normalize_destination()`  
**Affected Parameter:** `destination_url` (stored), `c` / `t` (public)

**Description:**  
Authenticated users can create short links that 302 redirect to arbitrary `http://` or `https://` destinations. `require_https_destination` exists in settings but defaults to off. Attackers with link-creation rights can abuse trusted domain for phishing.

**Evidence:**

```112:121:includes/itm_short_url.php
function itm_short_url_normalize_destination($url)
{
    ...
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}
```

```62:63:modules/short-url/go.php
header('Location: ' . $destination, true, 302);
```

**Proof of Concept (safe):** Create short link to `https://evil.example` → public [go.php](http://localhost/it-management/go.php) redirect.

**Impact:** Phishing, reputation abuse; not arbitrary internal SSRF (external URL only).

**Attack Scenario:** Compromised or malicious employee publishes `go.php?c=abc` in ticket email; victims trust `localhost/it-management` domain.

**Recommendation:** Enforce HTTPS destinations, allowlist domains, interstitial warning page, rate limits on creation. *(Documentation only.)*

---

### ITM-PENTEST-010 Legacy plaintext password-reset token column support

**Status:** **Remediated** — new tokens store `reset_token_hash` only (`reset_token` cleared); lookup/complete use hash-only SQL; `itm_password_reset_backfill_legacy_plaintext_tokens()` migrates any remaining plaintext rows on lookup.  
**Date updated:** 2026-09-02  
**Verification:** **Remediated** — expect **`[PASS]`** in `php scripts/verify_pentest_report.php`. No `OR (reset_token = ?` branch in `includes/itm_password_reset.php`. Regression: `php scripts/verify_password_reset_flow.php`.

**Severity:** Medium (was open; remediated)  
**OWASP Category:** A02:2021 – Cryptographic Failures  
**Affected Component:** Password reset  
**Affected File:** `includes/itm_password_reset.php`  
**Affected Function:** `itm_password_reset_store_token_for_employee()`, `itm_password_reset_lookup_employee_by_token()`, `itm_password_reset_complete_for_employee()`  
**Affected Parameter:** `reset_token`, `reset_token_hash`

**Description:**  
Token validation previously accepted **either** `reset_token_hash` **or** legacy plaintext `reset_token` column match. New issuances store only the SHA-256 hash and null the plaintext column; pending legacy rows are backfilled before lookup.

**Evidence (current — remediated):**

```php
SET reset_token = NULL, reset_token_hash = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL … HOUR)
```

```php
WHERE reset_token_hash = ? AND reset_token_expires_at >= NOW()
```

**Impact:** Database read access no longer exposes usable plaintext reset tokens for active invites.

**Recommendation:** Keep hash-only storage; run `php scripts/verify_password_reset_flow.php` after auth changes. *(Remediated.)*

---

### ITM-PENTEST-011 Public unauthenticated information endpoints

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — `ITM_SCRIPT_NO_AUTH` allowlist in `config/config.php` lines **514–516** includes `count_db_tables.php` and `openapi.php` (no session required). Regression: `php scripts/verify_pentest_report.php`.

**Severity:** Medium  
**OWASP Category:** A05:2021 – Security Misconfiguration  
**Affected Component:** Diagnostics / API documentation  
**Affected File:** `scripts/count_db_tables.php`, `scripts/openapi.php`  
**Affected Function:** N/A  
**Affected Parameter:** N/A

**Description:**  
`count_db_tables.php` (no login) returns live MySQL table count and writes `scripts/number_db_tables.txt`. `openapi.php?format=json` exposes full API v2 route catalog without authentication (by design).

**Evidence:** `config/config.php` allowlist `ITM_SCRIPT_NO_AUTH` includes `count_db_tables.php`, `openapi.php`.

**Proof of Concept (safe):** [count_db_tables.php](http://localhost/it-management/scripts/count_db_tables.php) (no auth); [openapi.php?format=json](http://localhost/it-management/scripts/openapi.php?format=json).

**Impact:** Low-sensitivity reconnaissance (schema scale, API surface). Combined with other bugs, aids targeting.

**Recommendation:** Restrict by IP, authentication, or move behind admin tools in production. *(Documentation only.)*

---

### ITM-PENTEST-012 MySQL error message echoed to users on save failure

**Status:** **Remediated** — create/edit save failures show a generic message; `mysqli_stmt_error()` is written to `error_log()` only. Email approval failures already used a generic message (no `mysqli_error` in the browser).  
**Date updated:** 2026-09-02  
**Verification:** **Remediated** — expect **`[PASS]`** in `php scripts/verify_pentest_report.php`. No `$error = … mysqli_error($conn)` or approval `echo … mysqli_error` in `modules/request_password/index.php`.

**Severity:** Low (was open; remediated)  
**OWASP Category:** A05:2021 – Security Misconfiguration  
**Affected Component:** Request Password create/edit save handler (formerly also approval handler in report text)  
**Affected File:** `modules/request_password/index.php`  
**Affected Function:** Create/edit POST save branch  
**Affected Parameter:** N/A

**Description:**  
On failed `INSERT`/`UPDATE` during create or edit, the module previously set `$error` from `mysqli_error($conn)`, which rendered in the form alert. Approval POST failures were already generic after the POST+CSRF approval refactor.

**Evidence (current — remediated):**

```php
error_log(
    'request_password save failed (company_id=' . (int)$company_id
    . ', action=' . (string)$crud_action . '): ' . mysqli_stmt_error($stmt)
);
$error = 'Error saving record. Please try again or contact support.';
```

**Impact:** Reduced minor information disclosure (schema/constraint text no longer shown to end users).

**Recommendation:** Keep generic user copy; retain server-side `error_log()` for operators. *(Remediated.)*

---

### ITM-PENTEST-013 Public ticket survey form lacks CSRF token

**Status:** **Remediated** — public survey POST validates `itm_validate_csrf_token()` and the form emits `csrf_token` from `itm_get_csrf_token()`.  
**Date updated:** 2026-09-02  
**Verification:** **Remediated** — expect **`[PASS]`** in `php scripts/verify_pentest_report.php`. `ticket-survey.php` POST handler calls `itm_validate_csrf_token()` before `itm_ticket_survey_submit()`.

**Severity:** Low (was open; remediated)  
**OWASP Category:** A01:2021 – Broken Access Control (CSRF)  
**Affected Component:** Ticket survey public page  
**Affected File:** `ticket-survey.php`  
**Affected Function:** POST submit handler  
**Affected Parameter:** `submit_survey`, `csrf_token`, answer fields

**Description:**  
Public survey submission remains gated by the unguessable survey URL token; POST now also requires a session CSRF token so third-party sites cannot forge submissions for a victim who already opened the survey link.

**Evidence (current — remediated):**

```php
if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid or expired form submission. Please refresh the page and try again.';
}
```

**Impact:** Reduced CSRF integrity risk on CSAT submissions; no authentication bypass.

**Recommendation:** Keep CSRF on all state-changing public forms that use a session. *(Remediated.)*

---

### ITM-PENTEST-014 Session cookie `Secure` flag conditional on HTTPS detection

**Status:** **Remediated** — `itm_session_cookie_secure()` forces `Secure` when `ITM_APP_URL` uses `https://`, with optional `ITM_SESSION_COOKIE_SECURE` override; otherwise falls back to per-request TLS detection.  
**Date updated:** 2026-09-02  
**Verification:** **Remediated** — expect **`[PASS]`** in `php scripts/verify_pentest_report.php`. `session_set_cookie_params()` and `itm_csrf_cookie_params()` use `itm_session_cookie_secure()` in `includes/itm_security_headers.php` (loaded from `config/config.php` before `session_start()`).

**Severity:** Low (was open; remediated)  
**OWASP Category:** A07:2021 – Identification and Authentication Failures  
**Affected Component:** Session bootstrap  
**Affected File:** `config/config.php`, `includes/itm_security_headers.php`  
**Affected Function:** `itm_session_cookie_secure()`, `session_set_cookie_params()`  
**Affected Parameter:** Cookie `secure` flag

**Description:**  
Previously `secure` was set only from inline HTTPS / `X-Forwarded-Proto` detection on each request. Misconfigured TLS termination could issue session cookies without `Secure`. Production installs should set `ITM_APP_URL` to an `https://` canonical URL so cookies always carry `Secure` regardless of proxy header drift.

**Evidence (current — remediated):**

```php
'secure' => itm_session_cookie_secure(),
```

**Impact:** Session cookies on HTTPS production deployments remain `Secure` when `ITM_APP_URL` is configured; local HTTP dev without `ITM_APP_URL` still omits `Secure`.

**Recommendation:** Set `ITM_APP_URL=https://…` in production `.env`; use `ITM_SESSION_COOKIE_SECURE=0` only for deliberate local HTTP testing. *(Remediated.)*

---

### ITM-PENTEST-015 Explorer API lacks application-level rate limiting

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — no `itm_api_enforce_rate_limit_or_exit()` call in `modules/explorer/api.php` (CSRF enforced on POST at lines **262–266** only). Regression: `php scripts/verify_pentest_report.php`.

**Severity:** Low  
**OWASP Category:** A04:2021 – Insecure Design  
**Affected Component:** File Explorer AJAX API  
**Affected File:** `modules/explorer/api.php`  
**Affected Function:** Entire router  
**Affected Parameter:** N/A

**Description:**  
Unlike `modules/knowledge_base/chat_api.php` and paid API gateways, Explorer `api.php` does not call `itm_api_enforce_rate_limit_or_exit()`. Authenticated users could abuse upload/list operations for DoS or storage exhaustion (partially mitigated by auth and upload validation).

**Evidence:** Grep for `itm_api_enforce_rate_limit` in `modules/explorer/api.php` — no matches; CSRF enforced on POST (lines 262–266).

**Impact:** Resource exhaustion within tenant storage quotas.

**Recommendation:** Per-user rate limits on upload/list; storage quotas per employee. *(Documentation only.)*

---

### ITM-PENTEST-016 Placeholder third-party API key in source

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — `define('MAILERLITE_API_KEY', 'YOUR_MAILERLITE_API_KEY_HERE')` at `config/config.php` line **98**. Regression: `php scripts/verify_pentest_report.php`.

**Severity:** Informational  
**OWASP Category:** A05:2021 – Security Misconfiguration  
**Affected Component:** Email fallback configuration  
**Affected File:** `config/config.php`  
**Affected Function:** Constant definition  
**Affected Parameter:** `MAILERLITE_API_KEY`

**Description:**  
`MAILERLITE_API_KEY` is defined as literal `YOUR_MAILERLITE_API_KEY_HERE`. Operational risk if replaced with a real key in tracked config instead of `.env`.

**Evidence:** Line 98 in `config/config.php`.

**Recommendation:** Remove literal from source; load only from environment. *(Documentation only.)*

---

### ITM-PENTEST-017 Positive: SQL injection static gate passes

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — expect **`[INFO]`** — `php scripts/check_sql_injection_coverage.php` exits `0` (invoked by `scripts/verify_pentest_report.php`); scanner source at `scripts/check_sql_injection_coverage.php`.

**Severity:** Informational (control effectiveness)  
**OWASP Category:** N/A — defensive control  
**Affected Component:** Codebase-wide  
**Affected File:** `scripts/check_sql_injection_coverage.php`  
**Evidence:** CLI run 2026-08-31: “Scanned 2643 PHP files and found no high-confidence direct-query findings.”

---

### ITM-PENTEST-018 Positive: CSRF static gate passes for module POST handlers

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — expect **`[INFO]`** — `php scripts/check_csrf_coverage.php` exits `0` (invoked by `scripts/verify_pentest_report.php`); scanner source at `scripts/check_csrf_coverage.php`.

**Severity:** Informational (control effectiveness)  
**OWASP Category:** N/A — defensive control  
**Affected Component:** Module POST handlers  
**Affected File:** `scripts/check_csrf_coverage.php`  
**Evidence:** CLI run 2026-08-31: exit 0; exemptions limited to CLI/QA scripts.

---

### ITM-PENTEST-019 Positive: Explorer upload and path controls

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — expect **`[INFO]`** — `get_full_path()` and `explorer_validate_upload_file()` defined in `modules/explorer/api.php`; upload hardening via `itm_ensure_files_storage_directory()` / `deny_http` policy per `AGENTS.md`.

**Severity:** Informational (control effectiveness)  
**OWASP Category:** N/A — defensive control  
**Affected Component:** Explorer  
**Affected File:** `modules/explorer/api.php`  
**Evidence:** `explorer_validate_upload_file()`, `get_full_path()` segment checks, `.htaccess` upload deny via `itm_ensure_files_storage_directory()`.

---

### ITM-PENTEST-020 Positive: Login and password-reset rate limiting

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — expect **`[INFO]`** — `itm_is_login_rate_limited()` in `login.php`; `itm_is_password_reset_rate_limited()` in `forgot-password.php` (also used from `reset-password.php`).

**Severity:** Informational (control effectiveness)  
**OWASP Category:** N/A — defensive control  
**Affected Component:** Authentication  
**Affected File:** `login.php`, `forgot-password.php`, `reset-password.php`  
**Evidence:** `itm_is_login_rate_limited()`, `itm_is_password_reset_rate_limited()` with IP and account thresholds.

---

### ITM-PENTEST-021 Positive: Session fixation mitigation on login

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — expect **`[INFO]`** — `session_regenerate_id(true)` after successful authentication at `login.php` line **194**.

**Severity:** Informational (control effectiveness)  
**OWASP Category:** N/A — defensive control  
**Affected Component:** Authentication  
**Affected File:** `login.php`  
**Evidence:** `session_regenerate_id(true)` after successful authentication (line 194).

---

### ITM-PENTEST-022 Positive: Hotel cancellation policy path allowlist (RCE mitigated)

**Date updated:** 2026-09-02  
**Verification:** **Confirmed** — expect **`[INFO]`** — `itm_hotel_booking_normalize_cancellation_policy_url()` extension allowlist `in_array($ext, ['html', 'htm', 'txt']` at `includes/itm_hotel_booking.php` line **1591** (blocks `..` sequences).

**Severity:** Informational (control effectiveness)  
**OWASP Category:** N/A — previously reported risk area  
**Affected Component:** Hotel booking  
**Affected File:** `includes/itm_hotel_booking.php`  
**Evidence:** `itm_hotel_booking_normalize_cancellation_policy_url()` rejects extensions other than `html`, `htm`, `txt` and blocks `..` sequences (lines 1588–1593).

---

## Attack Surface Summary

### 1. Pages (representative)

| Class | Examples |
|-------|----------|
| Auth | [login.php](http://localhost/it-management/login.php), [register.php](http://localhost/it-management/register.php), [forgot-password.php](http://localhost/it-management/forgot-password.php), [reset-password.php](http://localhost/it-management/reset-password.php), SSO entry points |
| Authenticated app | 272+ `modules/*/index.php` CRUD and bespoke UIs, [dashboard.php](http://localhost/it-management/dashboard.php), [user-config.php](http://localhost/it-management/user-config.php) |
| Guest hotel portal | `booking/*` (public portal bootstrap) |
| Public surveys | [ticket-survey.php](http://localhost/it-management/ticket-survey.php) |
| Short links | [go.php](http://localhost/it-management/go.php), `modules/short-url/go.php` |

### 2. Endpoints

- **REST / JSON:** `modules/api_v2/router.php` (PATH_INFO), `modules/hotel_booking_api/api.php`, `scripts/api.php`
- **Module APIs:** `modules/explorer/api.php`, `modules/tickets/api.php`, `modules/live_chat/api.php`, `modules/appointments/api.php`, `modules/problems/api.php`, `modules/notifications/api.php`, `modules/saved_report_views/api.php`, `modules/search/api.php`, `modules/network_discovery/api.php`, `modules/configuration_items/api.php`, `modules/ticket_sla_dashboard/api.php`
- **AJAX helpers:** `modules/select_options_api.php`, module `index.php?ajax_action=*` patterns (e.g. notes, todo)

### 3. Forms

- Standard CRUD POST forms across modules (CSRF token via `includes/header.php`)
- Login, registration, password reset, vault unlock, hotel booking checkout, ticket survey, short URL password gate

### 4. APIs

- **API v2:** Paid-tier scoped keys (`X-API-Key`), rate limits via `includes/itm_api_rate_limit.php`
- **Hotel distribution:** Channel API keys (hashed at rest), partner book/modify/cancel/ARI
- **Free tier:** Session-bound `scripts/api.php` integrations

### 5. AJAX endpoints

- Explorer file operations, select-options quick-add, notifications bell, live chat, org chart saves, rack planner autosave, roles-permissions matrix, company module access toggles

### 6. Authentication mechanisms

- Local bcrypt password (`password_verify`)
- Invitation-only registration
- LDAP (`sso-ldap.php`) and SAML (`sso-saml.php`) per company
- API keys on `ui_configuration` (tiered)
- Distribution channel keys, API v2 scoped keys
- Temporary share tokens (`share_sessions`), QR join codes, password-reset tokens, ticket survey tokens

### 7. Administrative functions

- [modules/settings/index.php](http://localhost/it-management/modules/settings/index.php), [modules/roles_permissions/index.php](http://localhost/it-management/modules/roles_permissions/index.php), [modules/company_module_access/index.php](http://localhost/it-management/modules/company_module_access/index.php), [modules/system_status/index.php](http://localhost/it-management/modules/system_status/index.php) (admin-only), script catalog [scripts/scripts.php?run=1](http://localhost/it-management/scripts/scripts.php?run=1)

### 8. File upload functionality

- Explorer (`modules/explorer/api.php` upload action)
- Ticket photos (`tickets_photos/`)
- Employee profile photos (`files/{company_id}/Private/.../profile/`)
- Floor plans, finance attachments, hotel room photos, import Excel via `table-tools.js`

### 9. Database interactions

- MySQLi prepared statements (enforced by convention and static audit)
- Audit triggers (`db/03_triggers.sql`) and `audit_logs`
- Multi-tenant scoping via `company_id` in bootstrap and module queries

### 10. External integrations

- MailerLite / Resend / tenant SMTP (`includes/itm_email.php`)
- Stripe webhooks ([booking/stripe-webhook.php](http://localhost/it-management/booking/stripe-webhook.php)) with signature verification
- IP2WHOIS, NVD API, hotel distribution webhooks (OTA/XML/JSON)
- LDAP/SAML identity providers
- Inbound IMAP email → tickets (CLI runner)

---

## Security Controls Reviewed

| Control | Status | Notes |
|---------|--------|-------|
| Prepared statements (static) | Effective | 2,643 files scanned, 0 high-confidence SQLi |
| CSRF on module POST | Effective | Static check pass; Explorer POST protected |
| Session cookie HttpOnly + SameSite=Lax | Effective | `config/config.php` |
| Session regeneration on login | Effective | `login.php` |
| Login / reset rate limiting | Effective | `attempts` table |
| Multi-tenant `company_id` gate | Effective | Central bootstrap; spot-check regressions exist |
| RBAC (`role_module_permissions`) | Effective | Module-level enforcement helpers |
| Company module access matrix | Effective | Opt-out per tenant |
| Upload directory hardening | Effective | `deny_http` / `upload` policies, dotfile blocks |
| Explorer path ACL | Effective | Prior cross-tenant profile photo issue remediated per `docs/security-validated-findings.md` |
| API v2 / distribution auth | Effective | Key required; scopes on v2 |
| Stripe webhook signatures | Effective | `itm_stripe_verify_webhook_signature()` |
| LDAP filter escaping | Effective | `ldap_escape()` in `itm_ldap_apply_user_filter()` |
| Chatbot XSS | Effective | `escapeHtml()` before `innerHTML` in `js/chatbot.js` |
| Security headers (CSP, XFO, HSTS) | **Effective** (bootstrap) | ITM-PENTEST-007 remediated via `itm_send_security_headers()` |
| Email approval workflow | **Improved** | ITM-PENTEST-001–003 remediated (env secret, POST+CSRF confirm, approver binding) |
| Secrets at rest (integration) | **Moderate** | DB_PASS-derived keys — ITM-PENTEST-005 |
| Error display defaults | **Effective** (default off) | ITM-PENTEST-006 remediated; admins may re-enable in Settings |

---

## Positive Security Findings

1. **Centralised security middleware** in `config/config.php` — authentication redirect, company selection, module access enforcement, disposable test-session rejection.
2. **Comprehensive static audit tooling** under `scripts/check_*.php` integrated with CI smoke workflow.
3. **Explorer defence-in-depth** — path normalisation, vault gate for Private folders, managed `.htaccess` on every storage segment, upload MIME inspection.
4. **Password storage** — bcrypt via `password_hash` / `password_verify` on login and registration.
5. **Host header controls** — optional `ITM_APP_URL` and `ITM_ALLOWED_HOSTS` to reduce poisoning of generated URLs.
6. **QR/share join rate limiting** — per-IP throttling in `includes/itm_qr_share.php`.
7. **Admin-only diagnostics** — `modules/system_status/index.php` and `scripts/system_status_phpinfo.php` require `itm_is_admin()`.
8. **CLI-only dev login bypass** — `scripts/bypass_login.php` rejects web SAPI and non-admin targets.

---

## Unresolved Questions

1. **Live HTTP verification** — No dynamic requests were sent to a running Apache/PHP instance in this engagement; header presence, TLS configuration, and `.htaccess` effectiveness depend on server deployment.
2. **Production `.env` values** — `ITM_MAINTENANCE_TOKEN`, `DB_PASS`, and `ITM_APP_URL` were not inspected on a live host (only `.env.example` in repo).
3. **WAF / reverse proxy** — Unknown whether nginx/Cloudflare adds security headers or rate limits in front of PHP.
4. **Extent of legacy `reset_token` plaintext rows** — Requires live DB query (`SELECT COUNT(*) FROM employees WHERE reset_token IS NOT NULL`).
5. **Full IDOR sweep** — Automated `scripts/verify_user_idor.php` and MBQA cover many modules; exhaustive manual IDOR testing of all 272 modules was not performed in this static pass.
6. **PHP dependency CVEs** — No Composer lockfile; PHP 7.4.33 runtime patch level on server not verified.

---

## Final Risk Assessment

**Overall rating: MEDIUM-HIGH** for a default deployment with seed data and factory UI settings; **MEDIUM** after mandatory credential rotation, rotating approval secrets, and hardening production ingress (verbose error display now defaults off per ITM-PENTEST-006).

The application demonstrates mature security **process** (static gates, regression scripts, upload hardening, RBAC layering) uncommon in procedural PHP codebases. Remaining **confirmed** high-impact risks are concentrated in **deployment hygiene** (default `Admin` password — ITM-PENTEST-004) and **DB-derived encryption keys** (ITM-PENTEST-005). Request Password email approvals (ITM-PENTEST-001–003), hardcoded approval secret, and verbose PHP error display defaults (ITM-PENTEST-006) were remediated.

Addressing ITM-PENTEST-004 should be prioritised before internet exposure. Defence-in-depth items (headers, rate limits, maintenance token policy) reduce blast radius from phishing and misconfiguration.

---

**Assessor note:** This report was produced by read-only analysis. No application source (except this file), configuration, database, or runtime data was modified during the assessment.
