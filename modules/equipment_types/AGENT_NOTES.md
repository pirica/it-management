# AGENT_NOTES.md - Equipment Types

## 1. Module Purpose
Lookup table for categories of IT equipment (e.g., "Server", "Workstation", "Switch").

## 2. Key Tables
- **equipment_types** — stores type names, codes, and UI emojis.

## 3. Required Relationships
- **equipment_types** → depends on **companies**.
- **equipment_types** → referenced by **equipment**, **catalogs**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Type name must be unique within a company.
- **UI Emojis**: Includes an emoji field for visual categorization in the UI.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_equipment_types_audit_insert`, `trg_equipment_types_audit_update`, `trg_equipment_types_audit_delete` on `equipment_types` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Cursor-Valid]
- Referenced by **equipment** and **catalogs**. [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM equipment_types WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO equipment_types (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary way assets are categorized across the system.
