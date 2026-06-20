# AGENT_NOTES.md - Employees

## 1. Module Purpose
The central module for managing employee records, including contact info, hierarchy, employment details, and **login accounts** (auth columns live on `employees` after the users-table merge).

## 2. Key Tables
- **employees** — main employee data (`photo`, `birthday`, `hide_year`, `start_date`, `employee_type_id`, `termination_date`, `role_id`, `access_level_id`, `is_hidden` among profile fields).
- **employee_type** — lookup for `employees.employee_type_id` (`name_type` labels such as Team member / Internship).

## 3. Required Relationships
- **employees** → depends on **companies**.
- **employees** → depends on **departments**.
- **employees** → depends on **employee_positions**.
- **employees** → depends on **employee_statuses**.
- **employees** → optionally depends on **employee_type** via `employee_type_id`.
- **employees** → feeds **resignations** read-only weekly report via `termination_date`, `start_date`, `employment_status_id`, and `employee_type_id` (same company scope).
- **employees** → optionally depends on **it_locations** via `location_id`.
- **employees** → self-references via `reports_to`.
- **employees** → links to **employee_roles** (`role_id`), **access_levels** (`access_level_id`), and **employee_statuses** (`employment_status_id`).
- **employees** → referenced by **employee_companies**, **employee_sidebar_preferences**, session auth (`$_SESSION['employee_id']`), and audit `@app_employee_id`.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Org Chart Visibility:** Only employees with `on_orgchart = 1` and an active employment status are shown on the Org Chart.
- **Contact Visibility:** Only employees with `on_contacts = 1` and an active employment status are shown in the Contacts module.
- **Login eligibility (mandatory):** use `employment_status_id` → `employee_statuses.name` = **Active** (case-insensitive) via `includes/itm_employee_employment_status.php`. Do **not** use a deprecated `employees.active` column — `emp_drop_active_column_if_exists()` removes it on index load.
- **List scope:** administrator-only module — all entry files call `itm_require_admin()`; non-admins are redirected to the dashboard (same as companies / employee_companies). Index lists every employee row for the active session `company_id` only (`WHERE e.company_id = ?`) **excluding** `is_hidden = 1` rows (`includes/itm_employees_hidden_accounts.php`). `company_id` and `is_hidden` never render on the index table. The seed admin row (`id=1`, `company_id=1`, `is_hidden=1`) is DB-protected and omitted from list/view/edit/delete/clear-table UI flows.
- **Sidebar label:** sidebar catalog entry id `employees` must appear **once** in `itm_sidebar_base_structure()` (Employee section → `👤 Employees`). A duplicate Admin-section item with the same id previously overwrote the label as `👥 Users`.
- **Unique Code:** `employee_code` should be unique per company if provided.
- **Import (mandatory):**
  - Header aliases: `Hilton ID` → `external_id`, `Position Title` → `employee_position_id`, `Department Name` → `department_id`, sort markers like `Id▼` → `id`.
  - If `id` column present, update existing row instead of duplicate insert. Only columns provided in the import (or auto-derived with a resolved value, such as department/position IDs, email reclassification, or derived display names) are updated in the database to prevent data loss in omitted columns (`providedFields` tracking). Unchanged existing rows increment **skipped**, not **updated**. **INSERT** still applies defaults for missing columns.
  - Auto-create **departments** and **employee_positions** when names/titles not found.
  - Email classification: personal domains (gmail.com, etc.) → `personal_email`; others → `work_email`.
  - Boolean markers: `✅` / `Active` → `1`, `❌` → `0` for `on_contacts`, `on_orgchart`.
  - **Employee type:** defaults to tenant **Team member** when import omits `employee_type_id`; accepts `employee type` header mapped to `employee_type_id` (`name_type` lookup).
  - **Termination date:** `termination_date` nullable `date` on create/edit/view/list (`includes/profile_termination_date_field.php`, after Employee Type). Display and import use **dd/mm/yyyy** via `itm_format_date_display()` / `itm_parse_date_input()`. Drives **Resignations** weekly report (`modules/resignations/`) when set to a valid calendar date; downstream SQL must use `itm_sql_valid_date_predicate('e.termination_date')`, not `<> '0000-00-00'` (MySQL 8 `NO_ZERO_DATE`).
  - **Start date:** `start_date` date field after request fields; import aliases `start date`, `admission date`.
  - **Employee code / IT location / request fields:** optional nullable columns (`employee_code`, `location_id` → `it_locations`, `request_date`, `requested_by`, `termination_requested_by`) on create/edit/view/list; import accepts `employee code`, `it location`, `location`, `request date`, `requested by`, `termination requested by`.
