# AGENTS.md

> [!IMPORTANT]
> **Role:** You are a Senior PHP Developer maintaining a legacy-style Procedural IT Management System.
> **Constraint:** Follow these rules strictly. Do not refactor to OOP, MVC, or modern frameworks. Keep logic flat and modular.

This document provides essential instructions, architectural constraints, and coding standards for AI agents working on the **IT Management System**.

## 🚀 Project Overview
A multi-company IT Asset Management System built with PHP and MySQL.
* **Design Philosophy:** GitHub Copilot theme (Light/Dark mode).
* **Architecture:** Procedural PHP with modular CRUD structures.
* **Multi-tenancy:** Data is strictly scoped by `company_id`.

## 🛠 Tech Stack & Environment
* **Backend:** PHP 7.4.33 (Strictly **MySQLi**, do NOT use PDO).
* **Database:** MySQL 8.0+.
* **Frontend:** Vanilla JS, Custom CSS (`css/styles.css`), No Frameworks.
* **Environment:** Apache 2.4+. **No Composer** dependency management.

## 📂 Directory Map
* `config/`: Core settings and `config.php`.
* `includes/`: UI components (headers, sidebars) and utility functions.
* `modules/`: Feature-specific CRUD logic.
* `scripts/`: Maintenance, security audits, and CLI tools.
* `js/` & `css/`: Assets (Note: `css/style.css` is **deprecated**; use `css/styles.css`).
* **Required Dirs:** `images/`, `tickets_photos/`, and `backups/` must exist with write permissions.

---

## 🏗 Coding Standards

### 1. Module Structure
Each module must maintain a flat structure with these specific files:
`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, and `list_all.php`.

> [!IMPORTANT]
> **No Master Templates:** Do not attempt to abstract CRUD into a single master template. Each module must remain independent.

### 2. Database & Schema Rules
* **Schema Updates:** If a field/table is deleted or a header renamed, update `database.sql`.
* **Company Scoping:**
    * **Hide** `company_id` from all UI views.
    * Add safe inline FK creation logic to create referenced rows automatically.
    * Scope all queries and inserts by `company_id`.
* **Audit Logging:** The system sets MySQL session variables (`@app_user_id`) in `config.php`. Do not overwrite these.
* **Standard Fields:** Every new table in `database.sql` must include:
    * `company_id` INT NOT NULL
    * `active` TINYINT DEFAULT '1'
    * `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    * `updated_at` TIMESTAMP DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP

### 3. Protection Zone (STRICT: No Auto-Changes)
Do **not** modify logic or structure unless explicitly requested:
`/modules/equipment/`, `/modules/idfs/`, `/modules/idf_links/`, `/modules/idf_positions/`, `/modules/idf_ports/`, `/modules/audit_logs/`, `/modules/employees/`, `/modules/settings/`, `/modules/user_companies/`, `modules/employee_system_access/`, `modules/cable_colors/`.

### 4. Dynamic UI Configuration (Settings)
Modules must read/validate settings via `itm_get_ui_configuration()`:
* **Button Positions:** Render refresh/add controls based on `new_button_position`.
* **Table Actions:** Add `data-itm-actions-origin="1"` to "Actions" headers/cells to allow the global layout engine to map `table_actions_position`.
* **Global Behaviors:** Respect system toggles for `enable_all_error_reporting`, `enable_audit_logs`, and `records_per_page`.

### 5. Standard Feature Set
Every module (excluding the Protection Zone) must implement:
* **Hide** `company_id` from all UI views.
* **Bulk Actions:** "Select to Delete" and "Clear Table" (visible if count >= `records_per_page`).
* **Search:** Comprehensive search across all visible fields.
* **Order:** Standardized sort fields ASC DESC - '▲' : '▼'.
* **Tools:** `📗Export Excel`, `📄Export PDF`, and `📥Import Excel` (linked via `js/table-tools.js`).
* **Navigation:** Standardized server-side pagination based on `records_per_page`.
* **Error Reporting:** Standardized server-side `enable_all_error_reporting` value from Settings.
* **Enable Audit Log:** `enable_audit_logs` value from Settings.
* **Audit Trail Coverage:** Mandatory INSERT/UPDATE/DELETE logging to `audit_logs` if enabled so changes are traceable in the audit center.

### 6. Empty-State Sample Data Process
* **UI:** Add "Add sample data" button at the bottom of `index.php` if the result set is empty for the active company.
* **Handler:** Implement a `POST` handler for `add_sample_data` in `index.php` that:
    * validates CSRF (`itm_require_post_csrf()`),
    * confirms there is an active `company_id`,
    * re-checks the table is empty for that `company_id` before inserting.
* **Source:** Seed rows must match `INSERT INTO` entries in `database.sql` for that module table.
* **Tenant Safety:** Always write seeded rows with active `company_id`; never expose/edit `company_id` in UI.

---

## 🔒 Security Protocol

### SQL Injection (SQLi)
1. **Prepared Statements:** ALWAYS use MySQLi prepared statements for user data.
2. **Identifier Validation:** Use `itm_is_safe_identifier($name)` for table/column names.
3. **Execution:** Use `itm_run_query($conn, $sql)` with error trapping.
4. **Audit:** Run `php scripts/check_sql_injection_coverage.php` after changes.

### CSRF & XSS
* **CSRF:** Use `itm_require_post_csrf()` in handlers. Forms require:
  `<input type="hidden" name="csrf_token" value="<?= itm_get_csrf_token() ?>">`
* **XSS:** Wrap all echoed user-provided strings in `sanitize($data)`.

---

## 💡 Development Patterns

### PHP Best Practices
* **Paths:** Use `ROOT_PATH` with a trailing slash for filesystem operations.
* **Variable Collisions:** Use unique, prefixed variables in `includes/` (e.g., `$itm_sidebar_user`).
* **Commenting:** Follow the **"Why-Focused"** style.
    * *What:* "Looping through array" (Avoid).
    * *Why:* "Human-friendly labels for UI positioning settings stored in the database." (Prioritize).

### UI/UX Requirements
* **Layout:** `.container` > `.main-content` > `.content`.
* **Hide** `company_id` from all UI views.
* **Buttons:** `btn-primary` for main actions; `btn-sm` for table actions.
* **Tables:** Use `.itm-actions-cell` and `.itm-actions-wrap` for action columns.
* **Booleans (List View):** Use badges for status: 
    * `<span class="badge badge-success">Active</span>`
    * `<span class="badge badge-danger">Inactive</span>`
* **Booleans (View Mode):** Use icons: `1` = ✅, `0` = ❌.
* **Dynamic Selects:** Enable quick-add functionality: `<option value="__add_new__">➕</option>`.
* **Color Fields:** Use color picker UI: `<input type="color" name="hex_color" id="cable-hex-color-picker" value="#008000">`.
* **Date Fields:** Show date picker UI.

---

## 🛠 Setup & Debugging
* **Dev Credentials:** `localhost` | `root` | `itmanagement`.
* **Logs:** System errors are piped to `ROOT_PATH . 'error_log.txt'`.
* **Testing:** Browser screenshots are not supported; rely on verbose error logging.
