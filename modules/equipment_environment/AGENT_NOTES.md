# AGENT_NOTES.md - Equipment Environment

## 1. Module Purpose
Lookup table for equipment environments (e.g., "Production", "Staging", "Development").

## 2. Key Tables
- **equipment_environment** — stores environment names and status.

## 3. Required Relationships
- **equipment_environment** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Environment name must be unique per company.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: POST handlers use `itm_require_post_csrf()`; forms include hidden `csrf_token`.
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
- Database triggers `trg_equipment_environment_audit_insert`, `trg_equipment_environment_audit_update`, `trg_equipment_environment_audit_delete` on `equipment_environment` in `database.sql` write to `audit_logs` when `enable_audit_logs` is enabled.

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first.
- Referenced by **equipment**, **idf_ports**, **switch_ports** (`management_id`).
- Respect tenant unique constraints; duplicates fail at the database layer.
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM equipment_environment WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO equipment_environment (company_id, name) VALUES (?, ?)");
$stmt->bind_param("is", $companyId, $name);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Helps in categorizing assets for better lifecycle management.
