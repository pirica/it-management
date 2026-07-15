# AGENT_NOTES.md - Ops Report Night Shift

## 1. Module Purpose
Dynamic night-shift guest list rows (23h00 – 07h30) on a daily Ops Report. Stores guest name and notes per `ops_report_id`.

## 2. Key Tables
- **ops_report_night_shift** — `guest_name`, `notes`, `sort_order`.

## 3. Required Relationships
- **ops_report_night_shift** → depends on **ops_report** (`ops_report_id`, ON DELETE CASCADE).
- **ops_report_night_shift** → depends on **companies** (`company_id`).
- Primary UX: **modules/ops_report/index.php** night-shift section.

## 4. Business Rules (Critical for Agents)
- User-added rows per report date.
- **Edit lock (D-2) — parent only:** enforced on **modules/ops_report/index.php** AJAX for non-admins (today/yesterday). Standalone CRUD here is not date-locked.
- Section title and column labels come from `ops_report.report_ui_json` (parent module), not this CRUD folder.

## 5. UI Behavior Requirements
- Standard flattened CRUD for direct row access.
- Search, sort, pagination, bulk delete, export/import per AGENTS.md standards.
- Hide `company_id` from UI.

## 6. API Actions (If Applicable)
- **import_excel_rows** — `index.php`.
- Parent AJAX handlers for inline add/edit/delete.

## 7. File Structure
- **index.php**, **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php**.

## 8. Multi-Tenant Rules
- All queries must filter by session `company_id`.
- `ops_report_id` must reference an existing **ops_report** row; DB does not enforce matching `company_id` on the parent (validate in application code if hardening).

## 9. Audit Logging Requirements
- Triggers: `trg_ops_report_night_shift_audit_insert|update|delete`.

## 10. Common Pitfalls
- Do not hardcode night-shift UI labels here — they are editable via parent `report_ui_json`. [Valid]-[2026-07-15]
- Parent report delete cascades all night-shift rows. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare(
    'SELECT * FROM ops_report_night_shift WHERE company_id = ? AND ops_report_id = ? ORDER BY sort_order'
);
$stmt->bind_param('ii', $companyId, $opsReportId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare(
    'INSERT INTO ops_report_night_shift (company_id, ops_report_id, guest_name, notes) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('iiss', $companyId, $opsReportId, $guestName, $notes);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Parent: **modules/ops_report/AGENT_NOTES.md**. Regression: `php scripts/verify_ops_report.php`.
