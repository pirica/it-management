# AGENT_NOTES.md - Ops Report Courtesy Call

## 1. Module Purpose
Dynamic courtesy-call guest rows on a daily Ops Report. Tracks guest name, room, times, notes, actions taken, case closure, and monitor status per `ops_report_id`.

## 2. Key Tables
- **ops_report_courtesy_call** — `guest_name`, `room_number`, `time_reported`, `checkout_date`, `notes`, `action_taken`, `case_closed`, `monitor`, `sort_order`.

## 3. Required Relationships
- **ops_report_courtesy_call** → depends on **ops_report** (`ops_report_id`, ON DELETE CASCADE).
- **ops_report_courtesy_call** → depends on **companies** (`company_id`).
- Primary add/edit/delete UX: **modules/ops_report/index.php** (`ajax_add_row`, `ajax_inline_edit`, `ajax_delete_row`).

## 4. Business Rules (Critical for Agents)
- Rows are user-added per report date; no fixed seed set (unlike F&B outlets / walk-round).
- **Edit lock (D-2) — parent only:** enforced on **modules/ops_report/index.php** AJAX for non-admins (today/yesterday). Standalone CRUD here is not date-locked.
- Any authenticated user may add/delete courtesy-call rows when the parent report date is editable.
- Field names in parent AJAX must stay whitelisted via `opr_child_table_map()`.

## 5. UI Behavior Requirements
- Standard flattened CRUD for direct row management (secondary to parent report UI).
- Search, sort, pagination, bulk actions, export/import per global module standards.
- Text fields (`notes`, `action_taken`) may be long — preserve in exports.

## 6. API Actions (If Applicable)
- **import_excel_rows** — `index.php` JSON import.
- Parent **ops_report** exposes `ajax_add_row` / `ajax_inline_edit` / `ajax_delete_row` for inline grid editing.

## 7. File Structure
- **index.php**, **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php**.

## 8. Multi-Tenant Rules
- All queries must filter by session `company_id`.
- `ops_report_id` must reference an existing **ops_report** row; DB does not enforce matching `company_id` on the parent (validate in application code if hardening).

## 9. Audit Logging Requirements
- Triggers: `trg_ops_report_courtesy_call_audit_insert|update|delete`.

## 10. Common Pitfalls
- Do not bypass parent D-2 lock when adding AJAX paths on **ops_report**. [Cursor-Valid]
- `checkout_date` and `time_reported` are stored as varchar in schema — match parent display/parsing conventions. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare(
    'SELECT * FROM ops_report_courtesy_call WHERE company_id = ? AND ops_report_id = ? ORDER BY sort_order'
);
$stmt->bind_param('ii', $companyId, $opsReportId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare(
    'INSERT INTO ops_report_courtesy_call (company_id, ops_report_id, guest_name, room_number) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('iiss', $companyId, $opsReportId, $guestName, $roomNumber);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Parent: **modules/ops_report/AGENT_NOTES.md**. Regression: `php scripts/verify_ops_report.php`.
