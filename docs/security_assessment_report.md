# Defensive Security Assessment Report: IT Management System

## 1. Executive Summary

This defensive security assessment report provides a deep technical review of the IT Management System's security posture, architecture, configurations, and potential attack surfaces. Executed under strict ethical and defensive boundaries, the objective is to locate design weaknesses, evaluate data protection mechanisms, identify potential vulnerability patterns, and deliver concrete, actionable remediation advice for hardening.

The IT Management System is an enterprise-oriented, multi-company platform designed to handle resource planning, helpdesk ticketing, network configuration, hospitality bookings, employee credentials, personal productivity tools, and diagnostics. Because of the multi-tenant architecture (isolated by `company_id`) and high-security components (such as the Passwords Vault and Private Contacts, both encrypted at rest), any flaw in isolation boundaries, access controls, input sanitization, or file handling could lead to significant operational risks.

Overall, the repository displays high-quality static analysis and coverage check tooling (such as CSRF checkers, SQLi static analyzers, and multi-tenant leakage scans) which significantly minimizes standard vulnerability classes. However, due to its legacy procedural nature, defensive engineering must remain highly vigilant. This report highlights key categories of interest, theoretical exploitation pathways (abuse vectors), and definitive mitigation strategies to achieve a hardened deployment state.

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
- **Severity**: High
- **Description**: The system includes a custom cancellation policy generation mechanism where administrators can input a `cancellation_policy_url` (such as `cancellation_policy/standard.html`) and arbitrary `cancellation_policy_html` content.
- **Risk**: If the file extension validator in `itm_hotel_booking_normalize_cancellation_policy_url()` is absent or bypassed, an attacker can input a file path ending in `.php` (e.g., `cancellation_policy/shell.php`) and place executable PHP code in the HTML content field.
- **Theoretical Abuse Scenario**: An authenticated malicious admin inputs `cancellation_policy/shell.php` with payload `<?php system($_GET['cmd']); ?>`. The backend writes this directly to disk. The attacker then browses to `booking/cancellation_policy/shell.php?cmd=whoami` to achieve shell access on the server.
- **Mitigation**:
  1. Enforce strict extension whitelisting on file creation paths. Allow only `.html`, `.htm`, or `.txt` extensions.
  2. Sanitize and validate paths, ensuring they do not traverse directory bounds (`..` sequences).
  3. Keep the target directories non-executable under HTTP via `.htaccess` policies that deny PHP execution engines.

#### Finding: Upload Directory Hardening Bypass (RCE via `.htaccess` Overwrites)
- **Severity**: High
- **Description**: The secure File Explorer allows arbitrary uploads to `files/{company_id}/`. While HTTP access is restricted by a top-level `RewriteRule ^ - [F]` policy, an attacker who can upload custom files can theoretically upload a malicious `.htaccess` file to disable directory-level rewriting or execute CGI scripts.
- **Risk**: An attacker uploading a `.htaccess` file can overwrite the system's defensive rules, exposing the directory to direct HTTP requests or enabling script execution.
- **Theoretical Abuse Scenario**: An attacker uploads a file named `.htaccess` containing directives to allow CGI execution or override rewrites. They then upload a malicious script to the same directory and invoke it over HTTP, bypassing the application bootstrap.
- **Mitigation**:
  1. The system's upload helpers (`itm_ensure_upload_directory()`) must force-overwrite `.htaccess` on *every* ensure call and folder access. This prevents user-controlled configurations from persisting.
  2. Implement an upload filter in the Explorer and general upload handlers that explicitly rejects dotfiles, particularly `.htaccess` and any file starting with `.`.
  3. Ensure that the web server configuration (`httpd.conf` or `apache2.conf`) restricts overrides using `AllowOverride None` where possible for upload folders, or enforces `AllowOverride List` only.

---

