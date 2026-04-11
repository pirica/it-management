# AGENTS.md

This document provides essential instructions, architectural constraints, and coding standards for AI agents working on the **IT Management System**.

## 🚀 Project Overview
A multi-company IT Asset Management System built with PHP and MySQL. 
* **Design Philosophy:** GitHub Copilot theme (Light/Dark mode).
* **Architecture:** Procedural PHP with modular CRUD structures.
* **Multi-tenancy:** Data is strictly scoped by `company_id`.

## 🛠 Tech Stack & Environment
- **Backend:** PHP 7.4+ (Strictly **MySQLi**, do NOT use PDO).
- **Database:** MySQL 8.0+.
- **Frontend:** Vanilla JS, Custom CSS (`css/styles.css`), No Frameworks.
- **Environment:** Apache 2.4+. **No Composer** dependency management.

## 📂 Directory Map
- `config/`: Core settings and `config.php`.
- `includes/`: UI components (headers, sidebars) and utility functions.
- `modules/`: Feature-specific CRUD logic.
- `scripts/`: Maintenance, security audits, and CLI tools.
- `js/` & `css/`: Assets (Note: `css/style.css` is **deprecated**; use `css/styles.css`).
- **Required Dirs:** `images/`, `tickets_photos/`, and `backups/` must exist with write permissions.

---

## 🏗 Coding Standards

### 1. Module Structure
Each module must maintain a flat structure with these specific files:
`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, and `list_all.php`.

> [!IMPORTANT]
> **No Master Templates:** Do not attempt to abstract CRUD into a single master template. Each module must remain independent.

### 2. Database & Schema Rules
- **Schema Updates:** If a field/table is deleted or a header renamed, you **must** update `database.sql`.
    - Database: `database.sql` Use only INSERT/UPDATE/DELETE. Don't use DB triggers.
- **Company Scoping:** - **Hide** `company_id` from all UI views.
    - Add safe inline FK creation logic to create referenced rows automatically.
    - Scope all queries and inserts by `company_id`.
- **Audit Logging:** The system sets MySQL session variables (`@app_user_id`) in `config.php`. Do not overwrite these.

### 3. Protection Zone (STRICT: No Auto-Changes)
Do **not** modify the logic or structure of these modules unless explicitly requested:
* `/modules/equipment/` (including Switch Port Manager)
* `/modules/idfs/`, `/modules/idf_links/`, `/modules/idf_positions/`,`/modules/idf_ports/`
* `/modules/audit_logs/`, `/modules/employees/`
* `/modules/settings/`
* `/modules/user_companies/`
* `modules/employee_system_access/`
* `modules/cable_colors/`

### 4. Dynamic UI Configuration (Settings)
Modules must read and validate settings via `itm_get_ui_configuration()`:
- **Button Positions:** Render the top refresh/add controls on the left, right, or both based on `new_button_position`.
- **Table Actions:** Add `data-itm-actions-origin="1"` to the "Actions" header and row cells to allow the global layout engine to map `table_actions_position`.
- **Global Behaviors:** Respect system toggles for:
    * `enable_all_error_reporting` setting.
    * `enable_audit_logs` setting.
    * `records_per_page` (Used as the threshold for pagination and bulk action visibility).

### 5. Standard Feature Set
Every module (excluding the Protection Zone) must implement:
* **Bulk Actions:** "Select to Delete" and "Clear Table". These must only be visible if the total record count is greater than or equal to the `records_per_page` setting.
* **Search:** Comprehensive search across all visible fields.
* **Order:** Standardized sort fields ASC DESC - '▲' : '▼' 
* **Tools:** `📗Export Excel`, `📄Export PDF`, and `📥Import Excel` (linked via `js/table-tools.js`).
* **Navigation:** Standardized server-side pagination based on the `records_per_page` value from Settings.
* ** Error Reporting:** Standardized server-side `enable_all_error_reporting` value from Settings.
* ** Enable Audit Log:** `enable_audit_logs` value from Settings.
* **Audit Trail Coverage (Required for New Modules):** Any new module must write INSERT/UPDATE/DELETE events to `audit_logs` (respecting the `enable_audit_logs` toggle) so changes are traceable in the audit center.

### 6. Empty-State Sample Data Process
When a company opens a module and sees **"No records found."**, modules should support quick seeding from `database.sql`:
* Add an **"Add sample data"** button at the bottom of `index.php` list pages, visible only when the module result set is empty for the active company.
* Implement a `POST` handler for `add_sample_data` in `index.php` that:
  * validates CSRF (`itm_require_post_csrf()` or module-equivalent CSRF validator),
  * confirms there is an active `company_id`,
  * re-checks the table is empty for that `company_id` before inserting.
* Seed rows must come from actual `INSERT INTO` entries in `database.sql` for that module table.
* Enforce tenant safety:
  * always write seeded rows with the active `company_id`,
  * never expose/edit `company_id` in UI,
  * skip protected modules unless explicitly requested.
* Keep seeding idempotent from the UI perspective by allowing it only when the module has zero rows for the company.
---

## 🔒 Security Protocol

### SQL Injection (SQLi)
1. **Prepared Statements:** ALWAYS use MySQLi prepared statements for user data.
2. **Identifier Validation:** Use `itm_is_safe_identifier($name)` for dynamic table/column names.
3. **Execution:** Use `itm_run_query($conn, $sql)` with error trapping.
4. **Audit:** Run `php scripts/check_sql_injection_coverage.php` after changes.

### CSRF & XSS
- **CSRF:** All `POST` handlers must call `itm_require_post_csrf()`. Forms require:
  `<input type="hidden" name="csrf_token" value="<?= itm_get_csrf_token() ?>">`
- **XSS:** Wrap all echoed user-provided strings in `sanitize($data)`.

---

## 💡 Development Patterns

### PHP Best Practices
- **Paths:** Use `ROOT_PATH` with a trailing slash for filesystem operations.
- **Variable Collisions:** Use unique, prefixed variables in `includes/` (e.g., `$itm_sidebar_user`).
- **Commenting:** Follow the **"Why-Focused"** style. Every file must be commented.
    * *What:* "Looping through array" (Avoid).
    * *Why:* "Using a generator here to handle large datasets without hitting memory limits" (Prioritize).

### UI/UX Requirements
- **Layout:** `.container` > `.main-content` > `.content`.
- **Buttons:** `btn-primary` for main actions; `btn-sm` for table actions.
- **Tables:** Use `.itm-actions-cell` and `.itm-actions-wrap` for action columns.
- **Boolean Display:** in `index.php`, if field name is `active` <span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>
- **Boolean Display:** In `view.php`, result `1` = ✅, `0` = ❌ (icon-only).
- **Dynamic Selects:** Use `data-addable-select="1"` to enable the quick-add "+" functionality.

---

## 🛠 Setup & Debugging
- **Dev Credentials:** `localhost` | `root` | `usbw` | `itmanagement`.
- **Logs:** Errors are standardized to `ROOT_PATH . 'error_log.txt'`.
- **Testing:** On testing don't capture Screenshot:
⚠️ Screenshot not captured: browser_container tool is not available in this environment.
