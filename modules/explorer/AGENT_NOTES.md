# AGENT_NOTES.md - Explorer

## 1. Module Purpose
Secure multi-tenant file manager. Physical files under `files/{company_id}/` with metadata in **explorer** (when used).

## 2. Key Tables
- **explorer** — file/folder metadata (optional tracking).
- **Physical storage:** `files/{company_id}/Common/`, `Departments/{dept_id}/`, `Private/{username}_{user_id}/`.

## 3. Required Relationships
- **explorer** → **companies**, **users**, **departments** (department segment access).

## 4. Business Rules (Critical for Agents)
- **Storage segments:**
  - `Common/` — all company users.
  - `Departments/{dept_id}/` — department members only.
  - `Private/{username}_{user_id}/` — owner only.
- **Blocked API access** to `Private` and `Departments` **roots** (`get_full_path` returns null). The UI resolves sidebar and double-click navigation to scoped paths (`Private/{username}_{user_id}`, `Departments/{dept_id}`).
- **Blocked creation/upload** at Home root, `Private` root, and `Departments` root.
- **Protected folders:** top-level `Common`, `Departments`, `Private`, `Trash`, and items directly under `Private`/`Departments` roots cannot be renamed, moved, deleted, copied, or zipped.
- **Trash ACL:** `listRecycle`, `restore`, and `emptyRecycle` apply the same `get_full_path` rules as live storage (users only see/restore/empty their permitted items).
- **Path validation:** normalize backslashes to `/`, trim slashes, block `..`; use segment-boundary checks for `Private/{owner}` and `Departments/{dept_id}`.
- **Localisation:** UK English (en-GB) UI labels (Favourites, Trash, etc.).

## 5. UI Behavior Requirements
- Breadcrumb navigation; upload, download, delete, rename, favourite.
- Quick Access sidebar opens scoped Private/Department folders, not blocked roots.
- `api.php` for async operations; `file.php` for delivery.

## 7. File Structure
- `index.php`, `api.php`, `file.php`, `setup.php`.

## 8. Multi-Tenant Rules
- `storage_root = ROOT_PATH . 'files/' . $company_id`; never cross company boundaries.

## 10. Common Pitfalls
- Path traversal if `folder_path` / `file_name` not validated against storage root.
- Allowing upload in blocked roots (Home, Private root, Departments root).
- Navigating to `Private` or `Departments` in JS without `resolveScopedFolderPath()` — list API returns empty after root blocking.
- `restore` POST `item` must be normalized before ACL and filesystem paths (backslashes bypass segment checks).

## 12. Module Owner Notes (Optional)
Integrated file storage for company, department, and private scopes. Regression: `php scripts/test_explorer_paths.php`; ZIP leak: `php scripts/verify_explorer_zip_leak.php`.
