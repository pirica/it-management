# AGENT_NOTES.md - Ops Report Butler

## 1. Module Purpose
Dynamic suites-butler service rows on a daily Ops Report. Each row records a room number and notes for butler visits linked to `ops_report_id`.

## 2. Key Tables
- **ops_report_butler** — `room_number`, `notes`, `sort_order`.

## 3. Required Relationships
- **ops_report_butler** → depends on **ops_report** (`ops_report_id`, ON DELETE CASCADE).
- **ops_report_butler** → depends on **companies** (`company_id`).
- Primary UX: **modules/ops_report/index.php** butler section (add/delete rows via AJAX).

## 4. Business Rules (Critical for Agents)
- User-added rows only (no seed on report create).
- **Edit lock (D-2) — parent only:** enforced on **modules/ops_report/index.php** AJAX for non-admins (today/yesterday). Standalone CRUD here is not date-locked.
- Any user may add/delete butler rows when parent date is editable.

## 5. UI Behavior Requirements
- Standard flattened CRUD (secondary access path).
- Search across `room_number` and `notes`.
- Standard bulk toolbar, pagination, export/import.
- CSRF on all POST handlers via `cr_require_valid_csrf_token()`; forms include `csrf_token` from `itm_get_csrf_token()`.

## 6. API Actions (If Applicable)
- **import_excel_rows** — `index.php`.
- Parent: `ajax_add_row` / `ajax_delete_row` for butler table.

## 7. File Structure
- **index.php**, **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php**.

## 8. Multi-Tenant Rules
- All queries must filter by session `company_id`.
- `ops_report_id` must reference an existing **ops_report** row; DB does not enforce matching `company_id` on the parent (validate in application code if hardening).

## 9. Audit Logging Requirements
- Triggers: `trg_ops_report_butler_audit_insert|update|delete`.

## 10. Common Pitfalls
- Keep parent and CRUD delete behaviour aligned (CASCADE from parent report delete).
- `notes` is free text — sanitise on output (`sanitize()` / `htmlspecialchars`).

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare(
    'SELECT * FROM ops_report_butler WHERE company_id = ? AND ops_report_id = ? ORDER BY sort_order'
);
$stmt->bind_param('ii', $companyId, $opsReportId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare(
    'INSERT INTO ops_report_butler (company_id, ops_report_id, room_number, notes) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('iiss', $companyId, $opsReportId, $roomNumber, $notes);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Parent: **modules/ops_report/AGENT_NOTES.md**. Regression: `php scripts/verify_ops_report.php`.
