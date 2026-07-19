# AGENT_NOTES.md - Ops Report

## 1. Module Purpose
Daily hotel operations report (duty managers, figures & revenue, F&B covers, walk-round checks, guest experience, courtesy calls, butler service). One persisted report per `company_id` + `report_date`.

## 2. Key Tables
- **ops_report** — daily header, scalar metrics, and `report_ui_json` (section titles, field labels, table headers, toolbar copy).
- **ops_report_fb_outlet** — F&B outlet cover counts.
- **ops_report_walk_round** — walk-round area checks (early/late shift).
- **ops_report_courtesy_call** — dynamic courtesy-call rows.
- **ops_report_guest_experience** — dynamic guest-experience rows.
- **ops_report_butler** — dynamic suites butler rows.
- **ops_report_night_shift** — dynamic night-shift guest rows (23h00 – 07h30).
- **ops_report_hotel_figure** — dynamic extra Hotel Figures & Revenue fields (`field_label`, `field_value`).

## 3. Required Relationships
- All child tables → **ops_report** (`ops_report_id`, cascade delete).
- All tables → **companies** (`company_id`).

## 4. Business Rules (Critical for Agents)
- **Day / month / year selectors** on `index.php` choose `report_date`.
- **Auto-create:** opening a date calls `opr_ensure_report()` — inserts `ops_report` plus default F&B outlets and walk-round areas when missing.
- **Edit lock (D-2):** non-admins may edit **today and yesterday** only (`report_date > date('Y-m-d', strtotime('-2 days'))`). Older dates are read-only unless `itm_is_admin()`.
- **All cells editable** when the date is unlocked — no per-field role restrictions (unlike backup tape log).
- **UI copy in DB:** section headings, metric labels, table column headers, add-row buttons, and `titles.*` (browser tab, export sheet/file prefix) persist in `ops_report.report_ui_json` (blur-save via `scope=report_ui` + `json_path`). Defaults seed on first open via `opr_default_ui_json()`. Table **Actions** header uses `controls.actions` only.
- **Fixed display (not in `report_ui_json`):** report date suffix (`opr_format_date()`, `d.m.y`) from day/month/year selectors; company line is always `Company:` + `companies.company` for the active tenant; date/export toolbar (Day, Month, Year, Go, Export Excel, Export PDF) in `.opr-controls`.
- **Extra rows:** any user may add/delete rows in courtesy calls, guest experience, butler, night shift, F&B outlets, walk-round, and custom hotel figure fields when the date is editable.
- **Exports:** 📗 Excel (SheetJS) and 📄 PDF (browser print) with company header.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.
- Custom single-day report layout (not standard CRUD list).
- Inline AJAX: `ajax_inline_edit`, `ajax_add_row`, `ajax_delete_row`. Editable dates render **visible** inputs/textareas (blur saves); locked dates show read-only `.display-val` only. UI labels use `.edit-input-ui` and `scope=report_ui`.
- `data-itm-no-export-excel="1"`, `data-itm-no-export-pdf="1"`, and `data-itm-no-import-excel="1"` on `#opr-report-root` and every section table — custom 📗/📄 controls live only in the top `.opr-controls` bar (`exportOPR()`).
- **Responsive:** header grid stacks below 900px; wide section tables scroll horizontally below 768px.

## 6. API Actions (If Applicable)
- `POST index.php` with `ajax_inline_edit=1`, `ajax_add_row=1`, or `ajax_delete_row=1` + `csrf_token`. UI labels: `scope=report_ui`, `json_path` (dot notation), `field=report_ui_json`.

## 7. File Structure
- **index.php** — main report UI and AJAX handlers.
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — wrappers routing to `index.php`.

## 8. Multi-Tenant Rules
- Strict `company_id` scoping on all queries.

## 9. Audit Logging Requirements
- Database triggers on **all** `ops_report*` tables: `ops_report`, `ops_report_fb_outlet`, `ops_report_walk_round`, `ops_report_courtesy_call`, `ops_report_guest_experience`, `ops_report_butler`, `ops_report_night_shift`, `ops_report_hotel_figure` (`trg_{table}_audit_insert|update|delete` in `database.sql`).
- Child-row triggers include `ops_report_id` in `old_values` / `new_values` JSON for parent linkage.

## 10. Common Pitfalls
- Do not use bare `mkdir()` for uploads — N/A (no uploads). [Cursor-Valid]
- Whitelist field names in AJAX handlers (`opr_report_fields()`, `opr_child_table_map()`, `opr_is_allowed_ui_path()`). [Cursor-Valid]
- Re-test D-2 lock after changing date math. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Editable date check
```php
function opr_is_editable_date($dateStr, $isAdmin) {
    if ($isAdmin) return true;
    $cutoff = date('Y-m-d', strtotime('-2 days'));
    return date('Y-m-d', strtotime($dateStr)) > $cutoff;
}
```

### Ensure daily report
```php
$report = opr_ensure_report($conn, $company_id, $selected_date);
```

## 12. Module Owner Notes (Optional)
Layout mirrors the hotel Daily Operations Report PDF (duty managers, revenue block, F&B grid, walk-round, guest experience, courtesy calls, butler, night shift guest list).
