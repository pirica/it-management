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

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.
