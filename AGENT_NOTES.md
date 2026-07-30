# AGENT_NOTES.md - Root Project

> **Authoritative process and module standards:** `AGENTS.md`. This file captures system-wide context, entry-point behaviour, and operational pitfalls that agents should read at session start alongside `AGENTS.md`.

## 1. Module Purpose

The IT Management System is a multi-tenant legacy procedural PHP application (PHP 7.4) designed to manage IT assets, infrastructure, employees, software licenses, appointments, budgets, and helpdesk operations. It operates with zero external dependencies (no Composer, no NPM, vanilla JS, custom CSS) to ensure maximum port-ability and reliability.

---

## 2. Key Tables (system-wide)

- **companies** — Tenant organizations; strictly isolates all company-scoped data.
- **employees** — Core user registry, storing profile, credentials, active employment status, role associations, and security configuration (including `reports_to` self-referencing hierarchy, `totp_secret`, and `totp_enabled` 2FA fields).
- **employee_roles** — Defines tenant role configurations and `sidebar_show` settings.
- **role_module_permissions** — Controls RBAC matrix permissions (`can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`).
- **employee_companies** — Mapping table to grant users access to additional tenants via the tenant switcher.
- **employee_sidebar_preferences** — Per-user personalized sidebar visibility settings (SideMenu).
- **ui_configuration** — Per-user configuration (button positions, theme, app name, favicon, custom module icon overrides, and API integration tier keys).
- **audit_logs** — Stores database DML audit logs automatically populated by MySQL triggers.
- **appointments** — Stores bookings for self-service IT visits, managed by slot availability and concurrency locking.
- **modules_registry** — Registry of all system modules.
- **company_module_access** — Central company-module enablement matrix.
- **share_sessions** — Unified temporary QR/code sharing logs.
- **emails** — Mail send logs.

---

## 3. Required Relationships

- **Multi-tenancy Scoping:** Almost all tables carry a foreign key referencing `companies(id)`. Reading or writing data must always be scoped to the active session `company_id`.
- **Private Data Scoping:** Vault modules (Passwords, Notes, Bookmarks, Private Contacts, Todo) must additionally filter queries by the logged-in user's `employee_id`.
- **Audit Logging Context:** The PHP bootstrapper sets `@app_employee_id` and `@app_company_id` session variables in MySQL. Triggers copy this context into the `audit_logs` record during inserts, updates, or deletes.
- **Parent Delete Dependencies:** For related tables lacking `ON DELETE CASCADE` or `SET NULL` on their foreign keys, child records must be detached or cleared for the active `company_id` *before* deleting the parent (e.g., `inventory_items` referencing `inventory_categories` must clear `category_id` before categories can be removed).

---

## 4. Business Rules (Critical for Agents)

### Security and Hardening
- **SQL Injection Prevention:** Use MySQLi prepared statements exclusively (`mysqli`). Do **not** concatenate user inputs directly into SQL queries. String identifiers (tables and columns) must be validated using `itm_is_safe_identifier($name)`.
- **CSRF Protection:** State-changing `POST` handlers must call `itm_require_post_csrf()` or local equivalents (`cr_require_valid_csrf_token()`). Forms must generate and include the CSRF token using `<input type="hidden" name="csrf_token" value="<?= itm_get_csrf_token() ?>">`.
- **Redaction of Sensitive Fields:** Passwords, Master Keys, and reset tokens must never be written in plain text to log files, `attempts.email`, or `audit_logs`.
- **Character Encoding:** The database, connection, and all source code files operate on `utf8mb4` with `utf8mb4_unicode_ci` collations. Do not strip or alter Unicode characters (such as emojis or portuguese accents) under the guise of fixing mojibake.
- **Directory Listing Prevention:** Every folder under the repository root must contain an empty `index.html` placeholder to prevent directory indexing.

### Recently Modified Subsystems

#### 1. Roles & Permissions Module (`modules/roles_permissions/`)
- Allows browsing tenant roles and configuring the permissions matrix for each.
- Multi-tenancy is strictly enforced, and only `itm_is_admin()` can edit permissions, create, or modify roles (non-admins receive HTTP 403).
- The seeded **Admin** role uses the `ALL` wildcard and cannot be deleted, modified, or updated.
- Active employee counts on role cards are calculated by joining the `employees` table with the active HR status (`employment_status_id` linked to "Active").
- Custom sidebar visibility overrides are managed using `employee_roles.sidebar_show` (0 hides the sidebar modules).

