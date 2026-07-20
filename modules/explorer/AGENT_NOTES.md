# AGENT_NOTES.md - Explorer

## 1. Module Purpose
Secure multi-tenant file manager. Physical files under `files/{company_id}/` with metadata in **explorer** (when used).

## 2. Key Tables
- **explorer** — file/folder metadata (optional tracking). Supports standard audit columns: `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`. Uniques are declared on `CREATE TABLE`: `uq_explorer_company_path_name` (`company_id`, `folder_path`(191), `file_name`(191)) and `uq_explorer_user_path_name` (adds `employee_id`) — no live `ALTER TABLE` in `database.sql`.
- **Physical storage:** `files/{company_id}/Common/`, `Departments/{dept_code}/`, `Private/{username}_{user_id}/`, `Trash/`.

## 3. Required Relationships
- **explorer** → **companies**, **employees**, **departments** (department segment access).

## 4. Business Rules (Critical for Agents)
- **Storage segments:**
  - `Common/` — all company users.
  - `Departments/{dept_code}/` — department members only (uses department code instead of ID).
  - `Private/{username}_{user_id}/` — owner only.
  - `Trash/` — soft-deleted items (relative paths mirror live layout).
- **Blocked API access** to `Private` and `Departments` **roots** (`get_full_path` returns null). The UI resolves sidebar and double-click navigation to scoped paths (`Private/{username}_{user_id}`, `Departments/{dept_code}`) via `resolveScopedFolderPath()` in `index.php`.
- **Blocked creation/upload** at Home root, `Private` root, `Departments` root, and `Trash` root.
- **Protected folders:** top-level `Common`, `Departments`, `Private`, `Trash`, and items directly under `Private`/`Departments` roots cannot be renamed, moved, deleted, copied, or zipped. User primary private folder cannot be renamed, moved, or deleted.
- **Trash Protection:** `Trash` root cannot be deleted if it contains any items.
- **Trash ACL:** `listRecycle`, `restore`, and `emptyRecycle` apply the same `get_full_path` rules as live storage (users only see/restore/empty their permitted items).
- **Path validation:** normalize backslashes to `/`, trim slashes, collapse `.` segments via `explorer_normalize_relative_path()`, block `..`; segment-boundary checks for `Private/{owner}` and `Departments/{dept_code}` (blocks `./Private` bypass).
- **Zip extraction:** `unzip` uses `explorer_extract_zip_safely()` — rejects archive entries whose resolved path escapes the target folder.
- **Trash at Home:** `list` omits the physical `Trash/` folder from scandir, then appends a `Trash` folder icon at Home **only** when `explorer_user_has_visible_trash_items()` finds ACL-visible deleted items for the signed-in user. Double-click or sidebar opens `listRecycle`. When trash is empty for that user, Home hides the icon (sidebar **🗑️ Trash** link remains).
- **Trash listing:** `explorer_filter_trash_list_to_leaf_items()` drops ancestor folders created when a nested file is soft-deleted (e.g. only `Private/Admin_1/24.png` is listed, not `Private` or `Private/Admin_1`). Empty deleted folders still appear.
- **Upload hardening (`deny_http`):** never bare `mkdir()` under `files/` — use `itm_ensure_files_storage_directory()` / `explorer_ensure_dir()`. Every segment gets force-written `deny_http` `.htaccess` + `index.html`. Serve UI via `itm_files_serve_url()` → `file.php`. See **`scripts/AGENT_NOTES.md`**.
- **Upload validation:** `upload` accepts only a whitelist of extensions, checks detected MIME (`finfo` / `getimagesize`) against that extension, rejects dotfiles, and enforces `EXPLORER_MAX_FILE_SIZE` (20MB). MIME mismatch or oversize files are rejected with `error` in the JSON response.
- **`downloadZip` (`api.php`):** allows **only** the exact path `Private/{username}_{employee_id}` for the signed-in employee. Blocks `Private` root, other users' folders, own private subfolders as zip targets, `Common`, `Departments`, `Trash`, and Home. The ZIP still includes all files recursively inside the allowed private folder.

