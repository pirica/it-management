# AGENT_NOTES.md - Workstation Os Types

## 1. Module Purpose
Lookup table for workstation Os Types (e.g., specific to workstation configurations and asset management).

## 2. Key Tables
- **workstation_os_types** — stores os_types names and status.

## 3. Required Relationships
- **workstation_os_types** → depends on **companies**.
- Referenced by workstation asset management modules.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique within a `company_id`.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- Database triggers `trg_workstation_os_types_audit_insert`, `trg_workstation_os_types_audit_update`, `trg_workstation_os_types_audit_delete` on `workstation_os_types` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Cursor-Valid]
- Referenced by **equipment** (`workstation_os_type_id`). [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM workstation_os_types WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO workstation_os_types (company_id, name) VALUES (?, ?)");
$stmt->bind_param("is", $companyId, $name);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Categorizes workstation-specific hardware and software configurations.
