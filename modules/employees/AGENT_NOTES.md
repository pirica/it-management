# AGENT_NOTES.md - Employees

## 1. Module Purpose
The central module for managing employee records, including contact info, hierarchy, and employment details.

## 2. Key Tables
- **employees** — main employee data (`photo`, `birthday`, `hide_year` among profile fields).

## 3. Required Relationships
- **employees** → depends on **companies**.
- **employees** → depends on **departments**.
- **employees** → depends on **employee_positions**.
- **employees** → depends on **employee_statuses**.
- **employees** → self-references via `reports_to`.
- **employees** → optionally links to **users** via `user_id`.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Org Chart Visibility:** Only employees with `on_orgchart = 1` and an active status are shown on the Org Chart.
- **Contact Visibility:** Only employees with `on_contacts = 1` and an active status are shown in the Contacts module.
- **Unique Code:** `employee_code` should be unique per company if provided.
- **Import (mandatory):**
  - Header aliases: `Hilton ID` → `external_id`, `Position Title` → `employee_position_id`, `Department Name` → `department_id`, sort markers like `Id▼` → `id`.
  - If `id` column present, update existing row instead of duplicate insert.
  - Auto-create **departments** and **employee_positions** when names/titles not found.
  - Email classification: personal domains (gmail.com, etc.) → `personal_email`; others → `work_email`.
  - Boolean markers: `✅` / `Active` → `1`, `❌` → `0` for `on_contacts`, `on_orgchart`.
- **Profile photo:** Stored under `files/{company_id}/Private/{username}_{employee_id}/profile/` as `{username}_{employee_id}.png` or `.jpg`. Requires `username` and the employee row `id` (no linked login account required). Legacy photos under `{username}_{user_id}` still resolve when `employees.user_id` is set. `employees.photo` holds the filename; serve via `emp_profile_photo_url()` → `itm_files_serve_url()`. Upload uses `emp_profile_photo_store_upload()` in `includes/employee_profile_photo.php` with `itm_ensure_files_storage_directory()`. Explorer `file.php` allows any authenticated company user to read `Private/*/profile/` paths.
- **Birthday / hide year:** `birthday` is a nullable `date`. `hide_year` masks the year in display (`j M` vs `j M Y`) via `emp_format_birthday_display()`. Birthdays module reads these fields for the monthly list.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Profile fields (create/edit):** `includes/profile_fields.php` — circular drag-and-drop photo above the form grid (scoped `.itm-employee-photo-*` CSS). Uses `js/itm-upload-helper.js` on `.itm-employee-photo-target`. `includes/profile_birthday_fields.php` — `birthday` date input and `hide_year` checkbox, placed immediately after Employment Status. Forms use `enctype="multipart/form-data"`. Photo upload needs `username` and employee `id` — `edit.php` must pass `id` into `emp_profile_photo_store_upload()` (create inserts the row first, then uploads).
- **View:** Profile thumbnail when `photo` + linked user exist; birthday respects `hide_year`.
- **Hierarchy Mapping**: Edit form should allow selecting a manager from other employees in the same company.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import with auto-lookup resolution for departments and positions.

## 7. File Structure
- Standard CRUD structure + `delete_clear_table.php`.
- **includes/profile_fields.php** — shared profile photo drag-and-drop for create/edit.
- **includes/profile_birthday_fields.php** — birthday and hide_year fields (after Employment Status).
- **includes/employee_profile_photo.php** (repo `includes/`) — path, upload, URL, and birthday display helpers.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Circular Reporting**: Avoid setting an employee to report to themselves or creating a loop.
- **Profile photo upload on edit:** `emp_profile_photo_store_upload()` needs `username` and `id` on the employee array; omitting `id` in `edit.php` shows a misleading username error.

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
The core of the HR/Identity management in the system.
