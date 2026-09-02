# AGENT_NOTES.md - Employee Statuses

## 1. Module Purpose
Lookup table for employment statuses (e.g., "Active", "Terminated", "Leave").

## 2. Key Tables
- **employee_statuses** — stores status names and active flags.

## 3. Required Relationships
- **employee_statuses** → depends on **companies**.
- **employee_statuses** → referenced by **employees**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Status name must be unique per company.
- **Active Mapping**: The `active` flag here determines if an employee with this status is considered "active" in the system (e.g., in the Org Chart).

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
- Marking a status as inactive if it's currently used by employees who should still be visible. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM employee_statuses WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO employee_statuses (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Crucial for the Org Chart visibility rules.
