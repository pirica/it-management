# AGENT_NOTES.md - Root Project

## 1. Module Purpose
The IT Management System is a multi-tenant legacy procedural PHP application (PHP 7.4) designed to manage IT infrastructure, employees, budgets, appointments, and helpdesk operations with zero external dependencies (no Composer, no NPM). This document serves as the system-wide developer reference, capturing cross-module architecture, global business rules, security constraints, and operational guidelines.

---

## 2. Key Tables
While individual modules own specific sub-schemas, the system core relies on several foundational tables:
- **companies** — Tenant organizations; scopes all system records.
- **employees** — User profiles with authentication credentials (`username`, `password_hash`, `totp_secret`, `totp_enabled`, `reports_to`).
- **employee_roles** — User roles mapping to hierarchical permissions.
- **employee_sidebar_preferences** — Custom per-user module visibility toggles.
- **ui_configuration** — Global settings, UI positions, integration keys, and API rate-limiting tiers (`tier`, `api_key`, `api_limit`).
- **audit_logs** — Centralized log capturing all INSERT, UPDATE, and DELETE DML mutations from database triggers.
- **appointments** — Central schedule booking slots for IT self-service visits.
- **email_smtp_configurations** — Multi-tenant SMTP configurations to send system mail.
- **knowledge_base** — Scoped reference articles utilized by the AI chatbot.

---

## 3. Required Relationships
- **Multi-Tenancy Scoping:** All tenant tables must include a `company_id` column referencing `companies(id)`. Direct cross-tenant access is prohibited.
- **Employee-to-Company:** `employees.company_id` dictates the user's default company. Multi-company access is mediated via `employee_companies` mapping.
- **Auditing Actors:** Database audit triggers fetch actor context using MySQL session variables `@app_employee_id` and `@app_company_id`, set in `config/config.php` on each authenticated request.
- **Cascade Deletions vs. Gaps:** Refer to table foreign keys (`db/01_schema.sql`). Some references use `ON DELETE CASCADE` or `ON DELETE SET NULL`. If a table lacks these and has inbound references (e.g. `inventory_categories` referenced by `inventory_items`), the application or delete handler must clean up or null-out child keys in the active `company_id` *before* deleting the parent row.

---

## 4. Business Rules (Critical for Agents)

### General System Constraints
- **Multi-Tenancy Isolation:** Every database read or write query (except global lookups/Companies) must contain `WHERE company_id = ?` using the session's active `company_id`.
- **Database Hygiene:** Never write inline `ALTER TABLE` commands in `db/01_schema.sql`. Put keys, indexes, and FK constraints directly inside `CREATE TABLE`.
- **Incremental Migrations (`db/migrations/`):** New schema changes for existing databases must be implemented as copy-paste `DROP TABLE IF EXISTS` + `CREATE TABLE` files under `db/migrations/` (no `ALTER TABLE`, no staging tables).
- **Multi-company Seed Admins:** Fresh imports seed 5 companies, each with a unique Admin username (`Admin`, `Admin2`...`Admin5`) and the password `Admin` (encrypted via bcrypt). Role, access, and status lookups must use tenant-correct joins or subqueries rather than hardcoded IDs.
- **Character Encoding & UTF-8:** The entire database utilizes `utf8mb4_unicode_ci` (`SET NAMES utf8mb4`). PHP files, CSS, JS, and SQL files must be saved as UTF-8 without BOM. Do not strip emojis or special punctuation to "fix" mojibake—ensure the connection charset matches using `mysqli_set_charset($conn, 'utf8mb4')`.

### API Keys & Rate Limits
- **Quotas and Tiers:** Integration quotas are stored on `ui_configuration` and enforced via `includes/itm_api_rate_limit.php`.
  - **Free Tier:** No hourly limit, no API key required, but **authenticated session is required** (resolves `company_id` + `employee_id` from PHP session).
  - **Paid Tiers:** **Basic** (300 requests/hr), **Pro** (1000 requests/hr), and **Enterprise** (10000 requests/hr) require an API key sent in the `X-API-Key` header or `api_key` parameter.
- **API Key UI Protection:** Under Settings, Free tiers hide key generation tools, and `tier` input dropdowns are read-only to prevent user privilege escalation.

### Appointment Scheduling & Alerts
- **Clickable Submit Button & Browser Alerts:** In the self-service booking UI (`js/appointment.js`), the **Schedule** button remains clickable to always trigger client-side validation alerts:
  - If no visit reason is selected, show `alert('--Select a reason for your appointment--')` and focus the dropdown.
  - If no slot is selected, show `alert('Select an appointment time.')` and focus the input or open-modal button.
  - The submit button is only disabled during the active API AJAX submission to prevent double-booking.
- **Slot Concurrency Lock:** Concurrency is protected via `appointments.booking_lock` (unique key over `company_id` + date/time slot).

