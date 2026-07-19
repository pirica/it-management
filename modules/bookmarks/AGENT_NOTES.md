# AGENT_NOTES.md - Bookmarks

## 1. Module Purpose
Hierarchical bookmark manager with private and shared links, folder tree, drag-and-drop, and import/export.

## 2. Key Tables
- **bookmarks** — URL records (`title` TEXT, `url` TEXT, `url_hash` SHA-256 of plaintext URL, `shared`, `employee_id`, `folder_id`). **Private** (`shared = 0`): `title` and `url` encrypted at rest via `itm_encrypt()` + session `vault_key`; **shared** (`shared = 1`): `title` and `url` stored plaintext for team access. **`UNIQUE (company_id, employee_id, url_hash)`** — one exact URL per employee (any folder).
- **bookmark_folders** — folder tree (emoji icons in sidebar). **Private** (`shared = 0`): `name` encrypted at rest + `name_hash` (SHA-256 of plaintext name) for import lookup; **shared** folders keep plaintext `name`. **Duplicate folder names are allowed** (same employee / company / parent). Identity is `PRIMARY KEY (id)` only — there is **no** UNIQUE on `name`.

## 3. Required Relationships
- **bookmarks** → **companies**, **employees**, **bookmark_folders**.

## 4. Business Rules (Critical for Agents)
- **Privacy:** filter by `employee_id` for private bookmarks and `company_id` for shared ones.
- **Visibility:** row visible when `(employee_id = logged employee OR shared = 1)` and `company_id` matches.
- **Permissions:** shared bookmarks read-only for regular users; admins (`itm_is_admin()`) and creators retain full CRUD.
- **Dual-pane UI:** left folder tree (📁/📂), main list view.
- **Drag-and-drop:** folders reordered/reparented via DnD interactions.
- **Folder / bookmark naming:**
  - Do **not** re-add `UNIQUE (company_id, …, name)` on `bookmark_folders`.
  - Bookmark **URLs** are unique per `(company_id, employee_id)` — similar paths/schemes are allowed; exact URL string duplicates are not.
  - Folder names may duplicate; distinguish rows by `id`.
  - Tenant unique-key audit (`php scripts/check_database_sql_company_name_uniques.php` / `includes/database_sql_unique_audit.php`) **skips** `bookmark_folders` and `bookmarks`.
- **Import/export:** browser HTML bookmark files, CSV, and XLSX.
- **Deletion (private data — hard delete):** `delete.php` and `delete_folder.php` use `DELETE` (not soft-delete). Optional folder delete moves child bookmarks to root or deletes folder contents when confirmed. Employee delete cascades via FK.

## 5. UI Behavior Requirements
- Dual-pane layout: left folder tree (📁/📂 emoji), right bookmark list.
- View modes: `all`, `private`, `shared` via `?view=`; folder filter via `?folder_id=`.
- **Search:** `index.php` and `list_all.php` decrypt private title/url/folder names in PHP, then filter/sort/paginate in memory (`bkm_query_bookmarks_for_list()`). SQL `LIKE` is not used on encrypted private fields.
- Dual-pane `index.php` list heading uses `data-itm-new-button-managed="server"` with centered `sanitize($moduleListHeading)` from `itm_sidebar_label_for_module()`. POST mutations on `index.php` call `itm_require_post_csrf()` (JSON `import_excel_rows` keeps token validation on the JSON body).
- Dual-pane `index.php` bulk toolbar (`Select`, `Select to Delete`, Cancel, Clear Table, **Move to** folder select) and row `ids[]` checkboxes appear only when `$showBulkActions = ($totalRows >= $perPage)`; uses shared `bulk-delete-selection.js` with `#select-all-rows` and `data-itm-bulk-select="1"` on **Select**. **Move to** form (`#bulk-move-form`) stays hidden until at least one row checkbox is checked.
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
- **import.php** — browser HTML bookmark file upload (`bkm_parse_html_bookmark_entries()` creates missing `<H3>` folders and imports links into each folder; `bkm_parse_html_bookmarks()` remains a flat compatibility wrapper). **Folder** target select (`Root` or folder tree) is the parent for HTML folder paths / the destination for CSV rows. **Vault required** before upload (`bkm_vault_bootstrap.php`); imports create **private** bookmarks with URLs encrypted at rest via `bkm_insert_bookmark_row()`. Imports accept `http://`, `https://`, and `ftp://` URLs only; skips exact URL duplicates per employee. Post-import tables: imported rows light green (`Successfully imported → Folder`); duplicate URL skips light red (`Duplicate URL → Folder`); invalid URL skips light yellow (`Invalid URL → Folder`); vault locked skips light grey (`Vault locked → Folder`).
- **export.php** / **export.js** — CSV, XLSX (CSV payload), TXT, HTML, and PDF (print view) via `export.php`. `bkm_export_row()` decrypts **own private** titles and URLs when `$_SESSION['vault_key']` is set; **shared** rows always export as plaintext. Private titles/URLs export blank when the vault is locked. `export.js` routes every format to `export.php` (no DOM scrape).

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
- Folder delete without contents moves bookmarks to root; **delete contents** hard-deletes bookmarks in the folder tree. [Cursor-Valid]
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
