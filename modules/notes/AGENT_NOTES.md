# AGENT_NOTES.md - Notes

## 1. Module Purpose
Google Keep–style personal and shared notes for the active company. Supports pinning, importance, image attachments (`images_json`), colour, labels, and sharing via `shared_with_json`.

## 2. Key Tables
- **notes** — main note records (`title`, `content`, `is_pinned`, `is_important`, `images_json`, `shared_with_json`, `color`, `employee_id`).
- **note_labels** — per-user label/tag names used when filtering and importing.

## 3. Required Relationships
- **notes** → depends on **companies** (`company_id`), **employees** (`employee_id`, share targets).
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
- **Responsive:** sidebar stacks above note list below 768px (`index.php` inline CSS).

## 6. API Actions (If Applicable)
- **AJAX on index** — pin, archive, share, label, image upload mutations; use `itm_notes_json_mutation_response()` (404 when `affected_rows === 0`).
- **import_excel_rows** (JSON POST on `index.php` / `list_all.php`) — resolves tags via **note_labels** and share targets via usernames.
- **download_all_images** — ZIP of note attachments via `itm_notes_resolve_image_path()` (never raw JSON paths).

## 7. File Structure
- `index.php` — main UI, filters, import API, CRUD routing.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — standard entry wrappers.

## 8. Multi-Tenant Rules
- All queries filter by `company_id` and visibility (`employee_id` / `shared_with_json`).
- Notes cannot be moved between companies.

## 9. Audit Logging Requirements
- Database triggers in `database.sql` always write to `audit_logs` on DML (not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not list another user's private notes — always apply `itm_notes_visibility_sql()`.
- View/edit GET load must use `itm_notes_fetch_visible_by_id()` — do not SELECT by `id + company_id` alone.
- Do not store share targets as plain text; use `shared_with_json`.
- Label import must resolve names against `note_labels` for the current user.
- **`images_json` attachments:** store leaf filenames only. ZIP download (`download_all_images`) resolves paths via `itm_notes_resolve_image_path()` in `includes/notes_visibility.php` — never concatenate raw JSON values into filesystem paths.
- **AJAX mutations:** visibility-scoped handlers call `itm_notes_json_mutation_response()` — return HTTP 404 with `ok:false` when `affected_rows === 0` (no misleading success on blocked delete). Regression: `php scripts/verify_notes_ajax_contract.php`.

## 11. Examples of Safe Code Patterns

### Safe SELECT with visibility
```php
$sql = "SELECT * FROM notes WHERE company_id = ? AND (" . itm_notes_visibility_sql() . ")";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $companyId, $loggedUserId, $loggedUserId);
$stmt->execute();
```

### Safe single-record view/edit load
```php
$data = itm_notes_fetch_visible_by_id($conn, $editId, $companyId, $loggedUserId, true);
if (!$data) {
    header('Location: index.php');
    die();
}
```

## 12. Module Owner Notes (Optional)
Bespoke UI module — module browser QA may treat some standard CRUD steps as N/A; verify behaviour manually after changes.
