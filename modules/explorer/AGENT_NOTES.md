# AGENT_NOTES.md - Explorer

## 1. Module Purpose
Secure multi-tenant file manager. Physical files under `files/{company_id}/` with metadata in **explorer** (when used).

## 2. Key Tables
- **explorer** — file/folder metadata (optional tracking).
- **Physical storage:** `files/{company_id}/Common/`, `Departments/{dept_id}/`, `Private/{username}_{user_id}/`, `Trash/`.

## 3. Required Relationships
- **explorer** → **companies**, **users**, **departments** (department segment access).

## 4. Business Rules (Critical for Agents)
- **Storage segments:**
  - `Common/` — all company users.
  - `Departments/{dept_id}/` — department members only.
  - `Private/{username}_{user_id}/` — owner only.
  - `Trash/` — soft-deleted items (relative paths mirror live layout).
- **Blocked API access** to `Private` and `Departments` **roots** (`get_full_path` returns null). The UI resolves sidebar and double-click navigation to scoped paths (`Private/{username}_{user_id}`, `Departments/{dept_id}`) via `resolveScopedFolderPath()` in `index.php`.
- **Blocked creation/upload** at Home root, `Private` root, and `Departments` root.
- **Protected folders:** top-level `Common`, `Departments`, `Private`, `Trash`, and items directly under `Private`/`Departments` roots cannot be renamed, moved, deleted, copied, or zipped. User primary private folder cannot be renamed, moved, or deleted.
- **Trash ACL:** `listRecycle`, `restore`, and `emptyRecycle` apply the same `get_full_path` rules as live storage (users only see/restore/empty their permitted items).
- **Path validation:** normalize backslashes to `/`, trim slashes, block `..`; segment-boundary checks for `Private/{owner}` and `Departments/{dept_id}`.
- **Localisation:** UK English (en-GB) UI labels (Favourites, Trash, etc.).

## 5. UI Behavior Requirements
- Breadcrumb navigation; upload, download, delete, rename, favourite.
- Quick Access sidebar opens scoped Private/Department folders, not blocked roots.
- **Hidden system files:** `index.html` and `.htaccess` are always omitted from Explorer listings (`explorer_is_hidden_system_entry()` in `api.php` `list` / `listRecycle`).
- **Preview routing:** `open` returns `preview: image|pdf|text|unsupported`. Images and PDFs load via `file.php` (not text `file_get_contents`). `.htaccess` `deny_http` blocks direct `/files/` URLs only; `file.php` reads from disk with ACL checks.
- **Employee profile photos (`file.php`):** paths matching `Private/*/profile/` are readable by any authenticated user in the active company (employee list/view/birthdays thumbnails). Other `Private/` paths remain owner-scoped.
- **Employee sidebar (index.php):** `🌐 Employees` links to `modules/employees/`; `🎉 Birthdays` links to `modules/birthdays/`; **Profile Storage** opens `Private/{username}_{user_id}/profile` via `openEmployeeProfileFolder()` (employee profile photos from the employees module).
- `api.php` for async operations; `file.php` for authorised file delivery (required after `deny_http`).

## 6. Upload hardening and `.htaccess` (`deny_http`)
- **Never bare `mkdir()`** for paths under `files/`. Use `itm_ensure_files_storage_directory()` (wrapper in `api.php` as `explorer_ensure_dir()`).
- **Every folder segment** under `files/{company_id}/…` (including `Trash/`, `Private/`, department and leaf folders) receives force-written:
  - **`.htaccess`** — `deny_http` policy (`ITM files hardening` marker). Canonical body: `itm_upload_directory_policy_body('deny_http')` in `includes/bootstrap_helpers.php`.
  - **`index.html`** — empty placeholder from `itm_upload_directory_empty_index_html()`.
- **Direct HTTP URLs** to `files/…` return 403 after hardening. UI must use `itm_files_serve_url()` → `modules/explorer/file.php?path=…`.
- **Dotfile uploads** (e.g. `.htaccess`) are blocked in `api.php`; malicious uploads are overwritten on the next ensure.
- **Do not commit** runtime tenant trees under `files/{company_id}/**` to git. Backfill: `php scripts/ensure_files_htaccess_chain.php` or `php scripts/empty_folders.php`.
- Full policy reference (all three ITM policies): **`docs/file_upload_modules.md`**.

## 7. File Structure
- `index.php`, `api.php`, `file.php`, `setup.php`.

## 8. Multi-Tenant Rules
- `storage_root = ROOT_PATH . 'files/' . $company_id`; never cross company boundaries.

## 10. Common Pitfalls
- Path traversal if `folder_path` / `file_name` not validated against storage root.
- Allowing upload in blocked roots (Home, Private root, Departments root).
- Navigating to `Private` or `Departments` in JS without `resolveScopedFolderPath()` — list API returns empty after root blocking.
- `restore` POST `item` must be normalized before ACL and filesystem paths (backslashes bypass segment checks).
- Linking `../../files/…` in HTML after `deny_http` — images/downloads break; use `itm_files_serve_url()`.
- Hand-editing `.htaccess` under `files/` — removed on next `itm_ensure_files_storage_directory()` call.

## 12. Module Owner Notes (Optional)
Regression: `php scripts/test_explorer_paths.php`; ZIP root leak: `php scripts/verify_explorer_zip_leak.php`. `.htaccess` RCE PoC: `verify_explorer_rce_htaccess.php`, `verify_explorer_rce_marker.php`. PHPUnit: `ExplorerTest::testGetFullPathSecurity`.