---

## 5. UI Behavior Requirements

### Layout and Interactive Elements
- **Dynamic Module-Title Emojis:** Page titles (`<title>`) must dynamically prepend the user-selected custom module emoji via `itm_crud_apply_module_icon_to_browser_title()` in `includes/itm_crud_browser_title.php`.
- **UI Action Labels (NO MIXED Rule):** Interactive elements (buttons, submit inputs, headers, navigation, and pagination) must use **emoji-only visible text** with zero accompanying text labels (e.g. use `💾` instead of `💾 Save`). Standard text descriptions must reside in the `title` or `aria-label` attributes.
  - View: `🔎` | Edit: `✏️` | Delete: `🗑️` | Back/Cancel: `🔙` | Save: `💾` | Create/Add: `➕`
- **Pagination Emoji Maps:** Standard list paginations must use emoji-only visible text: First (`⏮️`), Previous (`◀️`), Next (`▶️`), Last (`⏭️`).
- **Dynamic UI Configuration:** Modules must load settings using `itm_get_ui_configuration()`, mapping layouts according to `new_button_position` and `table_actions_position`.
- **Table Action Markers:** List action columns must have `class="itm-actions-cell"` and `data-itm-actions-origin="1"` applied to both headers and cell wrappers so the layout engine can position them correctly.
- **DB Import Endpoint:** Any table handling standard imports must bear the attribute `data-itm-db-import-endpoint="index.php"` (or `list_all.php` for bespoke views) to enable automated save-to-database integration.
- **Bulk Delete selection:** Standard index screens must support a bulk delete selection form (`id="bulk-delete-form"`) with a "Select to Delete" button and a Cancel button (`type="button"` with `data-itm-bulk-cancel="1"`). The bulk delete toolbar and checkboxes are only visible if the total row count matches or exceeds the page size (`$totalRows >= $perPage`).
- **Booleans in UI:**
  - List / View: Rendered as status badges (`<span class="badge badge-success">Active</span>` or `<span class="badge badge-danger">Inactive</span>`). No emojis.
  - Edit / Create forms: Must utilize the mandatory double-label checkbox pattern wrapper `itm-checkbox-control` displaying a matching visual emoji indicator (`✅` / `❌`). Standalone text inputs for active (0/1) are forbidden.

---

## 6. API Actions

