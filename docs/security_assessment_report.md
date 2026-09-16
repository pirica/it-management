# Defensive Security Assessment Report: IT Management System

> **Scope:** Defensive architecture review and deployment hardening guidance — **not** the live penetration-test finding register.
>
> **Tracked findings / regression:** [`docs/report.md`](report.md) and `php scripts/verify_pentest_report.php` ([verify_pentest_report.php?run=1](http://localhost/it-management/scripts/verify_pentest_report.php?run=1), Administrator session). ITM-PENTEST-001–023.
>
> **Last reviewed:** 2026-09-16 (code paths, checklist, and **Live:** mitigation notes re-verified against `origin/master`).

## 1. Executive Summary

This defensive security assessment report provides a deep technical review of the IT Management System's security posture, architecture, configurations, and potential attack surfaces. Executed under strict ethical and defensive boundaries, the objective is to locate design weaknesses, evaluate data protection mechanisms, identify potential vulnerability patterns, and deliver concrete, actionable remediation advice for hardening.

The IT Management System is an enterprise-oriented, multi-company platform designed to handle resource planning, helpdesk ticketing, network configuration, hospitality bookings, employee credentials, personal productivity tools, and diagnostics. Because of the multi-tenant architecture (isolated by `company_id`) and high-security components (such as the Passwords Vault and Private Contacts, both encrypted at rest), any flaw in isolation boundaries, access controls, input sanitization, or file handling could lead to significant operational risks.

Overall, the repository displays high-quality static analysis and coverage check tooling (CSRF checkers, SQLi static analyzers, multi-tenant leakage scans, production hardening gates) which significantly minimizes standard vulnerability classes. The **high-severity code paths** called out in earlier revisions of this document (cancellation-policy RCE, Explorer `.htaccess` upload bypass, PowerShell argument injection) are **mitigated in the current tree**. Remaining items are mostly **ongoing engineering discipline** (tenant scoping on every new AJAX handler, XSS conventions, password complexity outside the force-change gate) and **one residual XML hardening gap** in the News RSS parser.

### 1.1 Review snapshot (2026-09-16)

| Area | Verdict | Evidence |
|------|---------|----------|
| Cancellation policy RCE (§3.1) | **Mitigated** | Extension allowlist + `booking/cancellation_policy/.htaccess`; `php scripts/verify_hotel_booking.php` |
| Explorer `.htaccess` RCE (§3.1) | **Mitigated** | Dotfile upload block + managed `deny_http` overwrite; `php scripts/verify_explorer_rce_htaccess.php` |
| PowerShell injection (§3.5) | **Mitigated** | Hardware action allowlist in `itm_system_status_run_powershell_action()`; `php scripts/verify_system_status.php` |
| Default seed credentials (§3.4) | **Mitigated** (dev seeds remain) | `must_change_password` gate; `php scripts/verify_force_password_change.php` |
| Verbose error / log exposure (§4.1) | **Mitigated** | Default `enable_all_error_reporting = 0`; log path `docs/error_log.txt` via `itm_error_log_file_path()`; `docs/.htaccess` denies HTTP |
| SQLi static gate (§3.2) | **Passing** (discipline ongoing) | `bash scripts/smoke_test.sh` → `check_sql_injection_coverage.php` |
| Multi-tenant BAC (§3.3) | **Partial** | Central bootstrap + module guards; `php scripts/check_multi_tenant_leaks.php` (exit 0, heuristic warnings) |
| RSS XXE hardening (§5.2) | **Partial** | `news_parse_rss_items()` lacks explicit entity-loader / `LIBXML_NONET` flags |
| Production go-live gate | **Available** | `php scripts/check_prod_hardening.php` ([check_prod_hardening.php?run=1](http://localhost/it-management/scripts/check_prod_hardening.php?run=1), Administrator session) |

Static verification run during this review: `bash scripts/smoke_test.sh` — **pass** (PHP lint, CSRF, SQLi, FK label search). Database-backed regressions (`verify_pentest_report.php`, `verify_hotel_booking.php`, Explorer RCE PoC) require a live MySQL instance.

---

## 2. Reconnaissance

### 2.1 Technologies & Frameworks
- **Backend Language**: PHP 7.4.33. The system is written in legacy-style procedural PHP without modern MVC or Object-Relational Mapping (ORM) frameworks. It relies on a flat, modular file hierarchy.
- **Database Engine**: MySQL 8.0+.
- **Database Connector**: Strictly MySQLi. No PHP Data Objects (PDO) are utilized.
- **Frontend Assets**: Vanilla JavaScript and custom CSS (`css/styles.css`). There are no heavy JavaScript frameworks (such as React, Vue, or Angular) in the core application.
- **Dependencies**: The application deliberately avoids a package manager like Composer or npm, maintaining zero external package dependencies to eliminate standard supply chain risks.

### 2.2 System Architecture
The application uses a multi-company data model. Data isolation is maintained programmatically by enforcing `company_id` filters in SQL queries.

The architecture consists of:
- **Core Bootstrapper (`config/config.php`)**: Establishes the database connection, configures sessions, manages audit logs, resolves multi-tenancy, and loads helper libraries.
- **Shared Helpers (`includes/`)**: Houses core logic such as email utilities, role-based access control (RBAC), UI layout engine, date formatters, and encryption wrappers.
- **Feature Modules (`modules/`)**: Folder-based modules (e.g., `modules/employees/`, `modules/explorer/`, `modules/passwords/`) implementing standard CRUD (Create, Read, Update, Delete) entry files (`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`).
- **Support Scripts (`scripts/`)**: Audits, regressions, diagnostics, and CLI tools that execute either under SAPI CLI or via an Admin browser session.
- **Public Guest Portal (`booking/`)**: The guest-facing hotel reservation portal that operates without standard employee authentication.
- **Distribution API (`modules/hotel_booking_api/`)**: Partner channel manager integration API operating via JSON and OpenTravel XML.

### 2.3 Attack Surface Mapping
The primary attack surfaces identified across the application are:
1. **Authenticated Intranet Features**: Standard staff-facing pages under `modules/` where parameters (`id`, search strings, sort criteria) are processed.
2. **Public-Facing Endpoints**:
   - The `/booking/` guest portal.
   - Public join pages such as `/booking/users/bookings.php`, `/modules/private_contacts/join.php`, and `/modules/notes/join.php`.
   - The Partner API endpoint `/modules/hotel_booking_api/api.php` and its channel actions (`probe`, `availability`, `book`, etc.).
3. **File Upload Functions**:
   - Multi-photo upload in helpdesk tickets (`tickets_photos/`).
   - Profile photo upload in employees (`files/{company_id}/Private/{username}_{id}/profile/`).
   - AutoCAD, PDF, and image uploads in Floor Plans (`floor_plans/`).
   - Arbitrary document uploads in the secure File Explorer (`files/`).
4. **Select Options / Quick-Add API (`modules/select_options_api.php`)**: An AJAX handler allowing quick record creation during form entry (e.g., adding a new department or manufacturer on the fly).
5. **System Diagnostics (`modules/system_status/`)**: Admin-only server diagnostics calling PowerShell scripts or reading `/proc/` directories.
6. **Maintenance & Audit Scripts (`scripts/`)**: CLI/Browser diagnostic tools.

---

## 3. Vulnerability Discovery

### 3.1 Unsafe File Operations & Remote Code Execution (RCE)
#### Finding: Rate Plans Cancellation Policy URL Upload
- **Severity**: High (historical) — **mitigated in current code**
- **Description**: The system includes a custom cancellation policy generation mechanism where administrators can input a `cancellation_policy_url` (such as `cancellation_policy/standard.html`) and arbitrary `cancellation_policy_html` content.
- **Risk**: Without extension validation, an attacker could write a `.php` file under `booking/cancellation_policy/` and achieve code execution when the web server executes PHP in that path.
- **Theoretical Abuse Scenario**: An authenticated malicious admin sets URL `cancellation_policy/shell.php` with payload `<?php system($_GET['cmd']); ?>`, then browses to `booking/cancellation_policy/shell.php?cmd=whoami`.
- **Status (2026-09-16):** **Not exploitable** via the documented path. Relative URLs must end in `.html`, `.htm`, or `.txt`; `..` segments are rejected. Guest checkout prefers `booking/cancellation-policy.php` (DB HTML) over static files alone.
- **Mitigation (live)**:
  1. **Live:** `itm_hotel_booking_normalize_cancellation_policy_url()` in `includes/itm_hotel_booking.php` returns empty for other relative extensions (regression: `php scripts/verify_hotel_booking.php`).
  2. Path traversal blocked (`..` rejected).
  3. **Live:** `booking/cancellation_policy/.htaccess` denies PHP/CGI execution under that folder.
  4. Policy HTML body is embedded in a static HTML document wrapper; stored XSS in `.html` files remains a separate concern for admins who control rate-plan content.

#### Finding: Upload Directory Hardening Bypass (RCE via `.htaccess` Overwrites)
- **Severity**: High (historical) — **mitigated in current code**
- **Description**: The secure File Explorer allows uploads to `files/{company_id}/`. While HTTP access is restricted by `deny_http` (`RewriteRule ^ - [F]`), an attacker who could upload `.htaccess` might try to weaken directory rules.
- **Risk**: User-controlled `.htaccess` persisting on disk could weaken rewrite rules on misconfigured hosts.
- **Theoretical Abuse Scenario**: Upload `.htaccess` allowing CGI execution, then upload a script in the same folder.
- **Status (2026-09-16):** **Not exploitable** via Explorer upload UI — dotfiles (including `.htaccess`) are rejected; ensure helpers restore canonical policy on every path segment.
- **Mitigation (live)**:
  1. **Live:** `itm_ensure_upload_directory()` / `itm_ensure_files_storage_directory()` force-overwrite managed `.htaccess` on every ensure (`itm_upload_directory_policy_body()` in `includes/bootstrap_helpers.php`).
  2. **Live:** `explorer_validate_upload_file()` rejects leading-dot names; `explorer_is_hidden_system_entry()` hides managed placeholders from listings. Regressions: `php scripts/verify_explorer_rce_htaccess.php`, `php scripts/verify_explorer_rce_marker.php`.
  3. **Deployment:** Prefer `AllowOverride None` or minimal `AllowOverride List` on upload trees in `httpd.conf` / `apache2.conf`.

---

### 3.2 SQL Injection (SQLi)
#### Finding: Parameter Concatenation in Dynamic Queries
- **Severity**: High (process risk) — **static gate passing**
- **Description**: Legacy procedural modules may concatenate sort directions or identifiers. The static checker passes because prepared statements are widespread, but new code can reintroduce risk.
- **Risk**: Unsanitized `ORDER BY` direction or dynamic table/column names enable SQLi.
- **Theoretical Abuse Scenario**: `SELECT * FROM racks ORDER BY name $direction` with `$direction` from `$_GET['dir']` without whitelisting.
- **Status (2026-09-16):** **No high-confidence findings** in `php scripts/check_sql_injection_coverage.php` (smoke step 3). Remains an **ongoing discipline** requirement for new modules.
- **Mitigation**:
  1. Never interpolate user data into SQL — use `mysqli_prepare`.
  2. For structural fragments, use `itm_is_safe_identifier()` and whitelist sort direction (`DESC` / `ASC` only).

---

### 3.3 Multi-Tenant Data Leakage & Broken Access Control (BAC)
#### Finding: Incomplete `company_id` Enforcement in Background AJAX/APIs
- **Severity**: Medium — **partial / ongoing**
- **Description**: Background APIs (e.g. `select_options_api.php`, IDF visualizers, bespoke AJAX actions) accept numeric row IDs. Missing tenant predicates allow cross-company read or mutation.
- **Risk**: Company 1 user tampering `id` accesses Company 2 rows.
- **Theoretical Abuse Scenario**: `delete.php?id=100` deletes a row without `AND company_id = ?`.
- **Status (2026-09-16):** **Partially addressed.** `select_options_api.php` scopes lookups and inserts by `company_id` when the table is company-scoped. `php scripts/check_multi_tenant_leaks.php` exits **0** but still prints heuristic “needs review” rows (e.g. webmail helpers) for manual triage — not a blanket allowlist bypass.
- **Mitigation**:
  1. Append `AND company_id = ?` (session tenant) on every lookup, update, and delete.
  2. Use audit triggers where appropriate.
  3. Run `php scripts/check_multi_tenant_leaks.php`, `php scripts/repro_bac.php`, and `php scripts/repro_cross_tenant_admin.php` after CRUD/AJAX changes.

---

### 3.4 Hardcoded Secrets & Dangerous Defaults
#### Finding: Repository-Level Default Credentials
- **Severity**: Low — **mitigated for production login path**
- **Description**: Fresh imports seed `Admin` / `Admin2`–`Admin5` with password `Admin` in `db/02_data.sql` (dev/MBQA convenience).
- **Risk**: Public deployments left on default credentials.
- **Theoretical Abuse Scenario**: Internet scan finds login portal; `Admin` / `Admin` succeeds.
- **Status (2026-09-16):** **Mitigated** for password login — `employees.must_change_password = 1` on seed admins and demo users forces `force-password-change.php` before portal access (SSO and optional `ITM_SKIP_FORCE_PASSWORD_CHANGE=1` excepted). Seeds remain in SQL for fresh imports; `php scripts/check_prod_hardening.php` can fail when canonical passwords still verify under `APP_ENV=production`.
- **Mitigation (live)**:
  1. **Live:** `includes/itm_force_password_change.php` + `force-password-change.php`; regression: `php scripts/verify_force_password_change.php` ([verify_force_password_change.php?run=1](http://localhost/it-management/scripts/verify_force_password_change.php?run=1)). Posture: ITM-PENTEST-004-mitigation in `docs/report.md`.
  2. Force-change flow enforces minimum length (8) and blocks password equal to username; universal complexity rules are **not** enforced on all password change paths (see checklist).
  3. **Live:** Database credentials load from project-root `.env` via `itm_load_dotenv_file()` in `config/config.php`.

---

### 3.5 PowerShell Script Injection (Windows-specific Hardware Diagnostics)
#### Finding: Command Argument Injection in System Status
- **Severity**: Medium (historical) — **mitigated in current code**
- **Description**: Windows hardware metrics invoke `includes/*.ps1` via `shell_exec`.
- **Risk**: User-controlled arguments interpolated into shell commands enable command injection.
- **Theoretical Abuse Scenario**: `shell_exec("powershell.exe -File disk_usage.ps1 -Drive " . $_GET['drive'])` with malicious `drive` value.
- **Status (2026-09-16):** **Not exploitable** — no user input reaches the shell; action names are allowlisted and matched with `[a-z0-9_]+` before loading `includes/{action}.ps1`.
- **Mitigation (live)**:
  1. **Live:** `itm_system_status_run_powershell_action()` in `includes/itm_system_status_powershell.php`; regression: `php scripts/verify_system_status.php`.
  2. `escapeshellarg()` used on the PowerShell binary and script path.

---

## 4. Data Exposure Analysis

### 4.1 Debug & Error Logs
When **Settings → UI Configuration → enable all error reporting** is on (`enable_all_error_reporting = 1` for the signed-in employee), `config/config.php` enables `display_errors` and writes PHP errors to **`docs/error_log.txt`** via `itm_error_log_file_path()` in `includes/bootstrap_helpers.php`. The schema default for new `ui_configuration` rows is **`0`** (`db/01_schema.sql`); admins may re-enable per employee for troubleshooting.

#### Exposure Risks
1. **Browser stack traces**: When enabled, PHP errors may render in the browser for that employee's requests.
2. **Log file readability**: Verbose logs may contain SQL fragments, paths, or session-related context.
3. **Legacy root log**: An old `error_log.txt` at the repository root (pre-migration) may still be web-reachable if left on disk.

#### Mitigation (live)
1. **Live:** Verbose logs write to `docs/error_log.txt`, not the web root. **`docs/.htaccess`** denies HTTP access to `error_log*.txt` via `<FilesMatch "^error_log(-[0-9]+)?\.txt$">`.
2. **Live:** Default `enable_all_error_reporting` is **off** (schema, seeds, `itm_ui_config_defaults()`, `config.php` `?? 0` fallback). See ITM-PENTEST-006 in `docs/report.md`.
3. **Deployment:** Run `php scripts/check_prod_hardening.php` before go-live — fails under `APP_ENV=production` when legacy root `error_log.txt` exists, seed passwords still verify, or any `ui_configuration` row keeps error reporting on.
4. **Production `php.ini`:** Keep `display_errors=Off` globally; rely on server error logs outside the docroot where possible.

### 4.2 Directory Listing
If the web server does not have directory listing disabled, any directory without a default index file (like `index.php`) will expose its files.

#### Exposure Risks
Exposing folders like `backups/`, `files/`, or `images/` can leak database backups, corporate files, and sensitive tickets media.

#### Mitigation
1. As mandated by the application's security checks, **every single folder** in the repository must contain an empty `index.html` file to act as a directory listing fallback (`php scripts/empty_folders.php`).
2. The root and folder-level `.htaccess` configurations must explicitly disable index generation:
   ```apache
   Options -Indexes
   ```

---

## 5. Dependency & Supply Chain Risks

### 5.1 Outdated Libraries
Because the IT Management System is constructed using procedural PHP and does not utilize a dependency manager like Composer, it is largely exempt from standard automated package CVE scans.

However, custom or third-party client-side scripts included manually (such as `js/table-tools.js` or older versions of Chart.js) can fall out of date and introduce client-side vulnerabilities.

### 5.2 Third-Party Scripts & Module Integrity
#### Exposure Risks
1. **Client-side XSS**: Outdated utility scripts can contain vulnerabilities that allow Cross-Site Scripting (XSS) or DOM manipulation.
2. **Untrusted Feeds**: The News module (`includes/itm_news_feed.php`) fetches external RSS/Atom XML. Insecure parsing could enable XML External Entity (XXE) attacks against malicious feed content.

#### Mitigation
1. Maintain an inventory of all third-party client-side libraries. Periodically check and update them to their latest stable releases.
2. **RSS / XXE (partial gap):** `news_parse_rss_items()` calls `simplexml_load_string()` with `LIBXML_NOCDATA` only. On PHP 7.4, add explicit hardening before parse:
   ```php
   libxml_disable_entity_loader(true);
   $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
   ```
   Feeds are predominantly fixed Microsoft URLs from `news_feed_source_catalog()`, which limits practical exposure but does not replace parser hardening.

---

## 6. Infrastructure & Operational Security

### 6.1 Deployment Risks
Deploying legacy procedural systems with multiple entry points requires robust server-level access control. If the server does not enforce tight separation between public and private entry points, attackers can access diagnostic or administrative files directly.

#### Mitigation
1. Block access to administrative folders or internal scripts at the web server layer (e.g. in Apache `<Directory>` blocks) for non-VPN IP ranges.
2. Omit the `db/` schema bundle from production docroots where possible. **Live:** browser access to `scripts/debug.php` requires an **Administrator** session via `itm_script_require_admin_script_or_exit()` — non-admins receive “Access denied.” CLI (`php scripts/debug.php`) has no web gate; restrict shell access on production hosts.
3. **Live:** Pre-deploy gate `php scripts/check_prod_hardening.php` ([check_prod_hardening.php?run=1](http://localhost/it-management/scripts/check_prod_hardening.php?run=1)) aggregates seed-password, error-reporting, legacy log, env-flag, and `bypass_login.php` reachability checks when `APP_ENV=production`.

### 6.2 Secrets Management
Standard development patterns often result in database credentials or API keys being hardcoded into `config/config.php` or other library files.

#### Mitigation
1. **Live:** Use project-root `.env` (gitignored) loaded by `itm_load_dotenv_file()` — see `docs/ENV.md`.
2. **Live:** Root `.htaccess` denies HTTP reads of `.env` (`<Files ".env">` → `Require all denied`).

### 6.3 Directory & File Permissions
Insecure folder permissions (e.g., `777` on Linux hosts) on writable directories like `images/`, `files/`, or `backups/` allow local users or compromised processes to modify system files.

#### Mitigation
1. Writable directories must have their permissions limited to the minimum required. For typical Linux Apache setups, use `755` for directories and `644` for files, with ownership assigned to the web service account (e.g. `www-data`).
2. Run standard filesystem audits regularly to identify folders with loose permissions.

---

## 7. Exploitation Scenarios (Ethical Overview)

To assist defensive teams in visualizing threats, this section outlines theoretical attack flows. Steps marked **blocked** reflect current repository controls.

### 7.1 Scenario A: Remote Code Execution via Cancellation Policy Uploads
1. **Initial Access**: An attacker compromises an administrative account or leverages an active session.
2. **Reconnaissance**: The attacker navigates to Portal Rate Plans and notes that custom cancellation policy pages can be written to disk.
3. **Payload Delivery**: The attacker sets URL to `cancellation_policy/test.php` and inserts a PHP payload in the HTML editor.
4. **Execution**: **Blocked** — `itm_hotel_booking_normalize_cancellation_policy_url()` rejects `.php`; even if a file were written, `booking/cancellation_policy/.htaccess` denies PHP execution.
5. **Defensive Control**: Extension allowlist (`.html`, `.htm`, `.txt`) + folder `.htaccess` + guest route `cancellation-policy.php` for DB-backed HTML.

### 7.2 Scenario B: Cross-Tenant Data Harvesting via ID Tampering
1. **Initial Access**: A malicious employee logs in under Company 1.
2. **Tampering**: The user captures an AJAX request using a plain integer `id`.
3. **Exploitation**: The user increments `id`. Outcome depends on the handler — well-scoped modules return empty or 403; gaps are caught by `check_multi_tenant_leaks.php` heuristics and BAC repro scripts.
4. **Defensive Control**: `AND company_id = ?` on lookups; company module access and RBAC as secondary layers.

---

## 8. Mitigation & Hardening Guidance

### 8.1 Input Sanitization & XSS Prevention
- Ensure all dynamic variables echoed inside HTML context are wrapped in `sanitize()` or `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- In Javascript, avoid direct insertion of user strings into the DOM via `.innerHTML`. Always use `.textContent` or run a robust HTML escaping helper like `escapeHtml()` (see `js/chatbot.js`).

### 8.2 Directory Protection Rules (Apache Hardening)

Canonical managed `.htaccess` bodies are defined in `includes/bootstrap_helpers.php` (`itm_upload_directory_policy_body()`). Upload helpers force-overwrite policy files on every ensure — do not hand-edit upload-tree `.htaccess` files. The snippets below are human-readable reference only.

Enforce the following `.htaccess` rules in writable upload directories:

#### For `images/`, `tickets_photos/`, `floor_plans/`:
Ensure PHP engines are disabled and executable file requests are blocked:
```apache
Options -Indexes -ExecCGI -MultiViews
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<FilesMatch "(?i)\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|asp|aspx|jsp|sh|exe|bat|cmd)$">
    Require all denied
</FilesMatch>
```

#### For `backups/`:
Deny all HTTP requests completely:
```apache
Options -Indexes -ExecCGI
Require all denied
```

#### For `files/` (Secure Multi-Tenant Storage):
Force absolute HTTP access denial, routing all asset views exclusively through the PHP secure proxy:
```apache
RewriteEngine On
RewriteRule ^ - [F]
Options -Indexes -ExecCGI
```

#### For `docs/error_log.txt` (verbose PHP debug log):
When error reporting is enabled for an employee, block direct HTTP reads of log files under `docs/`:
```apache
<FilesMatch "^error_log(-[0-9]+)?\.txt$">
    Require all denied
</FilesMatch>
```
**Live:** present in `docs/.htaccess`.

---

## 9. Final Hardening Checklist

Status key: `[x]` implemented in repository · `[~]` partial / ongoing discipline · `[ ]` deployment or policy gap.

| Domain | Hardening Action | Status | Evidence |
|--------|------------------|--------|----------|
| **Secrets** | Move database credentials and API tokens out of source files to `.env`. | [x] | `config/config.php` — `itm_load_dotenv_file()`; `docs/ENV.md` |
| **Secrets** | Deny HTTP access to `.env` files in root `.htaccess`. | [x] | Root `.htaccess` — `<Files ".env">`; regression ITM-PENTEST-023 |
| **Filesystem** | Place an empty `index.html` file in *every* directory under the repository. | [x] | `itm_upload_directory_empty_index_html()`, `php scripts/empty_folders.php` |
| **Filesystem** | Implement the `upload` policy `.htaccess` in `images/` and `tickets_photos/`. | [x] | `itm_ensure_upload_directory()` / `itm_upload_directory_policy_body('upload')` |
| **Filesystem** | Implement the `deny_all` policy `.htaccess` in `backups/`. | [x] | `itm_upload_directory_policy_body('deny_all')` |
| **Filesystem** | Implement the `deny_http` policy `.htaccess` in `files/`. | [x] | `itm_ensure_files_storage_directory()` / Explorer ensure chain |
| **Filesystem** | Route verbose PHP logs outside web root; deny HTTP on log files. | [x] | `itm_error_log_file_path()` → `docs/error_log.txt`; `docs/.htaccess` `<FilesMatch>` |
| **Database** | Ensure every single SQL query enforces `company_id` multi-tenant boundaries. | [~] | Central bootstrap + module discipline; `php scripts/check_multi_tenant_leaks.php`, `docs/report.md` |
| **Database** | Implement parameterized queries for all user inputs; avoid query concatenation. | [x] | `php scripts/check_sql_injection_coverage.php` (smoke) |
| **Authentication** | Force password change on first login for seeded default accounts. | [x] | `employees.must_change_password`, `force-password-change.php`, `verify_force_password_change.php` |
| **Authentication** | Enforce strong password complexity rules for administrative and user accounts. | [~] | 8-char + not-equal-to-username on force-change only; not universal |
| **Authorization** | Validate CSRF tokens (`itm_require_post_csrf()`) on all state-changing POST requests. | [x] | `php scripts/check_csrf_coverage.php` |
| **XSS** | Wrap all echoed variables in HTML output in `sanitize()`. | [~] | Convention across modules; `escapeHtml()` in `js/chatbot.js`; not 100% automated |
| **XML** | Harden RSS/Atom parsing against XXE (News module). | [~] | `news_parse_rss_items()` — add `libxml_disable_entity_loader` + `LIBXML_NONET` |
| **Operations** | Restrict diagnostic scripts and schema files on production deployments. | [~] | Admin browser gate on `scripts/debug.php`; CLI unrestricted; `check_prod_hardening.php` |
| **Operations** | Remove legacy root `error_log.txt` before production. | [x] | `itm_prod_hardening_check_error_log_web_root()` flags file at repo root |
