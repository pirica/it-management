# AGENT_NOTES.md - Switch Port Types

## 1. Module Purpose
Lookup table for types of switch ports (e.g., "RJ45", "SFP", "Door", "Access Point").

## 2. Key Tables
- **switch_port_types** — stores port type names.

## 3. Required Relationships
- **switch_port_types** → depends on **companies**.
- **switch_port_types** → referenced by **switch_ports** and **floor_designer_points**.

## 4. Business Rules (Critical for Agents)
- **Name-Based Fallback**: The UI often uses name-based matching (e.g., 'RJ45') as a fallback if IDs don't match across companies.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: POST handlers validate via `cr_require_valid_csrf_token()`; forms include hidden `csrf_token` from `itm_get_csrf_token()`.
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
- Database triggers `trg_switch_port_types_audit_insert`, `trg_switch_port_types_audit_update`, `trg_switch_port_types_audit_delete` on `switch_port_types` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first.
- Referenced by **switch_ports**, **idf_ports**, **floor_designer_points**.
- Respect tenant unique constraints; duplicates fail at the database layer.
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM switch_port_types WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO switch_port_types (company_id, name) VALUES (?, ?)");
$stmt->bind_param("is", $companyId, $name);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Core categorization for network ports.
