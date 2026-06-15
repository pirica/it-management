# AGENT_NOTES.md - Notes

## 1. Module Purpose
Google Keep–style personal and shared notes for the active company. Supports pinning, importance, image attachments (`images_json`), colour, labels, and sharing via `shared_with_json`.

## 2. Key Tables
- **notes** — main note records (`title`, `content`, `is_pinned`, `is_important`, `images_json`, `shared_with_json`, `color`, `user_id`).
- **note_labels** — per-user label/tag names used when filtering and importing.

## 3. Required Relationships
- **notes** → depends on **companies** (`company_id`), **users** (`user_id`, share targets).
- **notes** → uses **note_labels** for tag metadata.
- Visibility helpers live in `includes/notes_visibility.php`.

## 4. Business Rules (Critical for Agents)
- A user sees only their own notes or notes shared with them (`itm_notes_visibility_sql()`).
- `shared_with_json` is a JSON array of user IDs.
- Import maps tag names and usernames to tenant-scoped IDs before insert.
- Standard CSRF on all POST handlers.

## 5. UI Behavior Requirements
- Custom card/grid UI (not standard flattened table CRUD on index).
- Sidebar filters: pinned, images, important, shared, labels.
- Supports `import_excel_rows` JSON on index/list_all.
- Hide `company_id` from views.

## 7. File Structure
- `index.php` — main UI, filters, import API, CRUD routing.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — standard entry wrappers.

## 8. Multi-Tenant Rules
- All queries filter by `company_id` and visibility (`user_id` / `shared_with_json`).
- Notes cannot be moved between companies.

## 9. Audit Logging Requirements
- Follow `enable_audit_logs`; table triggers in `database.sql` when enabled.

## 10. Common Pitfalls
- Do not list another user's private notes — always apply `itm_notes_visibility_sql()`.
- Do not store share targets as plain text; use `shared_with_json`.
- Label import must resolve names against `note_labels` for the current user.

## 11. Examples of Safe Code Patterns

### Safe SELECT with visibility
```php
$sql = "SELECT * FROM notes WHERE company_id = ? AND (" . itm_notes_visibility_sql() . ")";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $companyId, $loggedUserId, $loggedUserId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Bespoke UI module — module browser QA may treat some standard CRUD steps as N/A; verify behaviour manually after changes.
