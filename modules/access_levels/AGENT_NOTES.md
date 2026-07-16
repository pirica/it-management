# AGENT_NOTES.md - Access Levels

## 1. Module Purpose
This module manages system-wide access level definitions (e.g., "Full", "Limited"). These levels are typically used to categorize the depth of access a user or role has across the application.

## 2. Key Tables
- **access_levels** — stores the access level names and their active status.

## 3. Required Relationships
- **access_levels** → depends on **companies** (via `company_id`).
- **access_levels** → referenced by other modules that require access tiering.

## 4. Business Rules (Critical for Agents)
- **Unique Name per Company**: The `name` must be unique within the same `company_id`.
- **Tenant Isolation**: Users can only see and manage access levels belonging to their active company.
- **Active Status**: Inactive access levels should be hidden from selection dropdowns in other modules.

## 5. UI Behavior Requirements
- **Standard CRUD**: Supports list, view, create, edit, and delete.
- **Search & Sort**: The index view must support searching by name and sorting.
- **CSRF Protection**: All mutations (create, edit, delete, bulk actions) must include a valid CSRF token.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import of access levels.

## 7. File Structure
- **index.php** — main list view and bulk action handler.
- **create.php** — entry point for creating new records (wraps `index.php`).
- **edit.php** — entry point for editing records (wraps `index.php`).
- **delete.php** — handles single and bulk deletions.
- **list_all.php** — utility for listing all records.
- **view.php** — detailed view of a single record.

## 8. Multi-Tenant Rules
- All queries MUST filter by `company_id = ?`.
- Use `$_SESSION['company_id']` as the source of truth for the current tenant.

## 9. Audit Logging Requirements
- Changes to `access_levels` are automatically logged via database triggers (`trg_access_levels_audit_*`) to the `audit_logs` table.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Case Sensitivity**: Be mindful of case sensitivity in names depending on the database collation. [Cursor-Valid]
- **Foreign Key Constraints**: Deleting an access level may fail if it is referenced by other tables (check constraints). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM access_levels WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO access_levels (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
This module is a core lookup. Ensure that standard names like "Full" and "Limited" are preserved as they might be expected by system-level logic.
