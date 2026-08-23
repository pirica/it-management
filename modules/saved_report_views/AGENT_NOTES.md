# AGENT_NOTES.md - Saved Report Views

## 1. Module Purpose

JSON API and redirect entry for **saved list views** (custom report builder). Users save filter/column presets from Tickets, Equipment, or Expenses; views appear in Reports Hub **My reports** and can be scheduled via `scheduled_reports` (`saved_view:{id}` slug).

## 2. Key Tables

- **saved_report_views** — `filters_json`, `columns_json`, `shared_scope` (`private` | `department` | `company`), tenant + owner `employee_id`
- **scheduled_reports** — optional `saved_view_id` FK when `report_slug` is `saved_view:{id}`

## 3. Required Relationships

- `company_id` → `companies`
- `employee_id` → `employees` (owner)
- `share_department_id` — set when `shared_scope = department` (owner’s department at save time)

## 4. Business Rules

- Supported modules: `tickets`, `equipment`, `expenses` only (`includes/itm_saved_reports.php` whitelists filter keys per module).
- List pages expose filter controls (status/assignee/dates on tickets; type on general equipment; date/paid status/supplier on expenses). Save modal reads live values from `data-itm-saved-reports-list-form` + `data-itm-saved-report-filter` fields.
- Save modal includes a **column picker** (checkboxes per whitelisted column); at least one column required.
- Tickets list query is shared: `includes/itm_tickets_list_query.php` (index + saved-view run).
- Scheduled email attachments use `tabular_csv` from `itm_saved_reports_build_tabular_csv()` when dataset is a saved view.
- Shared views are **read-only** for non-owners; only owner may update/delete.
- `GET api.php?action=run&id=` returns JSON rows; rate-limited via `itm_api_enforce_rate_limit_or_exit()`.
- Module slug is in `itm_module_access_always_allowed_slugs()` — API is invoked from other list modules.

## 7. File Structure

| File | Role |
|------|------|
| `api.php` | `save`, `delete`, `list`, `run` actions |
| `index.php` | Redirect to Reports Hub `#my-saved-reports` |
| `../../includes/itm_saved_reports.php` | Validation, access, query runners |
| `../../includes/itm_saved_reports_ui.php` | Save-view modal included on list pages |

## 12. Module Owner Notes

- Regression: `php scripts/verify_saved_report_views.php`
- Reports Hub UI: `modules/reports/index.php` → **My reports**
- Schedule email: admin uses Reports Hub schedule modal or 📧 on owned saved views
