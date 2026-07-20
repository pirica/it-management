# AGENT_NOTES.md - Resignations

## 1. Module Purpose
Read-only weekly resignation report for the active company. Data is sourced from `employees.termination_date`, `start_date`, `employee_type_id`, and related lookups.

## 2. Key Tables
- **employees** — `external_id`, `first_name`, `last_name`, `start_date`, `termination_date`, `employment_status_id`, `employee_type_id`, `department_id`.
- **employee_statuses** — Employment Status multi-select filter.
- **employee_type** — Employee Type multi-select filter (`name_type` column).
- **departments** — Department column (`name`).

## 3. Required Relationships
- **resignations** → depends on **employees**, **employee_statuses**, **employee_type**, **departments**.
- Only rows with non-null `termination_date` on or after `1970-01-01` are eligible (do not use `<> '0000-00-00'` in SQL — MySQL 8 `NO_ZERO_DATE` rejects that literal).

## 4. Business Rules (Critical for Agents)
- Page title: `Weekly Resignations Report - Week {ISO week}/{2-digit year}` (example: Week 23/26).
- Filters: `year`, `month`, `week` (ISO week via `itm_iso_week_bounds()` date range + `MONTH(termination_date)`), Employment Status `IN (...)`, Employee Type `IN (...)` (NULL type allowed).
- Default Employment Status selection: **Active**, **Inactive**, **On Leave**, **Terminated** (not **Contractor**).
- Default Employee Type selection: **Team member**, **Internship**.
- Official Resignation Week column formats `termination_date` as `{week}/{yy}`.

## 5. UI Behavior Requirements
- **index.php only** — no create/edit/delete handlers.
- **List `<h1>`:** Settings-managed header row (`data-itm-new-button-managed="server"`) with centered `<?php echo sanitize($moduleListHeading); ?>`; heading text is `itm_resolve_module_sidebar_icon()` + weekly `$reportTitle`. **Employees** shortcut is gated by Settings `new_button_position` (left / `left_right` → left toolbar; `right` → right toolbar).
- **Browser `<title>`:** dynamic sidebar icon prepended to `$reportTitle` via `$crud_title`, then canonical app-name suffix.
- Filter card: Week, Month, Year, Employment Status multi-select, Employee Type multi-select, Search (all fields). Search matches visible columns including **dd/mm/yyyy** admission/last-work-day text and **Official Resignation Week** (`{week}/{yy}` via `DATE_FORMAT` / `CONCAT(WEEK…)`). Search reset uses emoji-only 🔙 (`title="Clear"`) when a query is active. Control card uses `data-itm-no-export-pdf` and `data-itm-no-export-excel`.
- Table columns: ID TM (`external_id`), Name, Team member / Internship (`employee_type.name_type`), Department, Admission date (`start_date`), Last work day (`termination_date`), Official Resignation Week. No Actions column — UI config **Table Actions** check is `n/a`.
- Default sort: Last work day ASC (`e.termination_date`). Also sortable: ID TM, Name, Employee Type, Department, Admission date, Official Resignation Week (`resignation_week` maps to `e.termination_date`). Sortable headers use ▲/▼ indicators.
- Date columns display as **dd/mm/yyyy** via `itm_format_date_display()`.
- Header actions: **Employees**.
- Sidebar: `📋 Resignations` in Employee section (`includes/ui_config.php`).
- **ui_configuration reviewed gate:** gate-excluded in `scripts/data/ui_configuration_excluded_modules.txt`; intentional gaps (no pagination, Actions column, CRUD entry files, bulk delete, new-button checks) documented in `scripts/data/ui_configuration_reviewed.json` — audit lines print `[n/a][pass|fail|n/a][reviewed]`.

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php** — list view with filters, search, sort, export via shared `table-tools.js`.

## 8. Multi-Tenant Rules
- All queries filter `e.company_id = ?`.

## 9. Audit Logging Requirements
- Read-only; no writes.

## 10. Common Pitfalls
- Do not add CRUD scaffolding unless requested — this module is intentionally index-only. [Cursor-Valid]
- Week filter uses ISO Monday–Sunday bounds from `itm_iso_week_bounds($year, $week)`; month still filters `MONTH(termination_date)` so cross-month ISO weeks respect the selected calendar month. [Cursor-Valid]
- Do not use `YEAR()` + `WEEK(..., 3)` together — PHP `date('W')` and MySQL `WEEK()` can disagree on some dates; date-range filtering matches the week selector and regression probe. [Cursor-Valid]
- Do not filter dates with `<> '0000-00-00'` in SQL — use `itm_sql_valid_date_predicate('e.termination_date')` from `includes/itm_date_format.php` (MySQL 8 strict mode raises `Incorrect DATE value: '0000-00-00'`). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant + valid termination date)
```php
$sql = 'SELECT e.id, e.external_id, e.first_name, e.last_name, e.termination_date
        FROM employees e
        WHERE e.company_id = ?
          AND ' . itm_sql_valid_date_predicate('e.termination_date') . '
          AND e.termination_date BETWEEN ? AND ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $companyId, $weekStart, $weekEnd);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Pattern based on `modules/birthdays/index.php`. Regression: `php scripts/verify_employee_type_resignations.php`. Debug a missing row: `php scripts/debug_resignations_termination_date.php --date=18/06/2026 --company_id=4 --employee_id=432 --week=25 --month=6 --year=2026` (catalogued in `scripts/scripts.php`).
