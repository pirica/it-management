# AGENT_NOTES.md - Rack Planner

## 1. Module Purpose
Visual rack elevation planner. Stores layout JSON per named rack plan and references devices from catalogs, equipment, and unlinked IDF positions.

## 2. Key Tables
- **rack_planner** — `name`, `rack_units`, `layout_json`, `notes` (primary persistence), `status_id` (foreign key to `rack_statuses` lookup table), along with tracking metadata columns (`employee_id`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`). All tracking columns (including `active`) The `active` column is kept as a hidden internal field with value `1` (or `0` when soft-deleted).
- **Reads** (not owned): **catalogs**, **equipment**, **idf_positions**, **racks**, **it_locations**, **employees**, **rack_statuses**.

## 3. Required Relationships
- **rack_planner** → **companies**.
- **rack_planner** → **employees** (via `employee_id`, `created_by`, `updated_by`, and `deleted_by`).
- **rack_planner** → **rack_statuses** (via `status_id`).
- Layout device codes: `catalog:<id>`, `equipment:<id>`, `idf_unlinked:<token>`.

## 4. Business Rules (Critical for Agents)
- **Price source sync (mandatory):** on save/autosave, price edits must persist to source tables:
  - `catalog:<id>` → `catalogs.price`
  - `equipment:<id>` → `equipment.purchase_cost`
  - `idf_unlinked:<token>` → `idf_positions.price` (token-style `equipment_id` `^[0-9]{4}-[0-9]{4}$`)
- Do not keep price changes only inside `layout_json`.
- **Tier D** bespoke module — navigation smoke in module browser QA; not standard flattened CRUD.

## 5. UI Behavior Requirements
- Vertical rack-unit grid; drag/drop placement.
- Custom handlers in `includes/handlers.php` — disable redundant default exports when custom layout applies.
- Auto-save AJAX (`ajax_update_layout`) returns HTTP 404 when `rack_planner` row is not tenant-scoped.
- List view supports bulk delete/clear when row count ≥ `records_per_page`.
- **Responsive:** rack visualizer uses `min(600px, 100%)` width with horizontal scroll; mobile padding in `includes/partials/render.php`.

## 6. API Actions (If Applicable)
- **ajax_update_layout** (POST on create/edit) — `id`, `rack_units`, `layout_json`; normalises layout, persists JSON, syncs prices to source tables; 404 when `affected_rows === 0`.
- **import_excel_rows** (JSON POST on index/list_all) — standard table import for plan metadata rows.
- **add_sample_data** (POST index) — seeds empty tenant from `database.sql` when table empty.

## 7. File Structure
- `index.php` — main planner UI; `$crud_action = $crud_action ?? 'index'` so wrappers (`create.php`, `edit.php`, etc.) keep their action.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — set `$crud_action` then `require index.php`.
- `includes/bootstrap.php`, `functions.php`, `handlers.php`, `partials/render.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; unique rack plan name per company (`rack_planner_name_company`). All active plans are filtered with `deleted_at IS NULL` to support soft deletion.

## 9. Audit Logging Requirements
- `trg_rack_planner_audit_insert|update|delete` in `database.sql` logging all fields.

## 10. Common Pitfalls
- There is no `rack_equipment` mapping table — layout lives in `layout_json`. [Cursor-Valid]
- Partial price sync breaks catalog/equipment/IDF reporting — always update source row. [Cursor-Valid]
- Be sure to explicitly request or insert columns (`employee_id`, `created_by`, `updated_by`, `deleted_by`, etc.) when executing SELECT/INSERT/UPDATE statements. [Cursor-Valid]
- Param type string for Create/Insert statement must be `'iisiisiii'` corresponding to the exact column types: `company_id` (i), `employee_id` (i), `name` (s), `rack_units` (i), `layout_json` (s), `notes` (s), `status_id` (i), `active` (i), `created_by` (i). If name is bound as an integer, it will fail to save strings correctly and fallback to 0. [Cursor-Valid]

## 11. View Action Enhancements
- The View screen displays a detailed info card of the rack plan above the visualizer.
- This card includes the resolved values for:
  - `name`: Direct string.
  - `status_id`: Resolved to `status_name` from the `rack_statuses` table, shown as a styled status badge.
  - `created_by`, `updated_by`, `deleted_by`: Resolved to employee full names (`first_name` + ' ' + `last_name`) from the `employees` table.
  - `created_at`, `updated_at`, `deleted_at`: Formatted using the exact layout `DD-MM-YYYY - HH:MM:SS` (using PHP `d-m-Y - H:i:s` syntax). If the raw value is NULL, it is displayed as blank.

## 12. Examples of Safe Code Patterns

### Tenant-scoped auto-save UPDATE with updated_by
```php
$stmt = mysqli_prepare($conn, 'UPDATE rack_planner SET layout_json = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL');
mysqli_stmt_bind_param($stmt, 'siii', $layoutJson, $updated_by, $id, $company_id);
```

## 13. Module Owner Notes (Optional)
See `modules/rack_planner/includes/AGENT_NOTES.md` for partial/handler details.
