# AGENT_NOTES.md - Todo

## 1. Module Purpose
Microsoft To-Do–style task list for the company. Supports categories, departments, assignees, completion, importance, due dates, and shared/global tasks.

## 2. Key Tables
- **todo** — main task records (tracks tracking fields: `created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_by`, `deleted_at`, `active`).
- **todo_categories** — user/company-scoped category names.

## 3. Required Relationships
- **todo** → depends on **companies**, **users** (assignee, creator), **departments**, **todo_categories**.
- Visibility helpers in `includes/todo_visibility.php`.

## 4. Business Rules (Critical for Agents)
- Global tasks: `assigned_to_employee_id IS NULL` (visible to company).
- Private/assigned tasks: user must be in `assigned_to_employee_id` (comma-separated IDs via `FIND_IN_SET`) or be `created_by`.
- **Assignee dropdown:** load active users scoped to `company_id` only (`users.company_id` or active `employee_companies` row via `COALESCE(uc.active, 1) = 1`) using `itm_mysqli_stmt_fetch_all_assoc()` (mysqlnd fallback). After tasks load, `todo_merge_assignee_users()` augments the map with inactive assignees via tenant-scoped per-id lookups (`itm_mysqli_stmt_fetch_assoc()`) so list/view labels stay visible. Do not include global `Admin` via username bypass.
- Import resolves category names, department names/codes, and usernames to IDs.
- Use `itm_todo_visibility_sql()` on all list queries.

## 5. UI Behavior Requirements
- Custom task UI on index (not standard table-only list).
- **Responsive:** sidebar stacks above task list below 768px; task titles wrap on narrow viewports (`index.php` inline CSS).
- `import_excel_rows` JSON handler on POST.
- CSRF on mutations.
- **Search:** index search matches title/description plus category, department, and assignee labels via `includes/itm_todo_search.php` (`FIND_IN_SET` on CSV `*_id` columns).

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk task import with category/department/username resolution.
- **toggle_complete** / inline AJAX handlers on index — must apply `itm_todo_visibility_sql()` before UPDATE.

## 7. File Structure
- `index.php` — main UI, import, visibility-filtered queries.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD entry points.

## 8. Multi-Tenant Rules
- `company_id` on all rows; visibility rules further restrict by user.

## 9. Audit Logging Requirements
- Database audit triggers on `todo` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not use raw `mysqli_query` with unescaped `$company_id` in new code — prefer prepared statements.
- `assigned_to_employee_id` may hold multiple IDs — use `FIND_IN_SET`, not `=`.
- Always apply `itm_todo_append_visibility_filter()` or equivalent SQL.

## 11. Examples of Safe Code Patterns

### Visibility filter
```php
$conditions[] = itm_todo_visibility_sql();
$types .= 'ii';
$params[] = $loggedUserId;
$params[] = $loggedUserId;
```

## 12. Module Owner Notes (Optional)
Bespoke UI — retest sharing and import after schema or visibility changes.
