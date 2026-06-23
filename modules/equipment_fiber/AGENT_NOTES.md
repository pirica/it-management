# AGENT_NOTES.md - Equipment Fiber

## 1. Module Purpose
Manages fiber optic connections and specifications for equipment.

## 2. Key Tables
- **equipment_fiber** — stores fiber-specific data for assets.

## 3. Required Relationships
- **equipment_fiber** → depends on **companies**.
- **equipment_fiber** → depends on **equipment**.

## 4. Business Rules (Critical for Agents)
- **Scoped to Equipment**: Each record should relate to a specific piece of equipment that supports fiber.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
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
- Database triggers `trg_equipment_fiber_audit_insert`, `trg_equipment_fiber_audit_update`, `trg_equipment_fiber_audit_delete` on `equipment_fiber` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first.
- Referenced by **equipment**, **switch_ports**, **idf_ports**, **idf_links**.
- Respect tenant unique constraints; duplicates fail at the database layer.
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM equipment_fiber WHERE company_id = ? AND equipment_id = ?");
$stmt->bind_param("ii", $companyId, $equipmentId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO equipment_fiber (company_id, name) VALUES (?, ?)");
$stmt->bind_param("is", $companyId, $name);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used for high-speed network infrastructure tracking.
