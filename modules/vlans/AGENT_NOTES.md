# AGENT_NOTES.md - VLANs

## 1. Module Purpose
Manages Virtual LAN (VLAN) definitions, including names, IDs, and descriptions.

## 2. Key Tables
- **vlans** — stores VLAN data.

## 3. Required Relationships
- **vlans** → depends on **companies**.
- **vlans** → referenced by **ip_subnets** and **switch_ports**.

## 4. Business Rules (Critical for Agents)
- **Unique ID**: VLAN ID must be unique within a company.
- **Hard delete**: `delete.php` / bulk/clear use hard `DELETE` (detach `switch_ports`, `idf_ports`, `ip_subnets` `vlan_id` first). Reviewed in `scripts/data/fields_missing_reviewed.json` — no scaffold soft-delete or `deleted_at IS NULL` list filter.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view renders all six scaffold audit columns via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`).
- **List columns:** `$displayFieldColumns` and `$uiColumns` filter with `itm_crud_is_list_hidden_audit_field()` so audit meta never appears on index/list_all tables.
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage` (loads `bulk-delete-selection.js` + Cancel on index), Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- Create/edit forms use `$uiColumns` (business fields only) with `itm_crud_render_form_hidden_audit_inputs()` for audit stamps across all duplicated entry files.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- Database triggers `trg_vlans_audit_insert`, `trg_vlans_audit_update`, `trg_vlans_audit_delete` on `vlans` in `db/03_triggers.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Cursor-Valid]
- Referenced by **switch_ports**, **idf_ports**, **ip_subnets** (`vlan_id`). [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM vlans WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO vlans (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Foundational for network segmentation.
