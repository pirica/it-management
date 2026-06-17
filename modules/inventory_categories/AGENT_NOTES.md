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
- `trg_inventory_categories_audit_insert|update|delete` in `database.sql` when `enable_audit_logs` is on.

## 10. Common Pitfalls
- Deleting categories without detaching `inventory_items.category_id` first — leaves items pointing at missing FK or blocks delete silently.
- Inverting bulk gate (`$perPage >= $totalRows`) hides bulk toolbar incorrectly.

## 11. Examples of Safe Code Patterns

### Detach children before parent delete
```php
$detachSql = 'UPDATE inventory_items SET category_id = NULL WHERE company_id = ? AND category_id IN (' . implode(',', $idList) . ')';
// run in same transaction as DELETE FROM inventory_categories ...
```

## 12. Module Owner Notes (Optional)
Parent lookup for **inventory_items** — coordinate delete handler changes with `modules/inventory_items/` if added.
