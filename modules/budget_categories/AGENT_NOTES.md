# AGENT_NOTES.md - Budget Categories

## 1. Module Purpose
Lookup table for categorizing budgets and expenses (e.g., "Hardware", "Software Licenses").

## 2. Key Tables
- **budget_categories** — stores category names and descriptions.

## 3. Required Relationships
- **budget_categories** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Category name must be unique within a company.

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
- Deleting a category that is referenced by budget reports or expenses. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM budget_categories WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO budget_categories (company_id, name, description) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $companyId, $name, $description);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used for high-level financial reporting.
