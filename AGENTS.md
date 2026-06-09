# 🤖 AI Agent Guidelines for IT Management System

Welcome, Agent. This document provides critical context, standards, and procedures for working on this IT Management System.

## 🏗️ Core Architecture & Tech Stack

- **Runtime:** PHP 7.4.33 (Strict compatibility required).
- **Database:** MySQL 8.0+ using **mysqli** extension only (**No PDO**).
- **Frontend:** Vanilla JS, jQuery, Bootstrap 4.6.2.
- **Dependencies:** **Zero external dependencies.** No Composer, no NPM. All libraries (e.g., `xlsx.full.min.js`) are vendored in `js/vendor/`.
- **Multi-tenancy:** Data is strictly scoped by `company_id`. Most tables must have a `company_id` column and queries must filter by it.
- **Deployment:** Apache 2.4+ with `RewriteBase /it-management/`.

## 🔒 Security Standards

### 1. SQL Injection Prevention
- Use **prepared statements** (`mysqli_prepare`, `mysqli_stmt_bind_param`) for all user-supplied data in `WHERE`, `INSERT`, and `UPDATE` clauses.
- Use `itm_is_safe_identifier($name)` and `cr_escape_identifier($name)` for dynamic table/column names.
- Fallback: Use `mysqli_real_escape_string($conn, $data)` only when prepared statements are not feasible.

### 2. CSRF Protection
- All state-changing requests (POST/PUT/DELETE) **must** include a CSRF token.
- Generate with `itm_get_csrf_token()` and validate with `itm_validate_csrf_token($_POST['csrf_token'] ?? '')`.

### 3. Audit Logging
- Mutations must be logged. This is handled either via:
  - Database triggers (`trg_{table}_audit_*` in `database.sql`).
  - Explicit calls to `itm_log_audit()` in PHP.
- Ensure any new table includes audit triggers in `database.sql`.

### 4. Password Security
- Passwords in the `passwords` module are encrypted with **AES-256-CBC** using a session-based key derived from the user's master key.
- Never store the master key in the database; only store its hash (`password_hash`).

## 🛠️ Coding Standards & Patterns

### Database Schema
- **Mandatory Columns:** Most tables require `active` (tinyint, default 1), `company_id` (int), `created_at` (timestamp), and `updated_at` (timestamp).
- **Foreign Keys:** Use InnoDB foreign key constraints with `ON DELETE CASCADE` or `ON DELETE SET NULL` as appropriate.

### Module Patterns
- **CRUD Wrapper:** Many modules use a pattern where local scripts set `$crud_table` and `$crud_title` then require a shared index/handler.
- **Imports:** Use the shared `itm_handle_json_table_import` for Excel/CSV imports.
- **UI Consistency:**
  - Use `white-space: nowrap` for table cells to prevent mid-word breaking.
  - Dropdowns must be explicitly hidden: `$('.dropdown-toggle').dropdown('hide');`.
  - Use specific icons: `⌛` for timestamps, `⚙️` for settings.

### Specific Module Logic
- **Floor Designer:**
  - Normalize layer names (replace spaces with hyphens).
  - Use name-based fallback for point types if ID matching fails.
  - Exclude spatial coordinates from configuration `UPDATE` statements to prevent resets.
- **Employees:**
  - Map 'Hilton ID' to `external_id` (always display as 'External ID' in UI).
  - Auto-create Departments/Positions during import if missing.
- **Alerts:**
  - Global alerts: `assigned_to_user_id IS NULL`.
  - Private alerts: `assigned_to_user_id` set to specific User ID.

## 🧪 Testing & Verification

### Mandatory Automated Checks
Before submitting any change, you **must** run:
1. **Linter:** `php -l` on all touched files.
2. **Smoke Test:** `bash scripts/smoke_test.sh` (Checks Lint, CSRF, and SQLi).
3. **Unit Tests:** `php scripts/run_tests.php` (Runs the PHPUnit suite).
4. **Audit Coverage:** `php scripts/check_audit_logs_coverage.php` (If schema or CRUD changed).

### Manual Verification
- **UI Changes:** Start the PHP server (`php -S localhost:8000`) and verify via browser or Playwright.
- **Database Changes:** Verify the table count and schema integrity:
  - `mysql -u root -pitmanagement -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='itmanagement';"` (Should be ~88 tables).
  - `php scripts/verify_database_schema.php`.

## 📂 Project Structure

- `/config/config.php`: Central configuration and database connection.
- `/modules/`: Individual feature modules.
- `/scripts/`: Maintenance, API documentation, and CLI tools.
- `/includes/`: Shared logic and visibility helpers.
- `/api-examples/`: Reference scripts for programmatic interaction.

## 🚀 Deployment

- **Root URL:** `http://localhost/it-management/`
- **MySQL Credentials:** User: `root`, Password: `itmanagement`.
- **App Credentials:** Username: `Admin`, Password: `Admin`.
- **Services:** Start MySQL with `mysqld --user=mysql` and Apache with `apachectl start`.

