# AGENT_NOTES.md - Birthdays

## 1. Module Purpose
Read-only monthly birthday list for the active company. Data is sourced from `employees.birthday`; no dedicated table.

## 2. Key Tables
- **employees** — `birthday`, `hide_year`, `first_name`, `last_name`, `photo`, `username`, `user_id`, `department_id`.
- **departments** — `code` for the Department column (joined on `department_id` + `company_id`).

## 3. Required Relationships
- **birthdays** → depends on **employees** and **departments**.
- Profile thumbnails use `includes/employee_profile_photo.php` (`emp_profile_photo_url()`).

## 4. Business Rules (Critical for Agents)
- Only employees with non-null `birthday` appear.
- Month filter uses `MONTH(e.birthday)`; default month is the current calendar month.
- `hide_year` on the employee row controls display format (`j M` vs `j M Y`) via `emp_format_birthday_display()`.
- Strictly scoped by `company_id`.

## 5. UI Behavior Requirements
- **index.php only** — no create/edit/delete handlers.
- Filter card: month `<select>` and name search; marked `data-itm-no-export-pdf="1"` and `data-itm-no-export-excel="1"` so PDF/Excel exports omit controls.
- Table columns: Name (first + last, optional photo), Day of Birth, Department (`departments.code`), Actions (link to employee view).
- Default sort: Day of Birth ASC (`DAY(e.birthday)`). Also sortable: Name, Department.
- Sidebar (main app): `🎉 Birthdays` and `🌐 Explorer` in Employee section (`includes/ui_config.php`).

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
