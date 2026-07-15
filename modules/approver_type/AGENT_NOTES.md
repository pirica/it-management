# AGENT_NOTES.md - Approver Type

## 1. Module Purpose
Lookup table for categories of approvers (e.g., "GM Approval", "HOD Approval").

## 2. Key Tables
- **approver_type** — stores approver type descriptions.

## 3. Required Relationships
- **approver_type** → depends on **companies**.
- **approver_type** → referenced by **approvers**.

## 4. Business Rules (Critical for Agents)
- **Unique Description**: `approver_type_description` must be unique per company.

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
- Similar to other lookup tables, avoid deleting records that are currently in use. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM approver_type WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO approver_type (company_id, approver_type_description) VALUES (?, ?)");
$stmt->bind_param("is", $companyId, $description);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used to define the hierarchy of who can approve what.
