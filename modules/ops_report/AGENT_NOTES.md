# AGENT_NOTES.md - Ops Report

## 1. Module Purpose
Daily hotel operations report (duty managers, figures & revenue, F&B covers, walk-round checks, guest experience, courtesy calls, butler service). One persisted report per `company_id` + `report_date`.

## 2. Key Tables
- **ops_report** — daily header and scalar metrics.
- **ops_report_fb_outlet** — F&B outlet cover counts.
- **ops_report_walk_round** — walk-round area checks (early/late shift).
- **ops_report_courtesy_call** — dynamic courtesy-call rows.
- **ops_report_guest_experience** — dynamic guest-experience rows.
- **ops_report_butler** — dynamic suites butler rows.
- **ops_report_night_shift** — dynamic night-shift guest rows (23h00 – 07h30).

## 3. Required Relationships
- All child tables → **ops_report** (`ops_report_id`, cascade delete).
- All tables → **companies** (`company_id`).

## 4. Business Rules (Critical for Agents)
- **Day / month / year selectors** on `index.php` choose `report_date`.
- **Auto-create:** opening a date calls `opr_ensure_report()` — inserts `ops_report` plus default F&B outlets and walk-round areas when missing.
- **Edit lock (D-2):** non-admins may edit **today and yesterday** only (`report_date > date('Y-m-d', strtotime('-2 days'))`). Older dates are read-only unless `itm_is_admin()`.
- **All cells editable** when the date is unlocked — no per-field role restrictions (unlike backup tape log).
- **Extra rows:** any user may add/delete rows in courtesy calls, guest experience, butler, night shift, F&B outlets, and walk-round when the date is editable.
- **Exports:** 📗 Excel (SheetJS) and 📄 PDF (browser print) with company header.

## 5. UI Behavior Requirements
- Custom single-day report layout (not standard CRUD list).
- Inline AJAX: `ajax_inline_edit`, `ajax_add_row`, `ajax_delete_row`.
- `data-itm-no-export-*` not used on main card — custom export buttons only.

## 6. API Actions (If Applicable)
- `POST index.php` with `ajax_inline_edit=1`, `ajax_add_row=1`, or `ajax_delete_row=1` + `csrf_token`.

## 7. File Structure
- **index.php** — main report UI and AJAX handlers.
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — wrappers routing to `index.php`.

## 8. Multi-Tenant Rules
- Strict `company_id` scoping on all queries.

## 9. Audit Logging Requirements
- `ops_report` INSERT/UPDATE/DELETE via database triggers.

## 10. Common Pitfalls
- Do not use bare `mkdir()` for uploads — N/A (no uploads).
- Whitelist field names in AJAX handlers (`opr_report_fields()`, `opr_child_table_map()`).
- Re-test D-2 lock after changing date math.

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
