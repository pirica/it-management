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
- `index.php` delegates to the manufacturers CRUD scaffold via `require ../manufacturers/index.php` with `$crud_table = 'note_labels'`.

## 5. UI Behavior Requirements
- Standard flattened CRUD via shared manufacturers template.
- List/search/sort/pagination/export/import per module standards.

## 7. File Structure
- `index.php` — sets `$crud_table` / `$crud_title`, requires `modules/manufacturers/index.php`.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — wrappers.

## 8. Multi-Tenant Rules
- Filter by `company_id` and `user_id` on all reads/writes.

## 10. Common Pitfalls
- Do not expose another user's labels.
- When changing the delegate target (`manufacturers/index.php`), verify note_labels still renders correct columns.

## 12. Module Owner Notes (Optional)
Thin wrapper module — behaviour is mostly inherited from the manufacturers CRUD template.
