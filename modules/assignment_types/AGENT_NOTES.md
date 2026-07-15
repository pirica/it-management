# AGENT_NOTES.md - Assignment Types

## 1. Module Purpose
Lookup table for types of assignments (e.g., "Permanent", "Temporary"). These are used to categorize how assets or roles are assigned to employees.

## 2. Key Tables
- **assignment_types** — stores assignment type names and active status.

## 3. Required Relationships
- **assignment_types** → depends on **companies**.
- **assignment_types** → referenced by modules handling assignments (e.g., equipment assignments).

## 4. Business Rules (Critical for Agents)
- **Unique Name**: The `name` must be unique within a `company_id`.
- **Tenant Scope**: Records are isolated per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- All queries must filter by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- Deleting an assignment type that is actively used by assignment history records will cause foreign key constraint failures if RESTRICT is applied. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM assignment_types WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO assignment_types (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Core lookup for asset management.
