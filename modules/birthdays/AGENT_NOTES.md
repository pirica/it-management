# AGENT_NOTES.md - Birthdays

## 1. Module Purpose
Read-only monthly birthday list for the active company. Data is sourced from `employees.birthday`; no dedicated table.

## 2. Key Tables
- **employees** — `birthday`, `first_name`, `last_name`, `department_id`, `employment_status_id`.
- **employee_statuses** — `name` for the Employment Status multi-select filter (`INNER JOIN` on `employment_status_id` + `company_id`).
- **departments** — `code` for the Department column; `name` included in search (joined on `department_id` + `company_id`).

## 3. Required Relationships
- **birthdays** → depends on **employees** and **departments**.
- Day formatting uses `emp_format_birthday_day_only()` from `includes/employee_profile_photo.php`.

## 4. Business Rules (Critical for Agents)
- Only employees with non-null `birthday` and a selected **Employment Status** appear (`INNER JOIN employee_statuses`; filter `es.id IN (...)`).
- Default status selection on first visit: **Active** and **On Leave** (tenant `employee_statuses` rows by name).
- Month filter uses `MONTH(e.birthday)`; default month is the current calendar month.
- `hide_year` on the employee row controls display format (`j M` vs `j M Y`) via `emp_format_birthday_display()`.
- Strictly scoped by `company_id`.

## 5. UI Behavior Requirements
- **index.php only** — no create/edit/delete handlers.
- Filter card: month `<select>`, **Employment Status** multi-select (`employment_status_id[]`, between Month and Search), and **Search (all fields)** across Name, Day, Department (`departments.code`), and department name (`departments.name`); marked `data-itm-no-export-pdf="1"` and `data-itm-no-export-excel="1"` so PDF/Excel exports omit controls.
- Table columns: Name (first + last, text only), Day (day of month without leading zeros via `emp_format_birthday_day_only()` — e.g. `1`, `9`, `10`), Department (`departments.code`). No Actions column.
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
- Do not add CRUD scaffolding unless requested — this module is intentionally index-only. [Valid]-[2026-07-15]
- PDF export must not include the month/search filter card; keep `data-itm-no-export-pdf` on the controls card. [Valid]-[2026-07-15]
- Do not use `LEFT JOIN` on `employee_statuses` — the `INNER JOIN` is intentional so employees without a status never appear. [Valid]-[2026-07-15]
- Month filter must use `MONTH(e.birthday)`, not `DAY()` or string formatting on the full date. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe tenant-scoped list query
```php
$sql = "SELECT e.id, e.first_name, e.last_name, e.birthday, e.hide_year, d.code AS department_code
        FROM employees e
        INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
        LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
        WHERE e.company_id = ? AND e.birthday IS NOT NULL AND es.id IN ($statusPlaceholders)";
$stmt = $conn->prepare($sql);
```

## 12. Module Owner Notes (Optional)
List layout based on `modules/backup_tape_log/index.php` patterns (card layout, sidebar/header includes).