- **Profile photo:** Stored under `files/{company_id}/Private/{username}_{employee_id}/profile/` as `{username}_{employee_id}.png` or `.jpg`. Requires `username` and the employee row `id`. Pre-merge installs may still have files under `{username}_{legacy_id}/profile/`; `emp_profile_photo_serve_path()` checks the canonical path first. `employees.photo` holds the filename; serve via `emp_profile_photo_url()` → `itm_files_serve_url()`. Upload uses `emp_profile_photo_store_upload()` in `includes/employee_profile_photo.php` with `itm_ensure_files_storage_directory()`. Explorer `file.php` allows any authenticated company user to read `Private/*/profile/` paths.
- **Auth-sensitive columns:** list and view hide `password`, `vault_key_hash`, and reset-token fields via `includes/itm_employees_auth_sensitive_fields.php` (`itm_employees_auth_sensitive_field_names()` merged into index list `$hiddenColumns`; `view.php` uses an explicit field whitelist).
- **Hidden accounts (`is_hidden`):** DB-only flag (`TINYINT`, default `0`) on `employees`; set to `1` directly in MySQL to protect admin/service accounts. Never on create/edit/view forms or index columns. Helpers: `includes/itm_employees_hidden_accounts.php` (`itm_employees_sql_visible_only_predicate()`, `itm_employees_is_hidden_account()`). Import skips updates to hidden rows; delete/clear-table skip hidden rows.
- **Admin delete dependencies:** `employees_delete_record()` (admin-only entry via `delete.php`) calls `itm_employees_detach_delete_dependencies()` before `itm_can_delete_record()` so inbound rows such as `attempts`, `audit_logs`, `employee_companies`, and `employee_system_access` no longer block deletion. Detach runs in the same transaction as the parent `DELETE`. Hard blockers (for example `approvers` when not removed) still surface via `itm_can_delete_record()`. Helper: `includes/itm_employees_delete_dependencies.php`; shared delete logic: `delete_functions.php`.
- **Role / access level:** `role_id` → `employee_roles.name`, `access_level_id` → `access_levels.name` on create/edit/view/index via `includes/profile_role_access_fields.php` and list FK label joins.
- **Birthday / hide year:** `birthday` is a nullable `date`. `hide_year` masks the year in display (`j M` vs `j M Y`) via `emp_format_birthday_display()`. Birthdays module reads these fields for the monthly list.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Profile fields (create/edit):** `includes/profile_fields.php` — circular drag-and-drop photo above the form grid (scoped `.itm-employee-photo-*` CSS). Uses `js/itm-upload-helper.js` on `.itm-employee-photo-target`. `includes/profile_employee_code_field.php` — optional `employee_code` text input after External ID. `includes/profile_location_field.php` — optional `location_id` select (`it_locations`, default NULL). `includes/profile_request_fields.php` — `request_date`, `requested_by`, `termination_requested_by` before start date. `includes/profile_start_date_field.php` — `start_date` date input. `includes/profile_employee_type_fields.php` — `employee_type_id` select with `__add_new__` quick-add (`data-add-label-col="name_type"`), default **Team member**. `includes/profile_termination_date_field.php` — `termination_date` date input, placed immediately after Employee Type. `includes/profile_birthday_fields.php` — `birthday` date input and `hide_year` checkbox, placed after Termination Date. Forms use `enctype="multipart/form-data"`. Photo upload needs `username` and employee `id` — `edit.php` must pass `id` into `emp_profile_photo_store_upload()` (create inserts the row first, then uploads).
- **View / list:** `employee_code`, `location_id` (`it_locations.name`), `request_date`, `requested_by`, `termination_requested_by`, `start_date`, `employee_type_id`, and `termination_date` render human-readable values; never show raw FK IDs when a label exists. Date fields display as **dd/mm/yyyy** (`itm_format_date_display()`). List columns include `employee_code` and `location_id` (no longer hidden).
- **View:** Profile thumbnail when `photo` + linked user exist; birthday respects `hide_year`.
- **Hierarchy Mapping**: Edit form should allow selecting a manager from other employees in the same company.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import with auto-lookup resolution for departments and positions.

## 7. File Structure
- Standard CRUD structure + `delete_clear_table.php`, `delete_functions.php`.
- **includes/profile_fields.php** — shared profile photo drag-and-drop for create/edit.
- **includes/profile_employee_code_field.php** — optional employee code text field.
- **includes/profile_location_field.php** — optional IT location FK select.
- **includes/profile_request_fields.php** — request date and requester fields.
- **includes/profile_start_date_field.php** — admission/start date field.
- **includes/profile_employee_type_fields.php** — employee type select before termination/birthday fields.
- **includes/profile_termination_date_field.php** — termination date field after employee type.
- **includes/profile_birthday_fields.php** — birthday and hide_year fields.
- **includes/profile_role_access_fields.php** — role and access level FK selects.
- **includes/employee_profile_photo.php** (repo `includes/`) — path, upload, URL, and birthday display helpers.
- **includes/itm_employees_hidden_accounts.php** (repo `includes/`) — DB-only `is_hidden` column ensure + UI exclusion predicate.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Circular Reporting**: Avoid setting an employee to report to themselves or creating a loop.
- **Profile photo upload on edit:** `emp_profile_photo_store_upload()` needs `username` and `id` on the employee array; omitting `id` in `edit.php` shows a misleading username error.
- **Resignations report:** after setting `termination_date`, confirm the active company matches `employees.company_id`. If the row is missing from the weekly report, run `php scripts/debug_resignations_termination_date.php --date=18/06/2026 --company_id=4 --employee_id=432 --week=25 --month=6 --year=2026`.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM employees WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO employees (company_id, first_name, last_name, department_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $companyId, $firstName, $lastName, $deptId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The core of the HR/Identity management in the system. Regression: `php scripts/employee_fields_missing.php` — schema and critical UI coverage for `employees` columns (including `termination_date`). Resignations weekly filter debug: `php scripts/debug_resignations_termination_date.php` (catalogued in `scripts/scripts.php`).
