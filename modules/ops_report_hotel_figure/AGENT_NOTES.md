# AGENT_NOTES.md - Ops Report Hotel Figure

## 1. Module Purpose
Dynamic extra Hotel Figures & Revenue fields on a daily Ops Report. Each row is a custom label/value pair (`field_label`, `field_value`) attached to `ops_report_id` beyond the fixed scalar metrics on **ops_report**.

## 2. Key Tables
- **ops_report_hotel_figure** — `field_label`, `field_value`, `sort_order`.

## 3. Required Relationships
- **ops_report_hotel_figure** → depends on **ops_report** (`ops_report_id`, ON DELETE CASCADE).
- **ops_report_hotel_figure** → depends on **companies** (`company_id`).
- Rendered in parent **modules/ops_report/index.php** figures section; users add custom rows when date is editable.

## 4. Business Rules (Critical for Agents)
- `field_label` is required (NOT NULL in schema).
- User-defined rows — no automatic seed.
- **Edit lock (D-2) — parent only:** enforced on **modules/ops_report/index.php** AJAX for non-admins (today/yesterday). Standalone CRUD here is not date-locked.
- Fixed revenue metrics (occupancy, RevPAR, etc.) stay on **ops_report** table, not here.

## 5. UI Behavior Requirements
- Standard flattened CRUD for administrative access.
- Search includes `field_label` and `field_value`.
- Standard module toolbar: bulk delete, export, import, pagination.

## 6. API Actions (If Applicable)
- **import_excel_rows** — `index.php`.
- Parent: `ajax_add_row` / `ajax_inline_edit` / `ajax_delete_row` for custom figure rows.

## 7. File Structure
- **index.php**, **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php**.

## 8. Multi-Tenant Rules
- All queries must filter by session `company_id`.
- `ops_report_id` must reference an existing **ops_report** row; DB does not enforce matching `company_id` on the parent (validate in application code if hardening).

## 9. Audit Logging Requirements
- Triggers: `trg_ops_report_hotel_figure_audit_insert|update|delete`.

## 10. Common Pitfalls
- Do not move core hotel KPIs into this table — they belong on **ops_report** header row. [Valid]-[2026-07-15]
- Label/value pairs export with parent report Excel/PDF, not via this CRUD list alone. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare(
    'SELECT * FROM ops_report_hotel_figure WHERE company_id = ? AND ops_report_id = ? ORDER BY sort_order'
);
$stmt->bind_param('ii', $companyId, $opsReportId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare(
    'INSERT INTO ops_report_hotel_figure (company_id, ops_report_id, field_label, field_value) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('iiss', $companyId, $opsReportId, $fieldLabel, $fieldValue);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Parent: **modules/ops_report/AGENT_NOTES.md**. Regression: `php scripts/verify_ops_report.php`.
