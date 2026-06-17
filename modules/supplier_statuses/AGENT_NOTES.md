# AGENT_NOTES.md - Supplier Statuses

## 1. Module Purpose
Lookup table for the status of suppliers (e.g., "Active", "Preferred", "Blacklisted").

## 2. Key Tables
- **supplier_statuses** — stores status names and active flags.

## 3. Required Relationships
- **supplier_statuses** → depends on **companies**.
- **supplier_statuses** → referenced by **suppliers**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Status name must be unique per company.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: POST handlers use `itm_require_post_csrf()`; forms include hidden `csrf_token`.
- **Hide `company_id`** from list, view, and create/edit forms.
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
- Database triggers `trg_supplier_statuses_audit_insert`, `trg_supplier_statuses_audit_update`, `trg_supplier_statuses_audit_delete` on `supplier_statuses` in `database.sql` write to `audit_logs` when `enable_audit_logs` is enabled.

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first.
- Referenced by **suppliers** (`status_id`).
- Respect tenant unique constraints; duplicates fail at the database layer.
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM supplier_statuses WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO supplier_statuses (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used for vendor management and procurement.