#### 2. Dynamic Module-Title Emojis (Mandatory)
- Individual module browser `<title>` tags must dynamically prepend the user-selected or system-configured custom emoji at runtime.
- Prepend the title rendering by calling `itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''))` immediately before the `<title>` tag.

#### 3. API Keys & Rate Limits
- API credentials and quotas are stored in `ui_configuration`.
- Enforced via `includes/itm_api_rate_limit.php`.
- **Tiers and Limits (rolling hour):**
  - **Free:** No limit. **Authenticated session required** (no key required).
  - **Basic:** 300 quota limit. API key required (`X-API-Key` or `api_key`).
  - **Pro:** 1000 quota limit. API key required.
  - **Enterprise:** 10000 quota limit. API key required.
- Quota probes can be verified by querying `GET scripts/api.php?rate_limit=1` without consuming API usage quota.

#### 4. Explorer Module
- High-security multi-tenant file system utilizing storage under `files/{company_id}/`.
- Paths are divided into `Common/`, `Departments/{dept_id}/` (members only), `Private/{username}_{employee_id}/` (owner only, requires Vault unlock), and `Trash/`.
- Traversal attacks (`..`) are blocked by Normalizing slashes and validating boundaries.
- **`downloadZip` Contract:** Allows recursive ZIP downloads **only** for the exact path of the user's own `Private/{username}_{employee_id}/` directory. Other ZIP targets are rejected with HTTP 403.
- Directory segment hardening is forced by writing a `.htaccess` file with `deny_http` rules (`RewriteRule ^ - [F]`) on **each folder segment** in the chain.

#### 5. Private Contacts, Notes, Todo, Bookmarks, and Passwords Vaults
- Vault tables (`password_entries`, `private_contacts`, `notes`, `todo`, `bookmarks`) store sensitive user PII encrypted at rest using `itm_encrypt()` and the user's master key.
- Unlocking the vault populates `$_SESSION['vault_key']`. If locked, a lock screen is displayed.
- When `employees.totp_enabled = 1` is configured on the user row, the vault unlock interface requires both the master key and a valid 6-digit TOTP authenticator code (`includes/itm_vault_unlock.php`).
- Vault fields are decrypted/hydrated at runtime and sorted/searched within PHP (no direct SQL `LIKE` on ciphertext).
- Master key rotation atomically re-encrypts all vault entries using a database transaction to prevent corruption.

#### 6. Email Management (`modules/emails/` and `modules/email_smtp_configurations/`)
- Supports custom tenant SMTP profiles, automated alert rules, and centralized logging.
- Passwords are encrypted with `itm_email_encrypt_password()`.
- System operations (password resets, invitations, onboarding approvals, alert runners) dispatch mail using `itm_send_email()`.
- The `emails` log is private-data exempt from audit triggers.

#### 7. Appointment Scheduling (`modules/appointment/`)
- Self-service visit booking system.
- To prevent double-booking concurrent requests, inserts set `appointments.booking_lock` and soft-deletes release it.
- Business hours, reasons, and modalities (In-person, Remote) are configured per tenant company.

#### 8. Ops Report (Daily Operations Report)
- Tracks daily hotel operations per `company_id` and `report_date`.
- Non-admins are restricted by a **D-2 Edit Lock**: they can edit today's and yesterday's dates only; older reports are read-only.
- Custom section titles, labels, and headers persist as JSON in `ops_report.report_ui_json`.

#### 9. License Management (`modules/license_management/`)
- Tracks software licenses linked to `license_types` and `suppliers`.
- Prices entered in create/edit or Excel imports normalize commas to dots (`cr_normalize_price_input()`).

#### 10. Request Password (`modules/request_password/`)
- Reset workflow requiring HR, HOD, and ISM approvals.
- Features digital signature blocks capturing Applicant, ISM, HR, and HOD dates.
- Deleting request logs is restricted only to the creating user (`created_by = session_employee_id`).

#### 11. CRUD Record Share (Temporary QR / Code Sharing)
- Exposes temporary QR codes, 6-digit access codes, WhatsApp, and Outlook share links for record snapshots across 23 capable modules.
- Managed via `includes/itm_crud_record_share.php` and log entries in `share_sessions` (private-data exempt).

#### 12. Company Module Access (`modules/company_module_access/`)
- Lets administrators enable/disable modules per company.
- Access is allowed by default; explicit `enabled = 0` rows deny access.
- Changes are configured in an admin matrix utilizing AJAX toggles.

---

## 5. UI Behavior Requirements

