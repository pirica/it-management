# AGENT_NOTES.md - Bookmark Folders

## 1. Module Purpose
Manages hierarchical folders for organizing bookmarks. Folders can be private to a user or shared within a company.

## 2. Key Tables
- **bookmark_folders** — stores folder metadata and hierarchy.

## 3. Required Relationships
- **bookmark_folders** → depends on **companies**, **employees**.
- **bookmark_folders** → self-references via `parent_folder_id`.

## 4. Business Rules (Critical for Agents)
- **Ownership/Sharing**: A folder is either owned by a specific `employee_id` or marked as `shared = 1`.
- **Recursive Deletion**: Deleting a folder sets `parent_folder_id` of subfolders to NULL (via `ON DELETE SET NULL`) or requires manual cleanup.
- **Tenant Isolation**: Strictly scoped by `company_id`.

## 5. UI Behavior Requirements
- **Tree View**: Often displayed in a sidebar or tree structure.
- **Drag & Drop**: Often supports reordering via the `position` column.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.
- Queries must also consider `employee_id` and `shared` status for visibility.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Circular References**: Avoid setting a folder's parent to itself or one of its children. [Valid]-[2026-07-15]
- **Ambiguous Columns**: When joining with the `bookmarks` table, both have `active` and `employee_id` columns—always use table aliases (e.g., `bf.active`). [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM bookmark_folders WHERE company_id = ? AND (employee_id = ? OR shared = 1)");
$stmt->bind_param("ii", $companyId, $employeeId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO bookmark_folders (company_id, employee_id, parent_folder_id, name, shared) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiisi", $companyId, $employeeId, $parentId, $name, $shared);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for the "Explorer" and "Bookmarks" user experience.
