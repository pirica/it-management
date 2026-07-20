# AGENT_NOTES.md - Role Module Permissions

## 1. Module Purpose
Granular RBAC: view/create/edit/delete/import/export per module and role.

## 2. Key Tables
- **role_module_permissions** — permission flags per role and module name.

## 3. Required Relationships
- **role_module_permissions** → **companies**, **employee_roles**.

## 4. Business Rules (Critical for Agents)
- **Unique constraint:** one permission set per company + role + module name.
- **`ALL` module name:** global permissions for a role when module name is `ALL`.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage` (shared `bulk-delete-selection.js` + `data-itm-bulk-cancel="1"` Cancel in index HTML), Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_role_module_permissions_audit_insert`, `trg_role_module_permissions_audit_update`, `trg_role_module_permissions_audit_delete` on `role_module_permissions` in `db/03_triggers.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs (`itm_crud_render_form_hidden_audit_inputs()`); **`$uiColumns`** filters `itm_crud_is_form_hidden_audit_field` / `itm_crud_is_delete_form_hidden_field` on form loops; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Cursor-Valid]
- Controls CRUD access per role/module — deleting rows breaks permissions. [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM role_module_permissions WHERE role_id = ? AND module_name = ? AND company_id = ?");
$stmt->bind_param("isi", $roleId, $moduleName, $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO role_module_permissions (company_id, role_id, module_name, can_view) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iisi", $companyId, $roleId, $moduleName, $canView);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Heart of the RBAC system.
