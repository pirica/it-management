# AGENT_NOTES.md - Bookmarks

## 1. Module Purpose
Hierarchical bookmark manager with private and shared links, folder tree, drag-and-drop, and import/export.

## 2. Key Tables
- **bookmarks** — URL records (`title`, `url`, `shared`, `employee_id`, `folder_id`). Duplicate titles are allowed; row identity is `id`.
- **bookmark_folders** — folder tree (emoji icons in sidebar). **Duplicate folder names are allowed** (same employee / company / parent). Identity is `PRIMARY KEY (id)` only — there is **no** UNIQUE on `name` (and no `uq_bookmark_folders_company_scope`).

## 3. Required Relationships
- **bookmarks** → **companies**, **employees**, **bookmark_folders**.

## 4. Business Rules (Critical for Agents)
- **Privacy:** filter by `employee_id` for private bookmarks and `company_id` for shared ones.
- **Visibility:** row visible when `(employee_id = logged employee OR shared = 1)` and `company_id` matches.
- **Permissions:** shared bookmarks read-only for regular users; admins (`itm_is_admin()`) and creators retain full CRUD.
- **Dual-pane UI:** left folder tree (📁/📂), main list view.
- **Drag-and-drop:** folders reordered/reparented via DnD interactions.
- **Folder / bookmark naming (no uniqueness):**
  - Do **not** re-add `UNIQUE (company_id, …, name)` on `bookmark_folders` or name UNIQUEs on `bookmarks`.
  - UI and import may create multiple folders or bookmarks with the same display name; distinguish rows by `id`.
  - Tenant unique-key audit (`php scripts/check_database_sql_company_name_uniques.php` / `includes/database_sql_unique_audit.php`) **skips** `bookmark_folders` and `bookmarks`.
- **Import/export:** browser HTML bookmark files, CSV, and XLSX.
- **Deletion:** single delete may require `bulk_action = 'single_delete'` for shared-handler compatibility.

## 5. UI Behavior Requirements
- Dual-pane layout: left folder tree (📁/📂 emoji), right bookmark list.
- View modes: `all`, `private`, `shared` via `?view=`; folder filter via `?folder_id=`.
- **Search:** `index.php` and `list_all.php` match bookmark title/url/notes and folder name (`bookmark_folders` JOIN or EXISTS). Dual-pane list uses `$searchRaw` + `$searchConditions`, emoji-only `🔙` reset, server-side sort (`$_GET['sort']`/`dir`, `ORDER BY $sortSql`), and pagination (`LIMIT $offset, $perPage`, Previous/Next).
- Dual-pane `index.php` list heading uses `data-itm-new-button-managed="server"` with centered `sanitize($moduleListHeading)` from `itm_sidebar_label_for_module()`. POST mutations on `index.php` call `itm_require_post_csrf()` (JSON `import_excel_rows` keeps token validation on the JSON body).
- Dual-pane `index.php` bulk toolbar (`Select to Delete`, Cancel, Clear Table, **Move to** folder select) and row `ids[]` checkboxes appear only when `$showBulkActions = ($totalRows >= $perPage)`; uses shared `bulk-delete-selection.js` with `#select-all-rows`.
- Folder drag-and-drop reparenting posts `action=move_folder` (CSRF on form).
- Shared bookmarks: edit/delete only for admin or owning `employee_id` (`bkm_can_edit_bookmark()`).
- Shared checkbox on bookmark/folder forms: unchecked = private **🔒**; checked = shared **🔓** (`itm-shared-indicator`). Active uses `itm-check-indicator` (✅/❌). Change listener must live in its own `<script>` block after closed `<script src="..."></script>` tags (`create.php`, `edit.php`, `create_folder.php`, `edit_folder.php`).
- **Responsive:** dual-pane stacks below 1200px; bookmark cards single column below 480px.
- `list_all.php` provides flattened table view; bulk delete toolbar is always shown (`$showBulkActions = true`), not gated by `records_per_page`.
- Excel import endpoint: `data-itm-db-import-endpoint="list_all.php"` on the flattened list table. Dual-pane `index.php` uses **custom** Tools import/export (`import.php`, `exportBookmarks` / `export.php`) — its list table opts out of table-tools via `data-itm-no-import-excel="1"` / `data-itm-no-export-excel="1"` / `data-itm-no-export-pdf="1"`. Actions header and body cells keep `itm-actions-cell` + `data-itm-actions-origin="1"`.
- Dual-pane `index.php` also accepts JSON `import_excel_rows` for compatibility, but the dual-pane table must not require `data-itm-db-import-endpoint`.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php` or `list_all.php`) — bulk import via `itm_handle_json_table_import($conn, 'bookmarks', $company_id)`; 📥 Import Excel on the flattened list uses `list_all.php` as `data-itm-db-import-endpoint`.
- **move_folder** (POST on `index.php`) — `folder_id`, `new_parent_id`; updates `bookmark_folders.parent_folder_id` when `itm_is_admin()` or folder owner.
- **move_bookmarks** (POST on `index.php`) — `action=move_bookmarks`, `ids[]`, `target_folder_id` (`0` = Root); updates `bookmarks.folder_id` for editable rows only.
- **import.php** — browser HTML bookmark file upload (`bkm_parse_html_bookmarks()`); **Folder** target select (`Root` or folder tree via `bkm_render_folder_options()`).
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
- Shared bookmarks (`shared = 1`) visible to all company users but editable only by `itm_is_admin()` or creator.

## 9. Audit Logging Requirements
- **Private data (no audit):** `bookmark_folders` and `bookmarks` are exempt from `audit_logs` and database audit triggers per `AGENTS.md` → **Private data — no audit trail**. Do not add `itm_log_audit()` for bookmark/folder mutations.

## 10. Common Pitfalls
- SQL ambiguity when joining `bookmark_folders` — alias `active`, `employee_id`. [Cursor-Valid]
- URLs missing scheme — prepend `http://` or `https://` when saving. [Cursor-Valid]
- `delete.php` expects `bulk_action=single_delete` for inline index deletes. [Cursor-Valid]
- Folder delete moves bookmarks to root — do not CASCADE-delete bookmark rows silently. [Cursor-Valid]
- Do not enforce unique folder names in PHP validation — the schema allows duplicates. [Cursor-Valid]
- Existing DBs that still have `uq_bookmark_folders_company_scope` must `DROP INDEX` that key (see comment under `bookmark_folders` in `database.sql`). [Cursor-Valid]

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
