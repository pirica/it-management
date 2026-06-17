# AGENT_NOTES.md - Ops Report Guest Experience

## 1. Module Purpose
Dynamic guest-experience feedback rows on a daily Ops Report. Stores reference id, guest/room details, feedback text, actions, case closure, and monitor fields per `ops_report_id`.

## 2. Key Tables
- **ops_report_guest_experience** — `ref_id`, `guest_name`, `room_number`, `time_reported`, `checkout_date`, `feedback`, `action_taken`, `case_closed`, `monitor`, `sort_order`.

## 3. Required Relationships
- **ops_report_guest_experience** → depends on **ops_report** (`ops_report_id`, ON DELETE CASCADE).
- **ops_report_guest_experience** → depends on **companies** (`company_id`).
- Inline grid editing via **modules/ops_report/index.php**.

## 4. Business Rules (Critical for Agents)
- User-added rows per report date (no default seed).
- **Edit lock (D-2)** enforced on parent report for non-admins.
- `ref_id` is optional guest-experience reference (varchar).
- Whitelist AJAX field names through parent `opr_child_table_map()`.

## 5. UI Behavior Requirements
- Standard flattened CRUD plus parent inline editors.
- List/search must include visible text columns (`feedback`, `action_taken`).
- CSRF, bulk delete gate (`$totalRows >= $perPage`), export/import tools.

## 6. API Actions (If Applicable)
- **import_excel_rows** — `index.php`.
- Parent AJAX: `ajax_add_row`, `ajax_inline_edit`, `ajax_delete_row` with `table=ops_report_guest_experience`.

## 7. File Structure
- **index.php**, **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php**.

## 8. Multi-Tenant Rules
- Strict `company_id`; parent `ops_report_id` must match tenant.

## 9. Audit Logging Requirements
- Triggers: `trg_ops_report_guest_experience_audit_insert|update|delete`.

## 10. Common Pitfalls
- Do not show raw `ops_report_id` when parent report date label can be resolved.
- Long `feedback` text must not be truncated in audit JSON triggers (DB handles full row).

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare(
    'SELECT * FROM ops_report_guest_experience WHERE company_id = ? AND ops_report_id = ?'
);
$stmt->bind_param('ii', $companyId, $opsReportId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare(
    'INSERT INTO ops_report_guest_experience (company_id, ops_report_id, guest_name, feedback) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('iiss', $companyId, $opsReportId, $guestName, $feedback);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Parent: **modules/ops_report/AGENT_NOTES.md**. Regression: `php scripts/verify_ops_report.php`.
