# AGENT_NOTES.md - Employee Positions

## 1. Module Purpose
Lookup table for job titles and positions (e.g., "Developer", "Manager").

## 2. Key Tables
- **employee_positions** — stores position names and active status.

## 3. Required Relationships
- **employee_positions** → depends on **companies**.
- **employee_positions** → referenced by **employees**, **approvers**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Position name must be unique within a company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Deleting a position that is still assigned to active employees. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM employee_positions WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO employee_positions (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used for organizational structure and hierarchy.
