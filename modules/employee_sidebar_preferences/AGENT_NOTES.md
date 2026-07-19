# AGENT_NOTES.md - Employee Sidebar Preferences

## 1. Module Purpose
Stores per-user sidebar section/item order and visibility (`employee_sidebar_preferences` rows). This module folder provides a **bespoke read-only audit list** for administrators — not the live drag-and-drop sidebar editor (that lives in [`includes/ui_config.php`](../../includes/ui_config.php)).

## 2. Key Tables
- **employee_sidebar_preferences** — one row per sidebar section/item per `company_id` + `employee_id` (`entry_type`, `entry_id`, `section_id`, `display_order`, `is_visible`).

## 3. Required Relationships
- **employee_sidebar_preferences** → **companies** (`company_id`, CASCADE).
- **employee_sidebar_preferences** → **employees** (`employee_id`, CASCADE).

## 4. Business Rules (Critical for Agents)
- **Immediate effect:** live sidebar reads these rows on every page load via `itm_sidebar_structure()` / `ui_config.php`.
- **Unique key:** `uq_employee_sidebar_pref_entry` on (`company_id`, `employee_id`, `entry_type`, `entry_id`).
- **Read-only UI:** list and view screens do not expose create, edit, delete, bulk, or import flows.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.
- **Bespoke read-only list:** search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), Export Excel/PDF via `table-tools.js`.
- **No bulk toolbar:** no `bulk-delete-form`, Select to Delete, Cancel, or Clear Table.
- **No Import Excel:** list `<table>` uses `data-itm-no-import-excel="1"` (not `data-itm-db-import-endpoint`).
- **Hide `company_id`** from list and view.
- **Actions column:** View (`🔎`) only — `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **View footer:** `🔙` back to list only (no Edit).
- **`active` field:** list/view use `badge-success` / `badge-danger` (no emoji).
- **Search reset:** emoji-only `🔙` on the search row when a query is active.

## 6. API Actions (If Applicable)
- None — no `import_excel_rows` or delete POST handlers in this module.

## 7. File Structure
- `index.php` — canonical list (search/sort/pagination/export).
- `view.php` — read-only detail.
- `list_all.php` — flattened list variant.
- No `create.php`, `edit.php`, or `delete.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- Database triggers `trg_employee_sidebar_preferences_audit_insert`, `trg_employee_sidebar_preferences_audit_update`, `trg_employee_sidebar_preferences_audit_delete` on `employee_sidebar_preferences` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`). Inventory: `docs/list_soft-delete.txt`. Bespoke UI inventory: `docs/list_bespoke_UI.txt`.
- Soft-deleted rows still occupy unique keys — recreating the same entry may collide until purged.
- Per-user sidebar order/visibility — unique per `company_id` + `employee_id` + entry.
- Scope every SELECT by `company_id`; never expose `company_id` in the UI.
- `fields_missing.php` and `check_ui_configuration_coverage.php` treat this slug as gate-excluded / bespoke skip — do not add standard bulk/import contracts back without updating inventory lists. Gate-excluded `[n/a][n/a]` lines for missing create/edit/delete are marked `[reviewed]` in `scripts/data/ui_configuration_reviewed.json`.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM employee_sidebar_preferences WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT (runtime / seed — not exposed in module UI)
```php
$stmt = $conn->prepare(
    "INSERT INTO employee_sidebar_preferences (company_id, employee_id, entry_type, entry_id, section_id, display_order, is_visible, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("iisssiii", $companyId, $employeeId, $entryType, $entryId, $sectionId, $displayOrder, $isVisible, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Allows users to personalize navigation experience; this CRUD folder is for auditing seeded/persisted preference rows only.