### 3.2 SQL Injection (SQLi)
#### Finding: Parameter Concatenation in Dynamic Queries
- **Severity**: High
- **Description**: Although the application passes the static static checker (`check_sql_injection_coverage.php`) due to the widespread use of prepared statements, legacy or custom-coded query structures that concatenate input parameters (such as `$_GET['id']` or sorting direction parameters) present potential SQLi injection vectors.
- **Risk**: If a developer introduces a new module without wrapping queries in prepared statements, or if a dynamic ordering parameter (e.g. `dir=ASC`) is interpolated directly, SQLi is possible.
- **Theoretical Abuse Scenario**: In an unsanitized sorting query like `SELECT * FROM racks ORDER BY name $direction`, if `$direction` is read from `$_GET['dir']` without strict whitelisting, an attacker can supply `ASC; SELECT SLEEP(5)` or blind SQLi payloads to extract sensitive database rows.
- **Mitigation**:
  1. Never interpolate variables directly into SQL queries. Always use parameterized queries via `mysqli_prepare`.
  2. For structural components of queries (table names, column names, sort directions) that cannot be parameterized, validate them against a strict whitelist or use `itm_is_safe_identifier($name)`. For sort direction, enforce a strict check: `strtolower($dir) === 'desc' ? 'DESC' : 'ASC'`.

---

### 3.3 Multi-Tenant Data Leakage & Broken Access Control (BAC)
#### Finding: Incomplete `company_id` Enforcement in Background AJAX/APIs
- **Severity**: Medium
- **Description**: Background APIs or lookups (such as `select_options_api.php`, IDF port visualizers, or custom AJAX actions) often read row identifiers (e.g. `id=23`) directly from the request. If the backend fails to verify that the requested row belongs to the active tenant's `company_id`, cross-tenant data leaks or unauthorized mutations can occur.
- **Risk**: An authenticated user of Company 1 can access or modify resources belonging to Company 2 by simply tampering with the numeric IDs in AJAX parameters.
- **Theoretical Abuse Scenario**: An attacker logged into Company 1 initiates a DELETE request to `delete.php?id=100` in a shared module. If `delete.php` only validates that the ID exists but does not verify `company_id = $_SESSION['company_id']`, a record belonging to Company 2 is deleted.
- **Mitigation**:
  1. Ensure every query involving resource lookup, modification, or deletion strictly appends `AND company_id = ?` using the session-derived tenant ID.
  2. Utilize database triggers to validate changes at the database engine level (e.g., verifying that referenced parent keys belong to the same tenant).
  3. Run routine multi-tenant validation sweeps using automated test harnesses like `repro_cross_tenant_admin.php` and `check_multi_tenant_leaks.php`.

---

### 3.4 Hardcoded Secrets & Dangerous Defaults
#### Finding: Repository-Level Default Credentials
- **Severity**: Low
- **Description**: Fresh database installations seed administrative users with predictable default usernames (`Admin`, `Admin2`-`Admin5`) and the default password `Admin`.
- **Risk**: Deploying the system to production without forcing a password change leaves administrative panels open to brute-force attacks.
- **Theoretical Abuse Scenario**: An attacker scans the internet for public-facing IT Management System instances, finds a login portal, and attempts to authenticate with `Admin` / `Admin`. If successful, they gain complete tenant administrative control.
- **Mitigation**:
  1. Implement a first-time login wizard that forces users to change default passwords.
  2. Block the use of overly simple passwords (such as matching the username) via backend password-strength checks.
  3. Move default credential configurations and environment secrets out of source code entirely, relying exclusively on project-root `.env` files.

---

