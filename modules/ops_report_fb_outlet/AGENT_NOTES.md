# AGENT_NOTES.md - Ops Report F&B Outlet

## 1. Module Purpose
Child rows for Food & Beverage outlet cover counts on a daily Ops Report. Each row stores breakfast, lunch, dinner, dado, pool, and brunch cover figures for one named outlet on a given `ops_report_id`.

## 2. Key Tables
- **ops_report_fb_outlet** — F&B outlet name and per-meal cover counts (`covers_breakfast`, `covers_lunch`, `covers_dinner`, `covers_dado`, `covers_pool`, `covers_brunch`).

## 3. Required Relationships
- **ops_report_fb_outlet** → depends on **ops_report** (`ops_report_id`, ON DELETE CASCADE).
- **ops_report_fb_outlet** → depends on **companies** (`company_id`).
- Primary editing UX lives in **modules/ops_report/index.php** (inline AJAX); this folder is flattened CRUD for admin/QA direct access.

## 4. Business Rules (Critical for Agents)
- Every row must belong to the same `company_id` as its parent **ops_report** row.
- Default outlet rows are seeded by `opr_ensure_report()` when a daily report is first opened — do not duplicate seed logic here without aligning parent module.
- **Edit lock (D-2) — parent only:** enforced on **modules/ops_report/index.php** AJAX for non-admins (today/yesterday). Standalone CRUD here is not date-locked.
- `sort_order` controls display order within the parent report F&B grid.

## 5. UI Behavior Requirements
- Standard flattened CRUD (`index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`).
- List: search across visible columns, sort, pagination, bulk delete when row count ≥ `records_per_page`, 📗/📄 export, 📥 import (`import_excel_rows`).
- Hide `company_id` from list/view; render `ops_report_id` as parent report label when FK row exists.
- CSRF: form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include `csrf_token` from `cr_get_csrf_token()`.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON bulk import via `index.php` (`data-itm-db-import-endpoint="index.php"`).

## 7. File Structure
- **index.php** — list, search, import, bulk actions.
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — standard CRUD wrappers.

## 8. Multi-Tenant Rules
- All queries must filter by session `company_id`.
- `ops_report_id` must reference an existing **ops_report** row; DB does not enforce matching `company_id` on the parent (validate in application code if hardening).

## 9. Audit Logging Requirements
- Database triggers: `trg_ops_report_fb_outlet_audit_insert|update|delete` (includes `ops_report_id` in JSON payload).

## 10. Common Pitfalls
- Do not orphan rows — deleting parent **ops_report** cascades child rows.
- Prefer parent **ops_report** inline editors for hotel-user workflows; this CRUD module is secondary.
- Whitelist field names if adding AJAX outside standard CRUD.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare(
    'SELECT * FROM ops_report_fb_outlet WHERE company_id = ? AND ops_report_id = ? ORDER BY sort_order ASC'
);
$stmt->bind_param('ii', $companyId, $opsReportId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare(
    'INSERT INTO ops_report_fb_outlet (company_id, ops_report_id, outlet_name, sort_order) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('iisi', $companyId, $opsReportId, $outletName, $sortOrder);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
See **modules/ops_report/AGENT_NOTES.md** for daily report layout, `report_ui_json`, exports, and D-2 lock. Regression: `php scripts/verify_ops_report.php`.