## 5. UI Behavior Requirements
- **UI configuration audit:** gate-excluded bespoke file manager — no flattened CRUD table or scaffold create/edit/delete/list_all in `index.php`. Intentional `[n/a][n/a]` lines are `[reviewed]` in `scripts/data/ui_configuration_reviewed.json`.
- **View audit meta:** Detail view renders all six scaffold audit columns via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`).
- **ZIP backup UI:** **Download as ZIP** appears only when browsing the exact `Private/{username}_{employee_id}` folder (not Home, not the `Private` root, not Common/Departments, and not private subfolders). The request always targets that root private path. **Compress** is hidden for `Common`, `Departments`, `Private`, `Trash`, and virtual trash items at Home.
- Quick Access sidebar opens scoped Private/Department folders, not blocked roots.
- **Trash at Home:** virtual `Trash` folder (`type: trash`, 🗑️ icon) on Home only when the user has recoverable items; double-click or sidebar **🗑️ Trash** opens recycle view (`openRecycle()`). Trash entries are not draggable.
- **Recycle view:** breadcrumbs show **Home / Trash**; grid labels show the leaf basename with the full trash-relative path in `title` (e.g. label `24.png`, title `Private/Admin_1/24.png`). Context menu offers **Restore** only.
- **Hidden system files:** `index.html` and `.htaccess` are always omitted from Explorer listings (`explorer_is_hidden_system_entry()` in `api.php` `list` / `listRecycle`).
- **Preview routing:** `open` returns `preview: image|pdf|text|unsupported`. Images and PDFs load via `file.php` (not text `file_get_contents`). `.htaccess` `deny_http` blocks direct `/files/` URLs only; `file.php` reads from disk with ACL checks.
- **Employee profile photos (`file.php`):** paths matching `Private/*/profile/` are readable by any authenticated user. Storage root is resolved from the **photo owner’s home `company_id`** (parsed from `Private/{username}_{employee_id}/…`), not the tenant-switcher session company — otherwise multi-company admins get 404 after upload. Other `Private/` paths remain owner-scoped under the active session company.
- **Responsive:** topbar/search/tabs adapt below 768px; file grid uses smaller tiles on mobile (`index.php` inline CSS).
- **Employee sidebar (index.php):** `🌐 Employees` links to `modules/employees/`; `🎉 Birthdays` links to `modules/birthdays/`; **Profile Storage** opens `Private/{username}_{user_id}/profile` via `openEmployeeProfileFolder()` (employee profile photos from the employees module).
- `api.php` for async operations; `file.php` for authorised file delivery (required after `deny_http`).

## 6. API Actions (If Applicable)
All actions are POST to `api.php` with `action` parameter (JSON responses unless noted):

| Action | Purpose |
|--------|---------|
| `list` | Directory listing; syncs discovered items to **explorer** table |
| `open` | Preview routing: `image` / `pdf` / `text` / `unsupported`; binary via `file.php` URL |
| `createFolder` | Create subfolder (blocked at Home, `Private`, `Departments` roots) |
| `delete` | Soft-delete to `Trash/` mirror path |
| `rename` | Rename file/folder (protected roots blocked) |
| `copy` | Copy item within ACL-permitted path |
| `move` | Move item (protected items blocked) |
| `zip` | Create ZIP (root ZIP blocked — regression script) |
| `unzip` | Extract archive in place |
| `upload` | Multipart upload (dotfiles blocked; extension + MIME + size validated) |
| `createYear` / `createMonths` / `createDays` / `createYearMonthDay` | Date-folder scaffolding helpers |
| `listRecycle` | Trash listing with same ACL as live storage; leaf filter via `explorer_filter_trash_list_to_leaf_items()` |
| `restore` | Restore from Trash (normalise `item` path before ACL) |
| `emptyRecycle` | Permanently empty permitted Trash items |

`GET api.php?downloadZip=1&path=` — ZIP download **only** when `path` is exactly `Private/{username}_{employee_id}` for the session employee.

`file.php?path=` — authorised download/preview after `get_full_path()` ACL check.

## 7. File Structure
- `index.php` — browser UI, sidebar, `resolveScopedFolderPath()`.
- `api.php` — JSON file operations (`list`, `upload`, Trash, etc.).
- `file.php` — authorised serve/preview after ACL.
- `setup.php` — initial tenant folder scaffolding.

## 8. Multi-Tenant Rules
- `storage_root = ROOT_PATH . 'files/' . $company_id`; never cross company boundaries.

## 9. Audit Logging Requirements
- Filesystem operations are not written to `audit_logs` by default; optional **explorer** table sync on `list`/`sync_db()` for metadata only.
- Metadata table `explorer` tracks standard audit columns (`created_by`, `updated_by`, `created_at`, `updated_at`, etc.). Triggers `trg_explorer_audit_insert`, `trg_explorer_audit_update`, and `trg_explorer_audit_delete` record changes to `audit_logs` including standard audit fields in JSON payloads.
- Trash restore/delete should preserve ACL — unauthorised paths must not leak filenames in JSON.

## 10. Common Pitfalls
- Path traversal if `folder_path` / `file_name` not validated against storage root. [Cursor-Valid]
- Allowing upload in blocked roots (Home, Private root, Departments root). [Cursor-Valid]
- Trusting only the client filename extension for Explorer uploads (no MIME/size check). [Cursor-Fixed]
- Navigating to `Private` or `Departments` in JS without `resolveScopedFolderPath()` — list API returns empty after root blocking. [Cursor-Valid]
- `restore` POST `item` must be normalized before ACL and filesystem paths (backslashes bypass segment checks). [Cursor-Valid]
- Linking `../../files/…` in HTML after `deny_http` — images/downloads break; use `itm_files_serve_url()`. [Cursor-Valid]
- Hand-editing `.htaccess` under `files/` — removed on next `itm_ensure_files_storage_directory()` call. [Cursor-Valid]
- In-process `include` of `api.php` from browser scripts sets `Content-Type: application/json` unless `ITM_EXPLORER_API_IN_PROCESS` is defined (see `explorer_human_test.php`). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Path ACL check before filesystem access
```php
$dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
if (!$dir) {
    echo json_encode(['items' => []]);
    exit;
}
```

### Ensure directory with managed hardening
```php
explorer_ensure_dir($dir . '/' . $name); // wraps itm_ensure_files_storage_directory()
```

## 12. Module Owner Notes (Optional)
Regression: `php scripts/test_explorer_paths.php`; ZIP contract: `php scripts/verify_explorer_zip_leak.php` (blocked roots + scoped Private backup); path `./` bypass: `php scripts/repro_explorer_path_bypass_v4.php`; Zip Slip: `php scripts/repro_explorer_zip_slip_v2.php`. `.htaccess` RCE PoC: `verify_explorer_rce_htaccess.php`, `verify_explorer_rce_marker.php`. PHPUnit: `ExplorerTest::testGetFullPathSecurity`, `ExplorerTest::testTrashListFiltersAncestorFolders`, `ExplorerPathBypassTest`, `ExplorerZipSlipTest`.
