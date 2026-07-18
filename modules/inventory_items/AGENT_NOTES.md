# AGENT_NOTES.md - Inventory Items

## 1. Module Purpose
Manages inventory of consumables and spare parts. Tracks quantities on hand and minimum stock levels.

## 2. Key Tables
- **inventory_items** — main inventory data.

## 3. Required Relationships
- **inventory_items** → depends on **companies**.
- **inventory_items** → depends on **inventory_categories**.
- **inventory_items** → depends on **manufacturers**.
- **inventory_items** → depends on **suppliers**.
- **inventory_items** → links to **it_locations** (storage location).

## 4. Business Rules (Critical for Agents)
- **Stock Alert**: `quantity_on_hand` falling below `quantity_minimum` should trigger a low-stock alert.
- **Unit Price**: Tracks price per unit for valuation.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Stock Indicators**: Visual cues for low stock items.
- **Active checkbox (create/edit via `create.php`)**: `itm-checkbox-control` + `itm-check-indicator` — unchecked box shows ❌, checked shows ✅; JS listener must live in its own `<script>` block after `select-add-option.js` (do not nest inside the external script tag).

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Negative Stock**: Ensure `quantity_on_hand` does not fall below zero (unless backorders allowed). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM inventory_items WHERE company_id = ? AND quantity_on_hand < quantity_minimum");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Vital for operational continuity.
