# AGENT_NOTES.md - Employee Type

## 1. Module Purpose
Tenant-scoped lookup for employee classification labels (`Team member`, `Internship`, and custom types). Referenced by `employees.employee_type_id`.

## 2. Key Tables
- **employee_type** — `name_type`, `active`, standard tenant fields.
- **employees** — optional FK `employee_type_id`.

## 3. Required Relationships
- **employee_type** → depends on **companies**.
- **employees.employee_type_id** → **employee_type.id** (`ON DELETE SET NULL`).

## 4. Business Rules (Critical for Agents)
- **Unique name**: `name_type` must be unique per `company_id`.
- Seed data ships **Team member** and **Internship** for all five demo companies in `database.sql`.
- Employees default to **Team member** on create/import when no type is supplied.

## 5. UI Behavior Requirements
- Standard flattened CRUD (`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`).
- Label column is `name_type` (humanized as **Type Name** in forms).
- Employees create/edit use `data-add-table="employee_type"` with `data-add-label-col="name_type"`.

## 6. API Actions (If Applicable)
- **import_excel_rows** on `employee_type/index.php`.

## 7. File Structure
- Flat CRUD under `modules/employee_type/`.

## 8. Multi-Tenant Rules
- All queries filter by `company_id`.

## 9. Audit Logging Requirements
- Database triggers: `trg_employee_type_audit_insert|update|delete`.

## 10. Common Pitfalls
- Do not rename `name_type` to `name` — FK helpers and employee selects rely on `name_type`.
- Clearing `employee_type_id` on employees is allowed; resignations report still includes rows when type filter allows NULL.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare('SELECT id, name_type FROM employee_type WHERE company_id = ? AND active = 1 ORDER BY name_type');
$stmt->bind_param('i', $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare('INSERT INTO employee_type (company_id, name_type) VALUES (?, ?)');
$stmt->bind_param('is', $companyId, $nameType);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Regression: `php scripts/verify_employee_type_resignations.php`.
