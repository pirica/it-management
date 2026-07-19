# AGENT_NOTES.md - System Access

## 1. Module Purpose
Company-scoped lookup of IT systems and applications (e.g. ERP, Email, VPN) used when recording **employee_system_access** assignments.

## 2. Key Tables
- **system_access** — system/application name and metadata per company.

## 3. Required Relationships
- **system_access** → depends on **companies**.
- **system_access** → referenced by **employee_system_access**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** single delete uses `itm_can_delete_record()` — blocked when child **employee_system_access** rows reference the system.
- **Clear table:** `delete.php` bulk `clear_table` deletes all tenant rows without per-row FK check — use with caution in production.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view renders all six scaffold audit columns via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`).
- **List header:** `data-itm-new-button-managed="server"` with `position:relative`, centered `sanitize($moduleListHeading)` from `itm_sidebar_label_for_module()`, `min-height:40px`, and Settings `new_button_position` create slots.
- Standard flattened CRUD: search, sort, pagination, export/import.
- Bulk toolbar when `$totalRows >= $perPage`; `bulk-delete-selection.js` + `data-itm-bulk-cancel="1"` Cancel in index HTML.
- `includes/employee_system_access.php` loaded on index/create/delete for shared helpers (`esa_ensure_table()`).

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import via standard flattened handler.

## 7. File Structure
- `index.php` — list + bulk form.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — standard CRUD.
- `delete.php` — single delete with `itm_can_delete_record()` guard.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- `trg_system_access_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls
- Deleting systems still referenced by **employee_system_access** — single delete shows `crud_error`; bulk delete may bypass per-row check. [Cursor-Valid]
- Omitting `company_id` on DELETE allows cross-tenant removal. [Cursor-Valid]
- Renaming systems without updating employee assignment labels — FK id stays stable but UI labels in child module depend on this name. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Guarded single delete
```php
if (!itm_can_delete_record($conn, 'system_access', 'id', $id, $company_id, $usageError)) {
    $_SESSION['crud_error'] = $usageError;
} else {
    $stmt = $conn->prepare('DELETE FROM system_access WHERE id = ? AND company_id = ?');
}
```

## 12. Module Owner Notes (Optional)
Defines the catalogue of applications managed for access control. Coordinate schema/label changes with `modules/employee_system_access/`.