- **Actions Column Layout:** Action headers and cells must carry `class="itm-actions-cell"` and `data-itm-actions-origin="1"` to enable correct dynamic alignment (`js/ui-layout.js`).
- **Bulk Selection Toolbar:** Above tables on index pages, display a bulk form with "Select to Delete", "Cancel", and "Clear Table".
  - Gated so that bulk actions render only when the row count is greater than or equal to `$perPage`.
  - The "Cancel" button must have `type="button"` and `data-itm-bulk-cancel="1"` to prevent accidental POST submissions.
- **Search Column Alignment:** The search function must query the same columns displayed in the UI. Set `$displayFieldColumns = $uiColumns` before the SQL search block to avoid undefined variable warnings.
- **UI Action Labels (Zero Tolerance for Mixed Labels):** Standalone action buttons, links, and page headers must feature **emoji-only visible labels** (e.g., Back 🔙, Save 💾, Edit ✏️, Delete 🗑️, View 🔎, Create ➕). Compound descriptive titles must reside exclusively in `title` or `aria-label` tags.
- **Pagination Controls:** Render emoji-only navigation icons (⏮️ First, ◀️ Previous, ▶️ Next, ⏭️ Last) without text labels. Preserve search, sort, and page parameters across URLs.

---

## 6. API Actions (If Applicable)

- **`import_excel_rows`** — JSON POST handler on index pages supporting spreadsheet imports. Updates modify only columns present in the payload header to prevent data loss.
- **`select_options_api.php`** — Dynamic lookup options and quick-adds. Validated against strict whitelists (`includes/itm_select_options_policy.php`).
- **`get_ports.php` / `update_port.php`** — API endpoints driving the Switch Port Manager tiles.

---

## 7. File Structure (High Level)

- **config/** — Bootstrapping, database connectivity, and session variables.
- **includes/** — Shared templates, headers, sidebars, and functional helpers.
- **modules/** — Segmented folders containing procedural CRUD files (`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`).
- **scripts/** — Diagnostic, static audit, and regression testing CLI/Browser utilities.
- **db/** — Database SQL schema, triggers, and seed files.

---

## 8. Multi-Tenant Rules

- Database queries must always append `WHERE company_id = ?` to prevent cross-tenant data leaks.
- Tenant profiles and directory uploads use the employee's home `company_id` (not the switched session company) when editing personal profiles.

---

## 9. Audit Logging Requirements

- Standard CRUD tables define INSERT, UPDATE, and DELETE triggers under `db/03_triggers.sql`. Triggers execute unconditionally on DML.
- **Private-Data Exempt Tables:** Sensitive user tables (emails, password credentials, chat messages, notes, todo, bookmarks, QR share sessions) are excluded from `audit_logs` triggers to maintain privacy.

---

## 10. Common Pitfalls

- **Incorrect Upload Directory Creation:** Do **not** use bare `mkdir()`. Always call `itm_ensure_upload_directory_chain()` to generate `.htaccess` and `index.html` placeholders correctly.
- **Broken Edit Selections:** FK dropdowns on edit forms must preserve and append currently selected values even if they do not match the current company options list, preventing form resets to `-- Select --`.
- **Empty-Table Sample Data Duplication:** When seeding sample templates, verify that the table is empty for the target tenant before performing inserts.
- **Deleting References with RESTRICT Constraint:** If deleting a parent record with a restricted child relationship, the application must detach or null-out child FKs for the active `company_id` *before* deleting the parent to prevent database constraint exceptions.

---

## 11. Examples of Safe Code Patterns

### Safe SELECT

```php
$stmt = $conn->prepare('SELECT id, name FROM departments WHERE company_id = ? AND deleted_at IS NULL AND id = ?');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
$result = $stmt->get_result();
```

### Safe INSERT

```php
$stmt = $conn->prepare('INSERT INTO departments (company_id, name, active, created_by) VALUES (?, ?, 1, ?)');
$stmt->bind_param('isi', $companyId, $name, $employeeId);
$stmt->execute();
```

---

## 12. Module Owner Notes

### Mandatory Verification Tools
- Run PHP syntax linter: `php -l path/to/file.php`
- Run SQL Injection static analyzer: `php scripts/check_sql_injection_coverage.php`
- Run UI Action Emoji validator: `php scripts/check_ui_action_emoji.php`
- Run the PHPUnit suite: `php scripts/run_tests.php`
- Run the Smoke Test suite: `bash scripts/smoke_test.sh`
