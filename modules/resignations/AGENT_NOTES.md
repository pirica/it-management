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
- Only rows with non-null, non-empty `termination_date` are eligible.

## 4. Business Rules (Critical for Agents)
- Page title: `Weekly Resignations Report - Week {ISO week}/{2-digit year}` (example: Week 23/26).
- Filters: `year`, `month`, `week` (ISO `WEEK(..., 3)`), Employment Status `IN (...)`, Employee Type `IN (...)` (NULL type allowed).
- Default Employment Status selection: **Active**, **Inactive**, **On Leave**, **Terminated** (not **Contractor**).
- Default Employee Type selection: **Team member**, **Internship**.
- Official Resignation Week column formats `termination_date` as `{week}/{yy}`.

## 5. UI Behavior Requirements
- **index.php only** — no create/edit/delete handlers.
- Filter card: Week, Month, Year, Employment Status multi-select, Employee Type multi-select, Search (all fields). Control card uses `data-itm-no-export-pdf` and `data-itm-no-export-excel`.
- Table columns: ID TM (`external_id`), Name, Team member / Internship (`employee_type.name_type`), Department, Admission date (`start_date`), Last work day (`termination_date`), Official Resignation Week.
- Header actions: **Employees**.
- Sidebar: `📋 Resignations` in Employee section (`includes/ui_config.php`).

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php** — list view with filters, search, sort, export via shared `table-tools.js`.

## 8. Multi-Tenant Rules
- All queries filter `e.company_id = ?`.

## 9. Audit Logging Requirements
- Read-only; no writes.

## 10. Common Pitfalls
- Do not add CRUD scaffolding unless requested — this module is intentionally index-only.
- Week/month/year filters are combined with AND; changing one selector affects the result set together with the others.

## 12. Module Owner Notes (Optional)
Pattern based on `modules/birthdays/index.php`. Regression: `php scripts/verify_employee_type_resignations.php`.
