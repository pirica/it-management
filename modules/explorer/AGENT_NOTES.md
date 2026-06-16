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
- **Blocked creation/upload** at Home root, `Private` root, and `Departments` root.
- **Protected folders:** top-level `Common`, `Departments`, `Private`, and the user's primary private folder cannot be renamed, moved, or deleted.
- **Path validation:** use segment-boundary checks (exact `Private/{owner}` or prefix with trailing slash) — prevent traversal.
- **Localisation:** UK English (en-GB) UI labels (Favourites, Trash, etc.).

## 5. UI Behavior Requirements
- Breadcrumb navigation; upload, download, delete, rename, favourite.
- `api.php` for async operations; `file.php` for delivery.

## 7. File Structure
- `index.php`, `api.php`, `file.php`, `setup.php`.

## 8. Multi-Tenant Rules
- `storage_root = ROOT_PATH . 'files/' . $company_id`; never cross company boundaries.

## 10. Common Pitfalls
- Path traversal if `folder_path` / `file_name` not validated against storage root.
- Allowing upload in blocked roots (Home, Private root, Departments root).

## 12. Module Owner Notes (Optional)
Integrated file storage for company, department, and private scopes.
