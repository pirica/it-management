# AGENT_NOTES.md - Employees

## 1. Module Purpose
The central module for managing employee records, including contact info, hierarchy, and employment details.

## 2. Key Tables
- **employees** — main employee data.

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

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Photo Upload**: Supports profile photos (check `images/` directory usage).
- **Hierarchy Mapping**: Edit form should allow selecting a manager from other employees in the same company.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import with auto-lookup resolution for departments and positions.

## 7. File Structure
- Standard CRUD structure + `delete_clear_table.php`.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Circular Reporting**: Avoid setting an employee to report to themselves or creating a loop.
- **Orphaned Users**: If an employee is deleted, consider what happens to the linked `user_id`.

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
