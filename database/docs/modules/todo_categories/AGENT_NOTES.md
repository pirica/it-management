# AGENT_NOTES.md - Todo Categories

## 1. Module Purpose
Lookup table for todo list categories (personal/company-scoped names used by the Todo module).

## 2. Key Tables
- **todo_categories** — category name, `cat_from_employee_id`, `company_id`.

## 3. Required Relationships
- **todo_categories** → depends on **companies**, **users** (`cat_from_employee_id`).
- **todo_categories** → referenced by **todo** (category FK / import mapping).

## 4. Business Rules (Critical for Agents)
- Category names should be unique per owner context within a company where schema requires it.
- Audit triggers log INSERT/UPDATE/DELETE to `audit_logs`.

## 5. UI Behavior Requirements
- Standard flattened CRUD (`index.php` inline procedural CRUD).
- Search, sort, pagination, bulk delete, export/import per AGENTS.md standards.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import; Todo index resolves category names against this table.

## 7. File Structure
- `index.php` — full CRUD implementation.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — wrappers.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; `cat_from_employee_id` ties categories to creating user when set.

## 9. Audit Logging Requirements
- `trg_todo_categories_audit_insert|update|delete` in `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Deleting categories still referenced by todo rows may block deletes or orphan tasks — check FK usage in `todo` before clear/delete changes. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Todo index category load (legacy NULL company fallback)
```php
$sql = 'SELECT id, name FROM todo_categories WHERE company_id = ? OR company_id IS NULL';
```

## 12. Module Owner Notes (Optional)
Todo index may load categories with `company_id = ? OR company_id IS NULL` for legacy rows — preserve that fallback when editing queries.
