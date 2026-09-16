# Defensive Security Assessment Report: IT Management System

> **Scope:** Defensive architecture review and deployment hardening guidance — **not** the live penetration-test finding register.
>
> **Tracked findings / regression:** [`docs/report.md`](report.md) and `php scripts/verify_pentest_report.php` ([verify_pentest_report.php?run=1](http://localhost/it-management/scripts/verify_pentest_report.php?run=1), Administrator session). ITM-PENTEST-001–023.
>
> **Last reviewed:** 2026-09-16.

## 1. Executive Summary

This document describes the IT Management System attack surface, **ongoing** defensive discipline, and production hardening expectations. Closed code paths (cancellation-policy RCE, Explorer `.htaccess` upload bypass, PowerShell argument injection, default-credential login without rotation, root web-exposed error logs) are **not** repeated here — verify them via the regression scripts in **§5** and the pentest register in `docs/report.md`.

Remaining focus areas:

- **Multi-tenant isolation** on every AJAX/CRUD handler (`company_id` predicates).
- **Convention-based XSS** (no full-repo automated gate).
- **Password complexity** outside the first-login force-change flow.
- **RSS XML parsing** hardening in the News module (partial).
- **Deployment hygiene** (docroot layout, VPN/network rules, `APP_ENV=production` gates).

Pre-deploy gate: `php scripts/check_prod_hardening.php` ([check_prod_hardening.php?run=1](http://localhost/it-management/scripts/check_prod_hardening.php?run=1), Administrator session).

---

## 2. Architecture & Attack Surface

### 2.1 Stack
- **PHP 7.4.33**, procedural modules, **MySQLi** (no PDO), **MySQL 8.0+**.
- **Frontend:** vanilla JS + `css/styles.css` (no Composer/npm app dependencies).

### 2.2 Layout
| Layer | Role |
|-------|------|
| `config/config.php` | DB, session, tenant bootstrap, UI config |
| `includes/` | RBAC, encryption, email, upload policies |
| `modules/` | Flat CRUD entry files per feature |
| `scripts/` | Audits, regressions, CLI/browser diagnostics |
| `booking/` | Guest hotel portal (unauthenticated) |
| `modules/hotel_booking_api/` | Partner distribution API (API key) |

### 2.3 Primary surfaces
1. Authenticated `modules/` — `id`, search, sort, POST mutations.
2. Public — `booking/`, `modules/*/join.php`, `modules/hotel_booking_api/api.php`.
3. Uploads — `tickets_photos/`, `floor_plans/`, `files/{company_id}/` (Explorer).
4. AJAX — `modules/select_options_api.php` and module-specific APIs.
5. Admin diagnostics — `modules/system_status/`, `scripts/` (browser + CLI).

Managed upload policies (`upload`, `deny_http`, `deny_all`) live in `includes/bootstrap_helpers.php` (`itm_upload_directory_policy_body()`). Do not hand-edit upload-tree `.htaccess` files.

---

## 3. Ongoing Risks & Discipline

### 3.1 Multi-tenant data leakage (BAC)
- **Severity:** Medium — **partial / ongoing**
- **Risk:** Handlers that load or mutate rows by numeric `id` without `AND company_id = ?` (session tenant) can leak or change another company's data.
- **Practice:**
  - Scope every lookup, update, and delete by `company_id`.
  - After CRUD/AJAX changes, run `php scripts/check_multi_tenant_leaks.php`, `php scripts/repro_bac.php`, and `php scripts/repro_cross_tenant_admin.php`.
- **Note:** `select_options_api.php` scopes company-scoped tables; the leak checker may still flag heuristic “needs review” rows for manual triage.

### 3.2 SQL injection (process discipline)
- **Severity:** High if introduced — **static gate currently passing**
- **Risk:** New modules that interpolate `ORDER BY` direction, column names, or user input into SQL.
- **Practice:** Prepared statements (`mysqli_prepare`); structural fragments via `itm_is_safe_identifier()` and `DESC`/`ASC` whitelist only.
- **Gate:** `php scripts/check_sql_injection_coverage.php` (smoke step 3).

### 3.3 XSS (convention)
- **Severity:** Medium — **partial**
- **Risk:** Unescaped output in PHP or `.innerHTML` in JS.
- **Practice:** `sanitize()` / `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` in PHP; `escapeHtml()` or `.textContent` in JS (see `js/chatbot.js`). No repository-wide automated XSS audit.

### 3.4 Password policy (beyond force-change)
- **Severity:** Low — **partial**
- **Risk:** Weak passwords after the mandatory first-login rotation (8 characters minimum; cannot equal username on force-change only).
- **Practice:** Enforce stronger rules on register, reset, and voluntary password changes if policy requires it. Seeds in `db/02_data.sql` remain for dev/MBQA — `check_prod_hardening.php` can fail when canonical passwords still verify under `APP_ENV=production`.

### 3.5 RSS / XXE (News module)
- **Severity:** Low–Medium — **partial**
- **Risk:** `news_parse_rss_items()` in `includes/itm_news_feed.php` uses `simplexml_load_string()` with `LIBXML_NOCDATA` only.
- **Recommended hardening:**
  ```php
  libxml_disable_entity_loader(true);
  $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
  ```
- Feeds are mostly fixed Microsoft URLs from `news_feed_source_catalog()`, which limits exposure but does not replace parser flags.

### 3.6 Verbose error reporting (operator toggle)
- **Severity:** Low when misused — **controls in place; discipline required**
- Default `enable_all_error_reporting` is **off** (`db/01_schema.sql`, Settings UI). When an admin enables it, `display_errors` runs for that employee and logs to `docs/error_log.txt` (`itm_error_log_file_path()`), with HTTP denied by `docs/.htaccess`. Remove any legacy `error_log.txt` at the repository root before production.

---

## 4. Infrastructure & Deployment

| Topic | Guidance |
|-------|----------|
| **Ingress** | Restrict `scripts/`, `db/`, and admin modules at Apache/nginx for non-VPN clients. |
| **Docroot** | Do not serve `db/` or `.env` from the web root; root `.htaccess` denies `.env`. |
| **Diagnostics** | Browser `scripts/debug.php` — Admin session only; CLI has no web gate — restrict shell. |
| **Secrets** | `.env` via `itm_load_dotenv_file()` — see `docs/ENV.md`. |
| **Permissions** | Writable trees (`images/`, `files/`, `backups/`) — `755` dirs / `644` files, `www-data` ownership on Linux. |
| **Go-live** | `php scripts/check_prod_hardening.php` when `APP_ENV=production`. |
| **Directory listing** | `Options -Indexes` + empty `index.html` on every folder (`php scripts/empty_folders.php`). |

### Apache reference (managed policies)

Canonical bodies: `itm_upload_directory_policy_body()` in `includes/bootstrap_helpers.php`. Human-readable snippets:

**`images/`, `tickets_photos/`, `floor_plans/` (`upload`):** disable PHP engine; block script extensions.

**`backups/` (`deny_all`):** `Require all denied`.

**`files/` (`deny_http`):** `RewriteRule ^ - [F]` — assets only via `modules/explorer/file.php`.

**`docs/error_log*.txt`:** `<FilesMatch "^error_log(-[0-9]+)?\.txt$">` → `Require all denied` (live in `docs/.htaccess`).

---

## 5. Regression & Verification Scripts

Use these to confirm controls — not duplicated as narrative findings in this doc.

| Area | Command |
|------|---------|
| Smoke (lint, CSRF, SQLi, FK search) | `bash scripts/smoke_test.sh` |
| Multi-tenant heuristics | `php scripts/check_multi_tenant_leaks.php` |
| Production posture | `php scripts/check_prod_hardening.php` |
| Pentest register ITM-PENTEST-001–023 | `php scripts/verify_pentest_report.php` |
| Hotel booking (incl. cancellation policy allowlist) | `php scripts/verify_hotel_booking.php` |
| Explorer upload / `.htaccess` | `php scripts/verify_explorer_rce_htaccess.php` |
| Force password change | `php scripts/verify_force_password_change.php` |
| System Status PowerShell allowlist | `php scripts/verify_system_status.php` |
| CSRF coverage | `php scripts/check_csrf_coverage.php` |

---

## 6. Hardening Checklist (open items)

Status: `[~]` partial / ongoing discipline · `[ ]` deployment or policy gap.

| Domain | Action | Status | Evidence |
|--------|--------|--------|----------|
| **Database** | `company_id` on every query that reads or mutates tenant data | [~] | `php scripts/check_multi_tenant_leaks.php` |
| **Authentication** | Strong password rules on all change paths (not only force-change) | [~] | `itm_force_password_change_validate_new_password()` scope |
| **XSS** | Consistent `sanitize()` / `escapeHtml()` | [~] | Module convention; no static gate |
| **XML** | XXE-safe RSS parse flags | [~] | `includes/itm_news_feed.php` → `news_parse_rss_items()` |
| **Operations** | Network/docroot hardening; no `db/` in prod docroot | [~] | `check_prod_hardening.php`; Apache `<Directory>` |
| **Operations** | Keep `enable_all_error_reporting` off in production | [~] | Settings UI; `check_prod_hardening.php` |
| **Supply chain** | Periodic review of vendored client JS | [~] | Manual inventory (`js/table-tools.js`, Chart.js, etc.) |

**Implemented controls** (CSRF gate, SQLi static gate, upload `.htaccess` policies, `.env` HTTP deny, seed password rotation gate, `docs/error_log.txt` path + deny rule, Explorer dotfile block, cancellation-policy extension allowlist, System Status PowerShell allowlist) — confirm via **§5** scripts; do not re-audit from this document.
