# AGENTS.md

This file provides instructions and context for AI agents working on the IT Management System.

## Project Overview
An IT Asset Management System built with PHP and MySQL. It features a GitHub Copilot theme with light/dark mode and supports multi-company operations.

## Tech Stack
- **Language**: PHP 8.4+
- **Database**: MySQL 8.0+ (using `mysqli` extension, NOT PDO)
- **Frontend**: Custom CSS (`css/styles.css`), Vanilla JS, GitHub Copilot theme.
- **Environment**: Apache 2.4+, No Composer.

## Key Directories
- `config/`: Configuration files (`config.php`).
- `includes/`: Common UI components, headers, sidebars, and utility functions.
- `modules/`: Feature-specific CRUD logic (e.g., equipment, printers, workstations).
- `scripts/`: Maintenance and security audit scripts.
- `js/`: Shared JavaScript tools.
- `images/`: Equipment photo uploads (must exist).
- `tickets_photos/`: Support ticket image uploads (must exist).
- `backups/`: Database backup storage (must exist).

## Coding Standards

### Security
- **SQL Injection**:
    - ALWAYS use MySQLi prepared statements for user-provided data.
    - Use `itm_is_safe_identifier($name)` from `config/config.php` to validate dynamic table or column names.
    - `(int)` type-casting is acceptable for simple numeric safety in queries.
    - Use `itm_run_query($conn, $sql)` for general queries with error trapping.
    - Run `php scripts/check_sql_injection_coverage.php` to audit your changes.
- **CSRF Protection**:
    - ALL POST request handlers must call `itm_require_post_csrf()` from `config/config.php`.
    - Include a hidden input with `name="csrf_token"` and `value="<?= itm_get_csrf_token() ?>"` in all forms.
    - Run `php scripts/check_csrf_coverage.php` to verify protection.
- **Data Sanitization**:
    - Use `sanitize($data)` for outputting user-provided strings in HTML to prevent XSS.

### PHP Development Patterns
- **Pathing**: Use `ROOT_PATH` (defined in `config/config.php`) with a trailing slash for filesystem operations.
- **Variable Scoping**: When including shared files (like `includes/sidebar.php`), use unique, prefixed variable names (e.g., `$itm_item`) to avoid collisions with the calling script.
- **Error Logging**: Application errors are standardized to be written to `ROOT_PATH . 'error_log.txt'`.
- **Database Connection**: Use the global `$conn` object.
- **UI Configuration**: UI preferences and defaults are managed via `itm_get_ui_configuration()` in `includes/ui_config.php`.

### UI/UX Standards
- **CSS**: Only use `css/styles.css`. The file `css/style.css` is deprecated/deleted.
- **Layout**: The standard page wrapper structure is: `<div class="container">` > `<div class="main-content">` > `<div class="content">`.
- **Buttons**:
    - Use `btn-primary` for main actions/add-buttons.
    - Use `btn-sm` for table row actions.
- **Tables**:
    - Use `itm-actions-cell` and `itm-actions-wrap` classes for table action columns.
    - Tables within `.content .card` automatically receive Export (Excel/PDF) and Import functionality via `js/table-tools.js`.
- **Forms**:
    - Use `data-addable-select="1"` on `<select>` elements to enable the "+" quick-add option via `js/select-add-option.js`.

### Maintenance & Setup
- **Directory Requirements**: Ensure `images/`, `tickets_photos/`, and `backups/` directories exist and have proper write permissions.
- **Audit Logging**: The application sets MySQL session variables (`@app_user_id`, etc.) in `config/config.php` to support database-level audit triggers.