### 3.5 PowerShell Script Injection (Windows-specific Hardware Diagnostics)
#### Finding: Command Argument Injection in System Status
- **Severity**: Medium
- **Description**: The system status module (`modules/system_status/`) runs custom PowerShell scripts (e.g. `cpu_usage.ps1`, `ram_usage.ps1`) to capture real-time server telemetry on Windows hosts. These are called via PHP's `shell_exec`.
- **Risk**: If the command invocation dynamically interpolates any user-controlled input (such as an IP address, domain, or process name) without proper sanitization, an attacker can inject PowerShell commands.
- **Theoretical Abuse Scenario**: If an admin-only feature allows querying a specific drive using a parameters like `shell_exec("powershell.exe -File disk_usage.ps1 -Drive " . $_GET['drive'])`, an attacker can pass `C; whoami` or use malicious arguments to execute arbitrary shell commands.
- **Mitigation**:
  1. Maintain hardware diagnostic parameters as hardcoded values. Avoid passing raw user input to `shell_exec` or command scripts.
  2. If arguments are necessary, validate them against a strict whitelist (e.g., allowing only letters from A to Z for drives).
  3. Use `escapeshellarg()` on any parameters passed to shell processes.

---

## 4. Data Exposure Analysis

### 4.1 Debug & Error Logs
The application maintains a centralized error logging system. When error reporting is enabled (`enable_all_error_reporting = 1`), PHP errors, database exceptions, and execution warnings are logged directly to `ROOT_PATH . 'error_log.txt'`.

#### Exposure Risks
1. **Unprotected Log Files**: If `error_log.txt` is located within the web root and is not protected by server-level configurations, anyone can read it directly via `http://example.com/error_log.txt`.
2. **Secrets Leakage in Call Traces**: Database connection failures or code exceptions can dump call stacks that contain database passwords, API keys, or session tokens in plaintext.

#### Mitigation
1. Ensure the `error_log.txt` path is protected in the root `.htaccess` file:
   ```apache
   <Files "error_log.txt">
       Require all denied
   </Files>
   ```
2. Disable detailed error displays (`display_errors = Off`) in the production environment's `php.ini`. Keep error logging (`log_errors = On`) active, but place the destination file outside the public HTML document root.

### 4.2 Directory Listing
If the web server does not have directory listing disabled, any directory without a default index file (like `index.php`) will expose its files.

#### Exposure Risks
Exposing folders like `backups/`, `files/`, or `images/` can leak database backups, corporate files, and sensitive tickets media.

#### Mitigation
1. As mandated by the application's security checks, **every single folder** in the repository must contain an empty `index.html` file to act as a directory listing fallback.
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
2. **Untrusted Feeds**: Features like the News feed reader parser read RSS inputs. If these XML inputs are parsed using insecure libraries (e.g. vulnerable to XML External Entity (XXE) injection), the server is vulnerable.

#### Mitigation
1. Maintain an inventory of all third-party client-side libraries. Periodically check and update them to their latest stable releases.
2. When parsing XML feeds (such as the RSS feeds in the News module), always disable external entity loading explicitly:
   ```php
   libxml_disable_entity_loader(true);
   ```

---

## 6. Infrastructure & Operational Security

### 6.1 Deployment Risks
Deploying legacy procedural systems with multiple entry points requires robust server-level access control. If the server does not enforce tight separation between public and private entry points, attackers can access diagnostic or administrative files directly.

#### Mitigation
1. Block access to administrative folders or internal scripts at the web server layer (e.g. in Apache `<Directory>` blocks) for non-VPN IP ranges.
2. Remove development utilities, diagnostic tests, and database schema files (`db/`) from production servers. Keep `scripts/debug.php` exclusively in local development environments.

### 6.2 Secrets Management
Standard development patterns often result in database credentials or API keys being hardcoded into `config/config.php` or other library files.

#### Mitigation
1. Leverage environment variables or a secure `.env` file placed outside the repository or ignored via `.gitignore`.
2. Ensure that `.env` files are never readable via HTTP by adding rules to the root `.htaccess`:
   ```apache
   <Files ".env">
       Require all denied
   </Files>
   ```

