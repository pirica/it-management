# AGENT_NOTES.md - Saved Report Views

## 1. Module Purpose

JSON API, export, public share, and redirect entry for **saved list views** (custom report builder). Users save filter/column presets from Tickets, Equipment, or Expenses; views appear in Reports Hub **My reports** and can be scheduled via `scheduled_reports` (`saved_view:{id}` slug).

Canonical doc: **`docs/SAVED_REPORT_VIEWS.md`**.

## 2. Key Tables

- **saved_report_views** — `filters_json`, `columns_json`, `shared_scope` (`private` | `department` | `company`), tenant + owner `employee_id`
- **scheduled_reports** — optional `saved_view_id` FK when `report_slug` is `saved_view:{id}`
- **share_sessions** — temporary public read-only links (`module_slug = saved_report_views`, `record_id` = view id)

## 3. Required Relationships

- `company_id` → `companies`
- `employee_id` → `employees` (owner)
- `share_department_id` — set when `shared_scope = department` (owner’s department at save time)

## 4. Business Rules

- Supported modules: `tickets`, `equipment`, `expenses` only (`includes/itm_saved_reports.php` whitelists filter keys per module).
- List pages expose filter controls; save modal reads live values from `data-itm-saved-reports-list-form` + `data-itm-saved-report-filter` fields.
- Save modal includes a **column picker** (checkboxes per whitelisted column); at least one column required.
- **Reports Hub extensions:** row actions — Open list, Run JSON, **Export Excel/PDF**, **Edit**, **Share link**, **Schedule email** (owners), Delete.
- **Edit from Hub:** `includes/itm_saved_reports_hub_ui.php` modal → `api.php?action=save` with `id`.
- **Export:** [export.php?id=&format=xlsx|pdf](http://localhost/it-management/modules/saved_report_views/export.php?id=1&format=xlsx) (session + `can_view`; up to 5000 rows).
- **Owner scheduling:** non-admins may schedule **owned** saved views only (`itm_saved_reports_can_manage_schedule_slug()`).
- **Public share:** `api.php?action=share` (POST) → `share_sessions` + [join.php?t=](http://localhost/it-management/modules/saved_report_views/join.php) (live table, 30 min TTL).
- Shared views are **read-only** for non-owners; only owner may update/delete/share/schedule.
- `GET api.php?action=run&id=` returns JSON rows; rate-limited via `itm_api_enforce_rate_limit_or_exit()`.
- Module slug is in `itm_module_access_always_allowed_slugs()` — API is invoked from other list modules.
- **Dashboard:** `itm_saved_reports_dashboard_snapshot()` — count + preview row totals on [dashboard.php](http://localhost/it-management/dashboard.php).

## 5. UI Behavior Requirements

- No flattened CRUD list — module folder is API/export/join only; management UI lives on Reports Hub `#my-saved-reports`.
- `index.php` redirects to [modules/reports/index.php#my-saved-reports](http://localhost/it-management/modules/reports/index.php#my-saved-reports).
- Hub action buttons follow NO MIXED emoji contract (`title` carries phrases).

## 6. API Actions

| Action | Method | Auth |
|--------|--------|------|
| `save` | POST | Session + CSRF; owner for updates |
| `delete` | POST | Session + CSRF; owner |
| `list` | GET | Session |
| `get` | GET | Session + `can_view` |
| `run` | GET | Session + `can_view` + rate limit |
| `share` | POST | Session + CSRF; owner |

## 7. File Structure

| File | Role |
|------|------|
| `api.php` | `save`, `delete`, `list`, `get`, `run`, `share` |
| `export.php` | Download XLSX (CSV) or printable HTML |
| `join.php` | Public token/code read-only table |
| `index.php` | Redirect to Reports Hub `#my-saved-reports` |
| `../../includes/itm_saved_reports.php` | Validation, access, query runners, share/export |
| `../../includes/itm_saved_reports_hub_ui.php` | Hub edit + owner schedule modals |
| `../../includes/itm_saved_reports_ui.php` | Save-view modal on list pages |
| `../../includes/itm_saved_reports_list_banner.php` | Active saved-view banner on list pages |

## 8. Multi-Tenant Rules

- All rows scoped by `company_id`; list/save/run enforce active tenant from session.
- Department share uses `share_department_id` captured at save time (owner’s `department_id`).

## 9. Audit Logging Requirements

- `trg_saved_report_views_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Adding filter keys without updating `itm_saved_reports_module_config()` and the list-page `data-itm-saved-report-filter` fields — unknown keys are stripped, not passed to SQL. [Cursor-Valid]
- Extending to new modules without a shared list-query helper — run logic must reuse the same SQL as the module `index.php`. [Cursor-Valid]
- Allowing non-owners to POST `save`/`delete`/`share` — enforce `itm_saved_reports_owner_owns_view()`. [Cursor-Valid]

## 12. Module Owner Notes

- Regression: `php scripts/verify_saved_report_views.php` — [browser](http://localhost/it-management/scripts/verify_saved_report_views.php?run=1)
- Reports Hub UI: [modules/reports/index.php#my-saved-reports](http://localhost/it-management/modules/reports/index.php#my-saved-reports)
- Schedule email: owner modal on Hub or admin executive section; runner `php scripts/run_scheduled_reports.php`
