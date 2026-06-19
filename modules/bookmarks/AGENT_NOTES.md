# AGENT_NOTES.md - Bookmarks

## 1. Module Purpose
Hierarchical bookmark manager with private and shared links, folder tree, drag-and-drop, and import/export.

## 2. Key Tables
- **bookmarks** — URL records (`title`, `url`, `shared`, `employee_id`, `folder_id`).
- **bookmark_folders** — folder tree (emoji icons in sidebar).

## 3. Required Relationships
- **bookmarks** → **companies**, **employees**, **bookmark_folders**.

## 4. Business Rules (Critical for Agents)
- **Privacy:** filter by `employee_id` for private bookmarks and `company_id` for shared ones.
- **Visibility:** row visible when `(employee_id = logged employee OR shared = 1)` and `company_id` matches.
- **Permissions:** shared bookmarks read-only for regular users; admins and creators retain full CRUD.
- **Dual-pane UI:** left folder tree (📁/📂), main list view.
- **Drag-and-drop:** folders reordered/reparented via DnD interactions.
- **Import/export:** browser HTML bookmark files, CSV, and XLSX.
- **Deletion:** single delete may require `bulk_action = 'single_delete'` for shared-handler compatibility.

## 5. UI Behavior Requirements
- Dual-pane layout: left folder tree (📁/📂 emoji), right bookmark list.
- View modes: `all`, `private`, `shared` via `?view=`; folder filter via `?folder_id=`.
- Folder drag-and-drop reparenting posts `action=move_folder` (CSRF on form).
- Shared bookmarks: edit/delete only for admin or owning `employee_id` (`bkm_can_edit_bookmark()`).
- **Responsive:** dual-pane stacks below 1200px; bookmark cards single column below 480px.
- `list_all.php` provides flattened table view; bulk delete toolbar is always shown (`$showBulkActions = true`), not gated by `records_per_page`.
- Excel import endpoint: `data-itm-db-import-endpoint="list_all.php"` on the flattened list table; dual-pane `index.php` handles JSON import but has no import table attribute.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php` or `list_all.php`) — bulk import via `itm_handle_json_table_import($conn, 'bookmarks', $company_id)`; 📥 Import Excel on the flattened list uses `list_all.php` as `data-itm-db-import-endpoint`.
- **move_folder** (POST on `index.php`) — `folder_id`, `new_parent_id`; updates `bookmark_folders.parent_folder_id` when admin or folder owner.
- **import.php** — browser HTML bookmark file upload (`bkm_parse_html_bookmarks()`).
- **export.php** / **export.js** — CSV, XLSX, and Netscape HTML export.

## 7. File Structure
- `index.php` — dual-pane UI, folder move POST, JSON import.
- `list_all.php` — flattened list with bulk actions.
- `create.php`, `edit.php`, `delete.php`, `view.php` — bookmark CRUD.
- `create_folder.php`, `edit_folder.php`, `delete_folder.php` — folder CRUD.
- `helpers.php` — `bkm_get_folders()`, `bkm_build_folder_tree()`, permission helpers, HTML import parser.
- `import.php`, `export.php`, `export.js` — import/export flows.

## 8. Multi-Tenant Rules
- `company_id` on all rows; private rows also scoped by `employee_id`.
- Shared bookmarks (`shared = 1`) visible to all company users but editable only by admin or creator.

## 9. Audit Logging Requirements
- No dedicated triggers on `bookmarks` / `bookmark_folders` in `database.sql`; rely on application logging if extended.
- Folder/bookmark mutations should remain CSRF-protected POST handlers.

## 10. Common Pitfalls
- SQL ambiguity when joining `bookmark_folders` — alias `active`, `employee_id`.
- URLs missing scheme — prepend `http://` or `https://` when saving.
- `delete.php` expects `bulk_action=single_delete` for inline index deletes.
- Folder delete moves bookmarks to root — do not CASCADE-delete bookmark rows silently.

## 11. Examples of Safe Code Patterns

### Safe visibility WHERE clause
```php
$where = 'company_id = ? AND active = 1 AND (employee_id = ? OR shared = 1)';
$stmt = $conn->prepare("SELECT * FROM bookmarks WHERE $where AND folder_id IS NULL ORDER BY title ASC");
$stmt->bind_param('ii', $companyId, $employeeId);
```

### Permission check before edit
```php
if (!bkm_can_edit_bookmark($bookmark, $employeeId, $isAdmin)) {
    header('Location: index.php');
    exit;
}
```

## 12. Module Owner Notes (Optional)
Core productivity feature; folder module: `modules/bookmark_folders/`.
