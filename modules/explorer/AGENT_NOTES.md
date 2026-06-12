# AGENT_NOTES.md - Explorer

## 1. Module Purpose
Provides a web-based file management system with multi-tenant isolation. It supports private, department-level, and common company storage.

## 2. Key Tables
- **explorer** — tracks file metadata and ownership (the physical files are in the `files/` directory).

## 3. Required Relationships
- **explorer** → depends on **companies**.
- **explorer** → depends on **users** (for private files).
- **explorer** → depends on **departments** (for department files).

## 4. Business Rules (Critical for Agents)
- **Storage Scoping**:
    - **Common**: Visible to all users in the company.
    - **Department**: Visible only to users in that department.
    - **Private**: Visible only to the owner.
- **Physical Isolation**: Files are stored in `files/{company_id}/...`.
- **Path Safety**: Filenames and paths must be sanitized to prevent traversal.

## 5. UI Behavior Requirements
- **Breadcrumb Navigation**: Supports folder-based navigation.
- **File Actions**: Upload, Download, Delete, Rename, Favorite.

## 6. API Actions (If Applicable)
- **api.php** — handles async file operations (upload, delete, list).

## 7. File Structure
- **index.php** — main explorer UI.
- **api.php** — backend API for file operations.
- **file.php** — handles file delivery (downloads).
- **setup.php** — ensures storage directories exist.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.
- Use `storage_root = ROOT_PATH . 'files/' . $company_id` for physical file operations.

## 9. Audit Logging Requirements
- Managed via database triggers for metadata changes.

## 10. Common Pitfalls
- **Path Traversal**: Always validate `folder_path` and `file_name` against the storage root.
- **Mime Types**: Validate file types on upload to prevent malicious scripts.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM explorer WHERE company_id = ? AND folder_path = ?");
$stmt->bind_param("is", $companyId, $folderPath);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Integrated file storage for all system entities.
