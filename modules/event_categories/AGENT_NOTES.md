# AGENT_NOTES.md - Event Categories

## 1. Module Purpose
Lookup table for categorizing events and alerts (e.g., "Maintenance", "Holiday", "Meeting"). Includes color coding for calendar display.

## 2. Key Tables
- **event_categories** — stores category names and hex colors.

## 3. Required Relationships
- **event_categories** → depends on **companies**.
- **event_categories** → referenced by **events** and **alerts**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique within a company.
- **Hex Color**: Should be a valid CSS hex color for visualization on the calendar.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: POST handlers use `itm_require_post_csrf()`; forms include hidden `csrf_token`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

- **Color Swatch**: List and view should show the selected color.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_event_categories_audit_insert`, `trg_event_categories_audit_update`, `trg_event_categories_audit_delete` on `event_categories` in `database.sql` write to `audit_logs` when `enable_audit_logs` is enabled.

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first.
- Referenced by **events**, **alerts** (`category_id`).
- Respect tenant unique constraints; duplicates fail at the database layer.
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM event_categories WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO event_categories (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Directly impacts the visual layout of the Calendar module.
