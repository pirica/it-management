# AGENT_NOTES.md - Backup Tape Log File

## 1. Module Purpose
Manages a monthly grid view to track server backup tapes. It allows users to record when tapes are inserted and returned to the safe.

## 2. Key Tables
- **backup_tape_log** — tracks status and timestamps for tapes.

## 3. Required Relationships
- **backup_tape_log** → depends on **companies**.
- **backup_tape_log** → depends on **equipment** (via `server_id`, restricted to `equipment_type` = 'Server').

## 4. Business Rules (Critical for Agents)
- **Monthly grid:** one row per day of selected month/year/server; `log_date` and `tape_to_be_used` (day name) auto-derived.
- **Sunday highlighting:** Sunday rows highlighted in yellow on the grid.
- **Immutability:** records not from **today** locked for edit/delete.
- **Restricted fields:** `tape_used_for_restore` and `ism_review` editable only by `itm_is_admin()` or IT department staff.
- **Role-Based Access:** `itm_is_admin()` and IT staff full access; regular users may have restricted fields/dates.
- **Date Logic:** `btl_format_datetime` treats `1970-01-01` as "—" for display.
- **Exports:** XLSX and PDF must include custom header (Year, Month, Company, Server, Unit No) and grid layout.
- **Add sample data:** `index.php` POST `add_sample_data` calls `itm_seed_insert_backup_tape_log_today_row()` (after `itm_seed_ensure_server_equipment()`), inserting a direct **today** row — not the stale template date from `02_data_sample.sql`. Button shows when no servers exist or the selected server has no row for today.

## 5. UI Behavior Requirements
- **Display title:** **Backup Tape Log File** (sidebar, registry `module_name`, `$crud_title`, and create/edit/view headings); index grid h1 keeps year prefix (`{year} Backup Tape Log File`).
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.
- **Grid View**: A custom interactive grid instead of a standard list.
- **No flattened list contract:** monthly grid omits standard Search/Sort/Pagination wiring on `index.php`. `fields_missing.php` reports those bespoke gate checks as `[SKIP][fail][reviewed]` via `scripts/data/fields_missing_reviewed.json`; `check_ui_configuration_coverage.php` prints matching gate-excluded lines as `[n/a][fail|n/a][reviewed]` via `scripts/data/ui_configuration_reviewed.json` (manifest: `scripts/ui_configuration_reviewed.php`).
- **No Excel import:** table uses `data-itm-no-import-excel="1"` (and export opt-outs); `check_index_table_compliance.php` does not require `data-itm-db-import-endpoint`.
- **Time Punch**: A "⌛" icon is used to auto-fill the current time into timestamp fields.
- **AJAX Updates**: Supports inline editing via POST requests.
- **Responsive:** monthly grid table scrolls horizontally below 768px (card uses `overflow:auto`).
- Actions column uses `itm-actions-cell` + `data-itm-actions-origin="1"`.

## 6. API Actions (If Applicable)
- **ajax_inline_edit** — Handles async updates to status and timestamps.

## 7. File Structure
- **index.php** — main monthly grid and AJAX handler.
- **edit.php**, **view.php**, **delete.php**, **list_all.php** — standard CRUD support.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.
- Filters equipment by `company_id` and 'Server' type.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Server Selection**: Ensure `server_id` is valid and belongs to the correct company. [Cursor-Valid]
- **Date Overlap**: Ensure only one record exists per server, company, and date. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (Monthly)
```php
$stmt = $conn->prepare("SELECT * FROM backup_tape_log WHERE server_id = ? AND log_date BETWEEN ? AND ? AND company_id = ?");
$stmt->bind_param("issi", $serverId, $startDate, $endDate, $companyId);
$stmt->execute();
```

### Safe AJAX Update
```php
$stmt = $conn->prepare("UPDATE backup_tape_log SET $field = ? WHERE id = ? AND company_id = ?");
$stmt->bind_param("sii", $value, $id, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The "Time returned to safe" field is critical for ISM compliance.
