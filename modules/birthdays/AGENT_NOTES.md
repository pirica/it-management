# AGENT_NOTES.md - Birthdays

## 1. Module Purpose
Read-only monthly birthday list for the active company. Data is sourced from `employees.birthday`; no dedicated table.

## 2. Key Tables
- **employees** — `birthday`, `hide_year`, `first_name`, `last_name`, `photo`, `username`, `user_id`, `department_id`, `employment_status_id`.
- **employee_statuses** — `name` for employment filter (`Active`, `On Leave` only).
- **departments** — `code` for the Department column; `name` included in search (joined on `department_id` + `company_id`).

## 3. Required Relationships
- **birthdays** → depends on **employees** and **departments**.
- Profile thumbnails use `includes/employee_profile_photo.php` (`emp_profile_photo_url()`).

## 4. Business Rules (Critical for Agents)
- Only employees with non-null `birthday` and **Employment Status** `Active` or `On Leave` appear (`INNER JOIN employee_statuses` on `employment_status_id` + `company_id`).
- Excludes Contractor, Inactive, Terminated, and other statuses.
- Month filter uses `MONTH(e.birthday)`; default month is the current calendar month.
- `hide_year` on the employee row controls display format (`j M` vs `j M Y`) via `emp_format_birthday_display()`.
- Strictly scoped by `company_id`.

## 5. UI Behavior Requirements
- **index.php only** — no create/edit/delete handlers.
- Filter card: month `<select>` and **Search (all fields)** across Name, Day, Department (`departments.code`), and department name (`departments.name`); marked `data-itm-no-export-pdf="1"` and `data-itm-no-export-excel="1"` so PDF/Excel exports omit controls.
- Table columns: Name (first + last, optional photo), Day (day of month without leading zeros via `emp_format_birthday_day_only()` — e.g. `1`, `9`, `10`), Department (`departments.code`). No Actions column.
- Default sort: Day ASC (`DAY(e.birthday)`). Also sortable: Name, Department.
- Sortable column headers use plain text styling (no blue link colour).
- Sidebar: `🎉 Birthdays` in Employee section (`includes/ui_config.php`). Explorer sidebar links to this module and **Profile Storage** (`Private/{user}/profile`).

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php** — list view with month filter, search, sort, export-safe markup.

## 8. Multi-Tenant Rules
- All queries filter `e.company_id = ?`.

## 9. Audit Logging Requirements
- Read-only; no writes.

## 10. Common Pitfalls
- Do not add CRUD scaffolding unless requested — this module is intentionally index-only.
- PDF export must not include the month/search filter card; keep `data-itm-no-export-pdf` on the controls card.

## 12. Module Owner Notes (Optional)
List layout based on `modules/backup_tape_log/index.php` patterns (card layout, sidebar/header includes).
