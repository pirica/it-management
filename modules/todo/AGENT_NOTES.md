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
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract. Row meta is for soft-delete display only; this module stays **private-data exempt** from `audit_logs` triggers.
- Custom task UI on index (not standard table-only list).
- **Responsive:** sidebar stacks above task list below 768px; task titles wrap on narrow viewports (`index.php` inline CSS).
- `import_excel_rows` JSON handler on POST.
- CSRF on mutations.
- **Search:** index search matches title/description plus category, department, and assignee labels via `includes/itm_todo_search.php` (`FIND_IN_SET` on CSV `*_id` columns). List fetch uses `includes/itm_todo_list_query.php` (`todo_query_tasks_for_list()`) with Settings `records_per_page` pagination, sortable export-table headers, and emoji-only 🔙 search reset.
- **List header (Settings UI):** index list uses `data-itm-new-button-managed="server"` with centered `$moduleListHeading` (`itm_resolve_module_sidebar_icon()` + catalog **To-Do** label) and ➕ create link gated by Settings `new_button_position` (default **left**). Inline `position:relative` / centered `h1` styles satisfy `fields_missing.php` bespoke gate scrapes. Active filter name (My Day, Tasks, …) and today’s date render below the toolbar as subtitles.
- **No flattened Actions/bulk contract:** task-card list omits standard Actions column and bulk-delete toolbar; gate-excluded checks print `[n/a][n/a][reviewed]` via `scripts/data/ui_configuration_reviewed.json` (manifest: `scripts/ui_configuration_reviewed.php`).
- **Create control:** primary ➕ uses `btn btn-primary itm-list-new-button` + static `create.php` (active list `filter` stored in `$_SESSION['todo_create_filter']` for create presets).
- **POST CSRF:** non-AJAX mutations call `itm_require_post_csrf()` on `index.php`.
- **QR / code share (`join.php`):** task creator (`created_by`) may create 30-minute temporary read links. `todo_share_sessions` stores plaintext `payload_json`. UI: 📱, `images/whatsapp.svg`, and 📨 on task rows and view screen; modal via shared `includes/itm_qr_share_modal.php`. Public page: `join.php` (`ITM_QR_SHARE_PUBLIC`). Regression: `php scripts/verify_qr_share_modules.php`, `php scripts/verify_whatsapp_share.php`, `php scripts/verify_outlook_share.php`.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk task import with category/department/username resolution.
- **toggle_complete** / inline AJAX handlers on index — must apply `itm_todo_visibility_sql()` before UPDATE.
- **create_share_session** — creator-only temporary QR/code share (`todo_share_sessions`)

## 7. File Structure
- `index.php` — main UI, import, visibility-filtered queries.
- `todo_share_helpers.php` — QR share session builder (`todo_share_create_session()`)
- `join.php` — public 6-digit / token join page for shared task snapshots
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD entry points.

## 8. Multi-Tenant Rules
- `company_id` on all rows; visibility rules further restrict by user.

## 9. Audit Logging Requirements
- **Private data (no audit):** `todo` and `todo_categories` must not write to `audit_logs` and have no `trg_*_audit_*` triggers in `database.sql` (see `AGENTS.md` → **Private data — no audit trail**).

## 10. Common Pitfalls
- Do not use raw `mysqli_query` with unescaped `$company_id` in new code — prefer prepared statements. [Cursor-Valid]
- `assigned_to_employee_id` may hold multiple IDs — use `FIND_IN_SET`, not `=`. [Cursor-Valid]
- Always apply `itm_todo_append_visibility_filter()` or equivalent SQL. [Cursor-Valid]

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
