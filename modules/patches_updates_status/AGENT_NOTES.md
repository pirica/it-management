# AGENT_NOTES.md - Patches & Updates Status

## 1. Module Purpose
Lookup table for the status of a patch installation (e.g., "Pending", "Installed", "Failed").

## 2. Key Tables
- **patches_updates_status** — stores status names.

## 3. Required Relationships
- **patches_updates_status** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Status name must be unique per company.

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
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- Database triggers `trg_patches_updates_status_audit_insert`, `trg_patches_updates_status_audit_update`, `trg_patches_updates_status_audit_delete` on `patches_updates_status` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Deleting a status nulls `patches_updates.status_id` automatically (`ON DELETE SET NULL`) — no manual detach step required.
- Respect tenant unique constraints; duplicates fail at the database layer.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM patches_updates_status WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO patches_updates_status (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Tracks progress of software updates.
