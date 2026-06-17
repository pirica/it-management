# AGENT_NOTES.md - Ops Report Walk Round

## 1. Module Purpose
Child rows for walk-round area checks on a daily Ops Report. Each row records early-shift and late-shift notes for one named area (`area_name`) on a given `ops_report_id`.

## 2. Key Tables
- **ops_report_walk_round** — area name, `early_shift`, `late_shift`, `sort_order`.

## 3. Required Relationships
- **ops_report_walk_round** → depends on **ops_report** (`ops_report_id`, ON DELETE CASCADE).
- **ops_report_walk_round** → depends on **companies** (`company_id`).
- Primary editing UX lives in **modules/ops_report/index.php** (inline AJAX).

## 4. Business Rules (Critical for Agents)
- Every row must share `company_id` with its parent **ops_report**.
- Default walk-round areas are seeded by `opr_ensure_report()` on first open of a report date.
- **Edit lock (D-2):** parent report enforces today/yesterday edit for non-admins.
- `sort_order` controls row order in the walk-round section.

## 5. UI Behavior Requirements
- Standard flattened CRUD with search, sort, pagination, bulk delete (when rows ≥ `records_per_page`), export, import.
- Hide `company_id`; show human-readable parent report reference for `ops_report_id`.
- CSRF on POST; `active` checkbox uses double-label pattern on create/edit.

## 6. API Actions (If Applicable)
- **import_excel_rows** — bulk JSON import on `index.php`.

## 7. File Structure
- **index.php**, **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php**.

## 8. Multi-Tenant Rules
- Strict `company_id` scoping; `ops_report_id` must belong to same tenant.

## 9. Audit Logging Requirements
- Triggers: `trg_ops_report_walk_round_audit_insert|update|delete` (payload includes `ops_report_id`).

## 10. Common Pitfalls
- Do not duplicate `opr_ensure_report()` seed areas without updating parent module.
- Deleting parent **ops_report** removes all walk-round rows (CASCADE).

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare(
    'SELECT * FROM ops_report_walk_round WHERE company_id = ? AND ops_report_id = ? ORDER BY sort_order'
);
$stmt->bind_param('ii', $companyId, $opsReportId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare(
    'INSERT INTO ops_report_walk_round (company_id, ops_report_id, area_name, sort_order) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('iisi', $companyId, $opsReportId, $areaName, $sortOrder);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Parent module: **modules/ops_report/AGENT_NOTES.md**. Regression: `php scripts/verify_ops_report.php`.