Core system endpoints reside in standalone PHP files and handle AJAX or integration payloads:
- **`scripts/api.php?rate_limit=1`** — Quota probe payload; retrieves active limits and session parameters without consuming a rate count.
- **`modules/explorer/api.php`** — Secure file manager endpoint; implements folder access, trash management, and `downloadZip` (restricts recursive ZIP download strictly to the employee's own private directory).
- **`modules/knowledge_base/chat_api.php`** — Chatbot API; enforces CSRF token validation, rate-limiting, and escapes user HTML messages before response.
- **`modules/appointment/api.php`** — Self-service appointment slot generation (`week_slots`) and schedule creation (`schedule`).

---

## 7. File Structure (High Level)
- **config/** — Global configuration, environment variables (`.env`), and early error-reporting setups.
- **includes/** — Shared headers, footers, sidebars, date/time formatters, API rate limiters, and vault handlers.
- **modules/** — Isolated feature directories housing modular CRUD routines (`index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`).
- **scripts/** — Maintenance tools, security checkers, audit catalogs, and backup scripts.
- **db/** — Database SQL schemas, migrations, seed records, and trigger definitions.
- **phpunit/** — Standard PHPUnit configuration, execution harnesses, and unit tests.
- **assets/**, **css/**, **js/** — Client-side styling, layouts, and interactive logic.

---

## 8. Multi-Tenant Rules
- All SELECT queries must end with `company_id = ?` bound from the active session.
- **Multi-Tenant Profile Uploads:** User profile photos are stored in isolated paths: `files/{home_company_id}/Private/{username}_{employee_id}/profile/`.
- **Cross-Tenant Switching:** Admin-level users utilize a session company switcher. Upon switching, the session variable `company_id` is re-scanned, and subsequent database reads immediately scope to the selected tenant.

---

## 9. Audit Logging Requirements
- **Unconditional Database Triggers:** Core table mutations are captured unconditionally by database-level triggers `trg_{table}_audit_insert|update|delete` defined in `db/03_triggers.sql`. These triggers insert raw JSON modifications into `audit_logs` and are **not** affected by the `enable_audit_logs` UI toggle.
- **Private Data Audit Exemptions:** The following private data tables are strictly excluded from audit logging to protect PII and security credentials. They must **not** have database audit triggers or PHP audit hooks:
  - `emails` (Send logs containing recipient and subject details).
  - `password_entries` / `password_folders` (Encrypted passwords and vaults).
  - `private_contacts` (Encrypted user address books).
  - `todo` / `todo_categories` (Personal task titles/descriptions).
  - `notes` / `note_labels` (Personal user notes).
  - `events` / `share_sessions` (Private schedules and temporary share payloads).
  - `live_chat_messages` / `live_chat_typing` (Chat bodies and typing events).

---

## 10. Common Pitfalls

- **Session Fixation:** Reusing old session IDs post-login. Authentication routines in `login.php` must call `session_regenerate_id(true)` upon successful password validation.
- **HTTPS Cookies:** Session cookies must be issued with the `HttpOnly`, `SameSite=Lax`, and `Secure` (when accessing over HTTPS) flags.
- **Hardcoding `display_errors`:** Avoid forcing `display_errors` directly in application index files; allow the setting-driven value from Settings (`enable_all_error_reporting`) via `config/config.php` to dictate error visibility.
- **Wrong Home Company updates:** Profile edits in `user-config.php` must update the user's home company row (`employees.company_id`), not the active session-switched company. Otherwise, multi-tenant admins switching companies will fail to update their core profile.
- **Broken Profile Photo URLs:** The profile photo URL must be constructed using an app-absolute path (`BASE_URL` + `/modules/explorer/file.php?path=...`) via `emp_profile_photo_url()`. Relative paths like `../../` will break when loaded on different depth folders or root pages.
- **Database Tenant Replica FK Alignment:** Lookups or child seed rows must resolve foreign keys via company-scoped name matches or business keys (e.g. `company_id` + Center Code) rather than assuming raw auto-incrementing IDs align across companies.
- **Directory Listing Prevention:** Every folder in the system must contain an empty `index.html` file. Managed upload paths under `images/`, `tickets_photos/`, `floor_plans/`, `backups/`, and `files/` must also enforce policy-hardened `.htaccess` files generated via `itm_ensure_upload_directory()`:
  - `upload` (Static assets allowed, scripts/PHP blocked) -> `images/`, `tickets_photos/`, `floor_plans/`.
  - `deny_http` (All HTTP access forbidden; file downloads served via PHP `file.php`) -> `files/` (and all subsegments).
  - `deny_all` (All HTTP requests denied completely) -> `backups/`.

---

## 11. Examples of Safe Code Patterns

### Safe SELECT (MySQLi Prepared Statement)
```php
$stmt = $conn->prepare('SELECT id, name, status FROM appointments WHERE company_id = ? AND id = ?');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Why: Output is escaped to prevent Cross-Site Scripting (XSS)
    echo sanitize($row['name']);
}
```

### Safe INSERT (MySQLi Prepared Statement)
```php
$stmt = $conn->prepare('INSERT INTO appointments (company_id, appointment_date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('issss', $companyId, $date, $startTime, $endTime, $status);
$stmt->execute();
```

---

## 12. Employee Profile (`user-config.php`)
- **Scoping:** Strictly scoped to the logged-in employee via `employee_id`.
- **Vault Security (`#vault-security`):** Form handles Vault Master Key changes and TOTP 2FA. Re-encrypts all notes, bookmarks, passwords, and private contacts atomically using database transactions. Provides a key generator and a secure One-Time Display overlay for newly generated keys.
- **Security Flash Messages:** Vault and password feedback must render at the top of the page *and* immediately above each section's Save button so the user does not miss messages.
- **Personalized Sidebar:** Checkboxes allow employees to toggle module side-menu options. Preferences are persisted in `employee_sidebar_preferences` (not `ui_configuration`), and `$ui_config` must be reloaded from `itm_get_ui_configuration()` immediately after saving to render the changes on the current request.
- **System Access Overview:** Dynamically loads active permission flags via a `DESCRIBE` query on `employee_system_access`. Internal database audit columns (e.g. `id`, `company_id`, `created_at`) are excluded from permission counts and forms.

---

## 13. Module Owner Notes (Optional)

### Diagnostic and Verification Scripts
Run the following scripts from the repository root to ensure full compliance and identify configuration drift:
- **`php scripts/verify_appointment.php`** — Validates appointment slots, schedule APIs, and concurrency locks.
- **`php scripts/check_ui_action_emoji.php`** — Verifies 100% compliance with the NO MIXED visible emoji-only action labels standard.
- **`php scripts/check_sql_injection_coverage.php`** — Audit script scanning for raw variable concatenations or missing prepared statements.
- **`php scripts/check_fk_label_search_coverage.php`** — Asserts that all search functions correctly match display labels instead of raw IDs.
- **`php scripts/run_tests.php`** — Launches the full PHPUnit test suite.

### Developer Login Bypass (Local Dev Only)
1. Run `php scripts/bypass_login.php` in your local terminal.
2. Capture the outputted `Session ID`.
3. Set the browser cookie `PHPSESSID` to this Session ID on `http://localhost/it-management/`.
4. Refresh to bypass authentication, automatically log in as Admin, and auto-unlock the Vault.
