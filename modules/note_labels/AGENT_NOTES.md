# AGENT_NOTES.md - Note Labels

## 1. Module Purpose
Per-user label/tag lookup for the Notes module. Stores distinct label strings a user can assign when organising notes.

## 2. Key Tables
- **note_labels** — label name per `user_id` and `company_id`.

## 3. Required Relationships
- **note_labels** → depends on **companies**, **users**.
- **note_labels** → referenced by **notes** (tag filtering and import mapping).

## 4. Business Rules (Critical for Agents)
- Labels are scoped to both `company_id` and `user_id` (private to the creating user).
- CRUD PHP is a **local copy** of the manufacturers flattened template (`$crud_table = 'note_labels'` in each entry file). Do **not** reintroduce `require __DIR__ . '/../manufacturers/…'` delegates.
- After manufacturers template changes, refresh with `itm_materialize_manufacturers_crud_module_files('note_labels', true)` from CLI (loads `config/config.php` once).

## 5. UI Behavior Requirements
- Standard flattened CRUD via manufacturers-style dynamic schema.
- List/search/sort/pagination/export/import per module standards.
- **Known gap:** list queries currently filter by `company_id` only — they do **not** filter `user_id = logged-in user` despite per-user scoping in section 8. Do not document per-user list filtering until code enforces it.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — same manufacturers-template JSON import handler as other flattened CRUD modules (`import_excel_rows` in `index.php`).

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — full local CRUD files (materialized from `modules/manufacturers/`).

## 8. Multi-Tenant Rules
- Filter by `company_id` and `user_id` on all reads/writes.

## 9. Audit Logging Requirements
- `trg_note_labels_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls
- Do not expose another user's labels when `user_id` filtering is added — today list queries are company-scoped only (known gap; see section 5).
- Cross-module manufacturers requires are forbidden; only `modules/manufacturers/` may own that path.

## 11. Examples of Safe Code Patterns

### Safe per-user label query
```php
$stmt = $conn->prepare('SELECT id, label FROM note_labels WHERE company_id = ? AND user_id = ? ORDER BY label ASC');
$stmt->bind_param('ii', $companyId, $userId);
```

## 12. Module Owner Notes (Optional)
Behaviour matches the manufacturers CRUD template but runs against `note_labels` with local PHP copies.
