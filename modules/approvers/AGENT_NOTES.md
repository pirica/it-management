# AGENT_NOTES.md - Approvers

## 1. Module Purpose
Maps specific employees to approver types, departments, and positions to define who has approval authority.

## 2. Key Tables
- **approvers** — the mapping table for approval authority.

## 3. Required Relationships
- **approvers** → depends on **companies**.
- **approvers** → depends on **employees**.
- **approvers** → depends on **employee_positions**.
- **approvers** → depends on **departments**.
- **approvers** → depends on **approver_type**.

## 4. Business Rules (Critical for Agents)
- **Unique Employee per Company**: An employee can only be listed once in the approvers table for a given company (`uq_approvers_company_scope`).
- **Referential Integrity**: All linked IDs (employee, position, department, type) must exist and be active.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Dropdown Lookups**: Form should provide searchable dropdowns for employees, positions, departments, and types.

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
- **Redundant Assignments**: Trying to assign the same employee twice as an approver. [Cursor-Valid]
- **Outdated Records**: If an employee leaves or changes position, the approver record may need manual updating or archiving. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM approvers WHERE company_id = ? AND employee_id = ?");
$stmt->bind_param("ii", $companyId, $employeeId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO approvers (company_id, employee_id, employee_position_id, department_id, approver_type_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiii", $companyId, $employeeId, $positionId, $deptId, $typeId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
This is a critical security/workflow configuration module.
## Share (temporary QR / code)
- **Capable:** `itm_qr_share_capable_module_slugs()`.
- **UI:** Share buttons on index.php inline view block.
- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.
- **Doc:** `docs/CRUD_RECORD_SHARE.md`.
