# AGENT_NOTES.md - Equipment RJ45

## 1. Module Purpose
Tracks standard RJ45 copper connections and port configurations for equipment.

## 2. Key Tables
- **equipment_rj45** — stores RJ45-specific data.

## 3. Required Relationships
- **equipment_rj45** → depends on **companies**.
- **equipment_rj45** → depends on **equipment**.

## 4. Business Rules (Critical for Agents)
- **Standard Patching**: Maps copper connections from devices to patch panels or switches.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search uses `$fieldColumns` in the search SQL block (list columns use `$uiColumns` / `$displayFieldColumns` alias); sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_equipment_rj45_audit_insert`, `trg_equipment_rj45_audit_update`, `trg_equipment_rj45_audit_delete` on `equipment_rj45` in `db/03_triggers.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Cursor-Valid]
- Referenced by **equipment** (`switch_rj45_id`). [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM equipment_rj45 WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO equipment_rj45 (company_id, name) VALUES (?, ?)");
$stmt->bind_param("is", $companyId, $name);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The most common connection type in the system.
