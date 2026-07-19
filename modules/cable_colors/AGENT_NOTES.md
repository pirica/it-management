# AGENT_NOTES.md - Cable Colors

## 1. Module Purpose
Lookup table for cable colors (e.g., "Gray", "Green", "Red"). Used to visually identify cable types in the floor designer and network management modules.

## 2. Key Tables
- **cable_colors** — stores color names, hex codes, and comments.

## 3. Required Relationships
- **cable_colors** → depends on **companies**.
- **cable_colors** → referenced by network point and connection modules.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: `color_name` must be unique within a `company_id`.
- **Hex Code**: `hex_color` should be a valid CSS hex color string (e.g., "#808080").

## 5. UI Behavior Requirements
- **Standard CRUD** with scaffold soft-delete (`itm_crud_build_soft_delete_sql()` on delete; list uses `itm_crud_append_not_deleted_predicate()`).
- **Bulk toolbar:** `bulk-delete-form`, shared `bulk-delete-selection.js`, **Select to Delete**, **Cancel** (`data-itm-bulk-cancel="1"`, `type="button"`), and **Clear Table** when row count ≥ `records_per_page`.
- **Color Preview**: The list and view pages should ideally show a small swatch of the hex color.
- Create/edit forms use `$uiColumns` (business fields only) with `itm_crud_render_form_hidden_audit_inputs()` for audit stamps; list/view keep `$visibleFieldColumns`.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- Deleting a color that is actively used in the floor designer might result in missing color indicators or fallback to a default color. [Cursor-Valid]
- Do not hard `DELETE` — use `itm_crud_build_soft_delete_sql()` and filter live rows with `itm_crud_append_not_deleted_predicate()`. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM cable_colors WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO cable_colors (company_id, color_name, hex_color) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $companyId, $name, $hex);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for the visual identity of network infrastructure.
