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
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` and Metadata fields**: The standard metadata columns (`active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) are excluded from visible table UI columns and are rendered as `<input type="hidden">` in edit and create forms.

- **Color Swatch**: List and view should show the selected color.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_event_categories_audit_insert`, `trg_event_categories_audit_update`, `trg_event_categories_audit_delete` on `event_categories` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Deleting a category nulls `events.category_id` and `alerts.category_id` automatically (`ON DELETE SET NULL`) — no manual detach step required. [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]

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
