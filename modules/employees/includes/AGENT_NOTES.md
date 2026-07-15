# AGENT_NOTES.md - Employees UI Fields

## 1. Module Purpose
Contains modular PHP files included by `create.php` and `edit.php` to render specific field groups (e.g., profile picture upload, birthday, employee code, requests, dates, and lookups) inside form rows in a consistent layout.

## 2. Key Tables
- **employees** — main record table where all these fields persist.
- **employee_type** — lookup source for the employee type select field.

## 3. Required Relationships
- IT Location dropdown maps to **it_locations**.
- Role and Access Level dropdowns map to **employee_roles** and **access_levels**.

## 4. Business Rules (Critical for Agents)
- **Attribute Parity**: Ensure that input `name` and `id` attributes match expected database columns exactly. Do not alter them, as doing so will break POST parsing in the parent handlers and validation during Excel imports.
- **Date Format**: Standard picker inputs use standard formats; view formatting employs the `dd/mm/yyyy` display standard.

## 5. UI Behavior Requirements
- **profile_fields.php** — Renders circular drag-and-drop avatar upload zone above the grid.
- **profile_employee_type_fields.php** — Renders the Type select with quick-add (➕) option.
- **profile_birthday_fields.php** — Renders birthday and `hide_year` checkbox.
- **profile_termination_date_field.php** — Renders the termination date picker immediately following Employee Type.

## 6. API Actions (If Applicable)
- Lookup tables use the Select Options API (`__add_new__` quick-add).

## 7. File Structure
- **profile_fields.php** — Drag-and-drop profile photo.
- **profile_employee_code_field.php** — Optional employee code field.
- **profile_location_field.php** — Optional IT Location dropdown.
- **profile_request_fields.php** — Request dates and requesters.
- **profile_start_date_field.php** — Start/admission date.
- **profile_employee_type_fields.php** — Employee type select.
- **profile_termination_date_field.php** — Termination/resignation date.
- **profile_birthday_fields.php** — Birthday and hide year.
- **profile_role_access_fields.php** — Roles & access permissions selects.
- **index.html** — Directory listing prevention.

## 8. Multi-Tenant Rules
- All dropdown selections (departments, locations, positions, roles, statuses) must list only options that belong to the active `$company_id`.

## 9. Audit Logging Requirements
- Changes to any fields inside these inputs are logged unconditionally to `audit_logs` via the `employees` triggers.

## 10. Common Pitfalls
- Forgetting that `edit.php` and `create.php` both include these files. Ensure modifications do not cause undefined variable warnings in either flow.
