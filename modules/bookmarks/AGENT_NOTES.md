# AGENT_NOTES.md - Bookmarks

## 1. Module Purpose
Manages user and company bookmarks (URLs). Supports folders, sharing, and custom positioning.

## 2. Key Tables
- **bookmarks** — main storage for bookmark records.

## 3. Required Relationships
- **bookmarks** → depends on **companies**.
- **bookmarks** → depends on **users**.
- **bookmarks** → depends on **bookmark_folders** (via `folder_id`).

## 4. Business Rules (Critical for Agents)
- **Visibility**: A bookmark is visible if (`user_id` matches OR `shared = 1`) AND `company_id` matches.
- **Deletion**: Single deletions must include `bulk_action = 'single_delete'` for compatibility with shared logic.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Import/Export**: Supports importing and exporting bookmarks (often via JSON or HTML).

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- **index.php** — list view and bulk action handler.
- **create.php**, **edit.php**, **delete.php** — standard CRUD.
- **import.php** / **export.php** — data mobility tools.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **SQL Ambiguity**: Join queries with `bookmark_folders` must use explicit aliases for `active`, `user_id`, etc.
- **URL Validation**: Ensure URLs are properly sanitized and prepended with `http://` or `https://` if missing.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT b.* FROM bookmarks b WHERE b.company_id = ? AND (b.user_id = ? OR b.shared = 1)");
$stmt->bind_param("ii", $companyId, $userId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO bookmarks (company_id, user_id, folder_id, title, url, shared) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiissi", $companyId, $userId, $folderId, $title, $url, $shared);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Core productivity feature for users.
