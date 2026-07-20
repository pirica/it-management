# AGENT_NOTES.md - Inventory Categories

## 1. Module Purpose
Lookup table for inventory item categories (e.g., "Cables", "Peripherals", "Supplies").

## 2. Key Tables
- **inventory_categories** — stores category names and status.

## 3. Required Relationships
- **inventory_categories** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name:** Category name must be unique per company.
- **Delete safety:** `inventory_items` may reference `category_id` without CASCADE — run `UPDATE inventory_items SET category_id = NULL WHERE company_id = ? AND category_id IN (...)` **before** bulk delete / clear_table / single delete on categories.

## 5. UI Behavior Requirements
- Standard flattened CRUD with bulk delete when row count ≥ `records_per_page`.
- `data-itm-db-import-endpoint="index.php"` on index table.
- `$displayFieldColumns = $uiColumns` before search block (mandatory alias).

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import via `itm_handle_json_table_import($conn, 'inventory_categories', $company_id)`.

## 7. File Structure
- `index.php` — list, search, import, detach-then-delete helpers (`inventory_categories_detach_items_then_delete()`).
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — standard CRUD entry points.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- `trg_inventory_categories_audit_insert|update|delete` in `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Deleting categories without detaching `inventory_items.category_id` first — leaves items pointing at missing FK or blocks delete silently. [Cursor-Fixed]
- Inverting bulk gate (`$perPage >= $totalRows`) hides bulk toolbar incorrectly. [Cursor-Fixed]

## 11. Examples of Safe Code Patterns

### Detach children before parent delete (prepared placeholders)
```php
$placeholders = implode(',', array_fill(0, count($idList), '?'));
$types = 'i' . str_repeat('i', count($idList));
$params = array_merge([$companyId], $idList);
$detachSql = 'UPDATE inventory_items SET category_id = NULL WHERE company_id = ? AND category_id IN (' . $placeholders . ')';
$stmt = $conn->prepare($detachSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
// then DELETE FROM inventory_categories ... in the same transaction
```

## 12. Module Owner Notes (Optional)
Parent lookup for **inventory_items** — coordinate delete handler changes with `modules/inventory_items/` if added.