### 6.3 Directory & File Permissions
Insecure folder permissions (e.g., `777` on Linux hosts) on writable directories like `images/`, `files/`, or `backups/` allow local users or compromised processes to modify system files.

#### Mitigation
1. Writable directories must have their permissions limited to the minimum required. For typical Linux Apache setups, use `755` for directories and `644` for files, with ownership assigned to the web service account (e.g. `www-data`).
2. Run standard filesystem audits regularly to identify folders with loose permissions.

---

## 7. Exploitation Scenarios (Ethical Overview)

To assist defensive teams in visualizing threats, this section outlines theoretical attack flows. This information is intended for educational, defensive, and diagnostic purposes only.

### 7.1 Scenario A: Remote Code Execution via Cancellation Policy Uploads
1. **Initial Access**: An attacker compromises an administrative account or leverages an active session.
2. **Reconnaissance**: The attacker navigates to the Portal Rate Plans section and notes that custom cancellation policy pages are saved as files on disk.
3. **Payload Delivery**: The attacker creates a rate plan, sets the URL to `cancellation_policy/test.php`, and inserts a PHP payload into the HTML editor.
4. **Execution**: The attacker triggers the write operation, bypassing validation due to missing extension checks, and executes commands by navigating directly to `/booking/cancellation_policy/test.php?cmd=id`.
5. **Defensive Control**: This is fully mitigated by strictly whitelisting allowed file extensions (`.html`, `.htm`, `.txt`) in `itm_hotel_booking_normalize_cancellation_policy_url()`.

### 7.2 Scenario B: Cross-Tenant Data Harvesting via ID Tampering
1. **Initial Access**: A malicious employee logs in with standard, non-privileged credentials under Company 1.
2. **Tampering**: The user opens their department views, captures an AJAX request (e.g., fetching a department's details), and notices the resource is fetched using a plain integer identifier (`id=5`).
3. **Exploitation**: The user modifies the ID to `id=6` in the request. If the backend fails to validate that Department 6 belongs to Company 1, the user harvests names, codes, or metadata belonging to Company 2.
4. **Defensive Control**: This is mitigated by appending `AND company_id = ?` to all database lookup statements.

---

## 8. Mitigation & Hardening Guidance

### 8.1 Input Sanitization & XSS Prevention
- Ensure all dynamic variables echoed inside HTML context are wrapped in `sanitize()` or `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- In Javascript, avoid direct insertion of user strings into the DOM via `.innerHTML`. Always use `.textContent` or run a robust HTML escaping helper like `escapeHtml()`.

### 8.2 Directory Protection Rules (Apache Hardening)
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

---

## 9. Final Hardening Checklist

| Domain | Hardening Action | Status |
|--------|------------------|--------|
| **Secrets** | Move database credentials and API tokens out of source files to `.env`. | [ ] |
| **Secrets** | Deny HTTP access to `.env` files in root `.htaccess`. | [ ] |
| **Filesystem** | Place an empty `index.html` file in *every* directory under the repository. | [ ] |
| **Filesystem** | Implement the `upload` policy `.htaccess` in `images/` and `tickets_photos/`. | [ ] |
| **Filesystem** | Implement the `deny_all` policy `.htaccess` in `backups/`. | [ ] |
| **Filesystem** | Implement the `deny_http` policy `.htaccess` in `files/`. | [ ] |
| **Database** | Ensure every single SQL query enforces `company_id` multi-tenant boundaries. | [ ] |
| **Database** | Implement parameterized queries for all user inputs; avoid query concatenation. | [ ] |
| **Authentication** | Enforce strong password complexity rules for administrative and user accounts. | [ ] |
| **Authorization** | Validate CSRF tokens (`itm_require_post_csrf()`) on all state-changing POST requests. | [ ] |
| **XSS** | Wrap all echoed variables in HTML output in `sanitize()`. | [ ] |
| **Operations** | Block or remove `scripts/debug.php` and schema files in production environments. | [ ] |
