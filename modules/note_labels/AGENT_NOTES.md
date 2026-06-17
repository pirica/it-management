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

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — full local CRUD files (materialized from `modules/manufacturers/`).

## 8. Multi-Tenant Rules
- Filter by `company_id` and `user_id` on all reads/writes.

## 10. Common Pitfalls
- Do not expose another user's labels.
- Cross-module manufacturers requires are forbidden; only `modules/manufacturers/` may own that path.

## 12. Module Owner Notes (Optional)
Behaviour matches the manufacturers CRUD template but runs against `note_labels` with local PHP copies.
