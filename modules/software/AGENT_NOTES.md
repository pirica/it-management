# AGENT_NOTES.md - Software

## 1. Module Purpose
Tenant software catalog used for EOL / Extended / ESU tracking. Dates on this table are inherited by equipment through `equipment_software` — they are not stored on the junction.

## 2. Key Tables
- **software** — catalog rows (`name` unique per `company_id`, optional `build`, `eol_date`, `extended_date`, `esu_date`).
- **equipment_software** — many-to-many links (owned by equipment create/edit sync; this module is the catalog).
- **software_license_links** — many-to-many links to `license_management` (sync from software create/edit via `license_management_ids[]`; view lists linked licenses).

## 3. Required Relationships
- **software** → **companies** (`company_id`, CASCADE).
- **equipment_software** → **software** (`software_id`, RESTRICT) and **equipment** (`equipment_id`, CASCADE).
- **software_license_links** → **software** (`software_id`, RESTRICT) and **license_management** (`license_management_id`, RESTRICT).

## 4. Business Rules (Critical for Agents)
- **Unique name** per company. Soft-deleted rows still occupy the unique key.
- **No per-install date overrides** on `equipment_software`. Change dates on the catalog row.
- Seed dates in `db/02_data.sql` are illustrative, not official vendor lifecycle dates. Chrome Enterprise uses `DATE_ADD(CURDATE(), …)` so dashboard 30-day windows stay in-range after import.
- Flattened CRUD materialized from `modules/manufacturers/` via `itm_materialize_standard_crud_module_files('software')`. Humanize map: `eol_date` → EOL, `extended_date` → Extended, `esu_date` → ESU, `build` → Build.

## 5. UI Behavior Requirements
- Standard flattened CRUD: search (`$displayFieldColumns` alias), sort, pagination, bulk delete/clear, Excel import/export, Add sample data.
- Hide `company_id`. Keep `software` in `$hideCompanyIdTables` on `index.php`, `edit.php`, `view.php`, and `list_all.php` (materialize from manufacturers does not add the new table name). Actions cells: `itm-actions-cell` + `data-itm-actions-origin="1"`. Import endpoint `index.php`.
- Create/edit: multi-select **Linked licenses** (`license_management_ids[]`) syncs `software_license_links` via `itm_software_license_sync_for_software()`.
- View: read-only **Linked licenses** table with links to `modules/license_management/view.php`.
- Calendar emits **one event per catalog product**, not per linked asset (`itm_software_eol_append_calendar_events()`).

## 6. API Actions (If Applicable)
- **import_excel_rows** on `index.php`.

## 7. File Structure
- `index.php`, wrappers `create.php`, `edit.php`, `view.php`, `list_all.php`, `delete.php`.
- Canonical doc: `docs/SOFTWARE_EOL.md`. Helper: `includes/itm_software_eol.php`.

## 8. Multi-Tenant Rules
- All queries scoped by `company_id`.

## 9. Audit Logging Requirements
- Triggers `trg_software_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls
- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields. Inventory: `docs/list_soft-delete.txt`. [Cursor-Valid]
- Do not delete a software row still linked by `equipment_software` (RESTRICT). Unlink from equipment first. [Cursor-Valid]
- Do not delete a software row still linked by `software_license_links` (RESTRICT). Unlink licenses first. [Cursor-Valid]
- Dashboard/email use **eol_date only** (hardware or inherited). Extended/ESU are calendar/expiring only. [Cursor-Valid]
- Omitting `software` from `$hideCompanyIdTables` shows a **Company** list/view column (often the company email as the FK label). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = mysqli_prepare($conn, 'SELECT id, name, eol_date FROM software WHERE company_id = ? AND deleted_at IS NULL ORDER BY name');
mysqli_stmt_bind_param($stmt, 'i', $companyId);
mysqli_stmt_execute($stmt);
```

## 12. Module Owner Notes (Optional)
Regression: `php scripts/verify_software_eol.php`. License links: `php scripts/verify_software_license_links.php`. Browser: [verify_software_eol.php?run=1](http://localhost/it-management/scripts/verify_software_eol.php?run=1) · [verify_software_license_links.php?run=1](http://localhost/it-management/scripts/verify_software_license_links.php?run=1) (Admin session). Module: [modules/software/index.php](http://localhost/it-management/modules/software/index.php) (open in a new browser tab).
