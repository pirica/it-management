# AGENT_NOTES.md - Bookmark Folders

## 1. Module Purpose
Manages hierarchical folders for organizing bookmarks. Folders can be private to a user or shared within a company.

## 2. Key Tables
- **bookmark_folders** — folder metadata and hierarchy (`company_id`, `employee_id`, `parent_folder_id`, `name`, `position`, `shared`, soft-delete/audit columns).
- **Keys:** `PRIMARY KEY (id)` only for business identity. **No** UNIQUE on `name` (duplicates allowed for any owner/parent/company). Non-unique KEYs remain on `company_id`, `employee_id`, `parent_folder_id`.

## 3. Required Relationships
- **bookmark_folders** → depends on **companies**, **employees**.
- **bookmark_folders** → self-references via `parent_folder_id`.

## 4. Business Rules (Critical for Agents)
- **Ownership/Sharing**: A folder is either owned by a specific `employee_id` or marked as `shared = 1`.
- **Folder names (duplicates OK):**
  - Multiple folders may share the same `name` (same employee, same parent, or across the company).
  - Do **not** add `UNIQUE (company_id, name)`, `UNIQUE (company_id, employee_id, name)`, or parent-scoped name UNIQUEs unless product rules change.
  - Folder names may be reused immediately after hard delete (no UNIQUE on `name`).
  - Tenant unique-key audit skips this table — see `includes/database_sql_unique_audit.php` and `scripts/check_database_sql_company_name_uniques.php`.
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
- **Private data (no audit):** `bookmark_folders` and `bookmarks` are exempt from `audit_logs` and `trg_*_audit_*` triggers per `AGENTS.md` → **Private data — no audit trail**. Do not add PHP audit hooks for folder/bookmark mutations.

## 10. Common Pitfalls

- **Hard delete (private data):** `index.php` delete actions use `DELETE FROM bookmark_folders` (not soft-delete). Dual-pane folder delete lives in `modules/bookmarks/delete_folder.php`. Employee delete cascades via FK. [Cursor-Fixed]
- Do not add PHP “name already exists” guards for folders unless intentionally changing product rules. [Cursor-Valid]
- Legacy installs: drop `uq_bookmark_folders_company_scope` if still present (`db/` comment under `CREATE TABLE bookmark_folders`). [Cursor-Valid]
- **Circular References**: Avoid setting a folder's parent to itself or one of its children. [Cursor-Valid]
- **Ambiguous Columns**: When joining with the `bookmarks` table, both have `active` and `employee_id` columns—always use table aliases (e.g., `bf.active`). [Cursor-Valid]

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
