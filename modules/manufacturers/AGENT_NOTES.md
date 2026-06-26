# AGENT_NOTES.md - Manufacturers

## 1. Module Purpose
Lookup table for equipment and inventory manufacturers (e.g., "Dell", "Cisco", "HP"). 

## 2. Key Tables
- **manufacturers** — stores manufacturer names and status.

## 3. Required Relationships
- **manufacturers** → depends on **companies**.
- **manufacturers** → referenced by **equipment**, **catalogs**, **inventory_items**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Manufacturer name must be unique per company.

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
- `index.php` — main flattened CRUD (list + POST handlers).
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — entry wrappers or full screens per action.
- Materialized siblings (for example `modules/note_labels/`, `modules/modules_registry/`) mirror this layout locally.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- Database triggers `trg_manufacturers_audit_insert`, `trg_manufacturers_audit_update`, `trg_manufacturers_audit_delete` on `manufacturers` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls


## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM manufacturers WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO manufacturers (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Central lookup for asset branding; provides the code blueprint for the standard CRUD materialization helpers in `includes/ui_config.php`.
