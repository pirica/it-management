# AGENT_NOTES.md - Explorer

## 1. Module Purpose
Secure multi-tenant file manager. Physical files under `files/{company_id}/` with metadata in **explorer** (when used).

## 2. Key Tables
- **explorer** — file/folder metadata (optional tracking). Supports standard audit columns: `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`.
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
- **Localisation:** UK English (en-GB) UI labels (Favourites, Trash, etc.).
- **Upload hardening (`deny_http`):** never bare `mkdir()` under `files/` — use `itm_ensure_files_storage_directory()` / `explorer_ensure_dir()`. Every segment gets force-written `deny_http` `.htaccess` + `index.html`. Serve UI via `itm_files_serve_url()` → `file.php`. See **`docs/file_upload_modules.md`**.

## 5. UI Behavior Requirements
- Breadcrumb navigation; upload, download, delete, rename, favourite.
- Quick Access sidebar opens scoped Private/Department folders, not blocked roots.
- **Hidden system files:** `index.html` and `.htaccess` are always omitted from Explorer listings (`explorer_is_hidden_system_entry()` in `api.php` `list` / `listRecycle`).
- **Preview routing:** `open` returns `preview: image|pdf|text|unsupported`. Images and PDFs load via `file.php` (not text `file_get_contents`). `.htaccess` `deny_http` blocks direct `/files/` URLs only; `file.php` reads from disk with ACL checks.
- **Employee profile photos (`file.php`):** paths matching `Private/*/profile/` are readable by any authenticated user in the active company (employee list/view/birthdays thumbnails). Other `Private/` paths remain owner-scoped.
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
| `upload` | Multipart upload (dotfiles blocked) |
| `createYear` / `createMonths` / `createDays` / `createYearMonthDay` | Date-folder scaffolding helpers |
| `listRecycle` | Trash listing with same ACL as live storage |
| `restore` | Restore from Trash (normalise `item` path before ACL) |
| `emptyRecycle` | Permanently empty permitted Trash items |

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
- Path traversal if `folder_path` / `file_name` not validated against storage root. [Valid]-[2026-07-15]
- Allowing upload in blocked roots (Home, Private root, Departments root). [Valid]-[2026-07-15]
- Navigating to `Private` or `Departments` in JS without `resolveScopedFolderPath()` — list API returns empty after root blocking. [Valid]-[2026-07-15]
- `restore` POST `item` must be normalized before ACL and filesystem paths (backslashes bypass segment checks). [Valid]-[2026-07-15]
- Linking `../../files/…` in HTML after `deny_http` — images/downloads break; use `itm_files_serve_url()`. [Valid]-[2026-07-15]
- Hand-editing `.htaccess` under `files/` — removed on next `itm_ensure_files_storage_directory()` call. [Valid]-[2026-07-15]

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
Regression: `php scripts/test_explorer_paths.php`; ZIP root leak: `php scripts/verify_explorer_zip_leak.php`; path `./` bypass: `php scripts/repro_explorer_path_bypass_v4.php`; Zip Slip: `php scripts/repro_explorer_zip_slip_v2.php`. `.htaccess` RCE PoC: `verify_explorer_rce_htaccess.php`, `verify_explorer_rce_marker.php`. PHPUnit: `ExplorerTest::testGetFullPathSecurity`, `ExplorerPathBypassTest`, `ExplorerZipSlipTest`.
