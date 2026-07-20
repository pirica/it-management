# AGENT_NOTES.md - Employee Companies

## 1. Module Purpose
Maps employees (login accounts) to companies they may access after login. Drives the company picker on dashboard/login and complements each employee's primary `employees.company_id`.

## 2. Key Tables
- **employee_companies** — `employee_id`, `company_id`, `granted_by_employee_id`, `active`.

## 3. Required Relationships
- **employee_companies** → depends on **employees**, **companies**.
- **employee_companies** → referenced by session `company_id` selection and `itm_user_has_company_access()` helpers.

## 4. Business Rules (Critical for Agents)
- **Administrator gate:** every entry file (`index.php`, `edit.php`, `view.php`, `list_all.php`) calls `itm_require_admin()` immediately after `config.php`; `delete.php` routes through `index.php`. Non-admins are redirected away on GET and receive HTTP 403 on POST mutations.
- **Primary link:** rows determine which companies appear in the company selection list.
- **Admin row guard:** delete/import flows check `cr_is_admin_user_company_row()` — prevents removing last admin access without confirmation (`itmConfirmUserCompanyDelete()`).
- **MBQA bypass:** `itm_user_company_assignment_bypasses_admin_delete_guard()` allows disposable test users through QA delete steps only.
- Module browser QA skips some write steps — read-only / shared auth constraints.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view renders all six scaffold audit columns via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`).
- Standard flattened CRUD with FK labels for `employee_id` and `company_id`.
- List uses `$uiColumns` (hides `company_id` and audit meta); search shares `$displayFieldColumns = $uiColumns`; list query filters `deleted_at IS NULL` via `itm_crud_append_not_deleted_predicate()`.
- Create/edit forms use `$uiColumns` with `itm_crud_render_form_hidden_audit_inputs()` for audit stamps; view keeps full `$fieldColumns` including audit meta.
- Delete uses `itm_crud_build_soft_delete_sql()` (admin-row guards still skip protected mappings on bulk/clear/single delete).
- List header uses `data-itm-new-button-managed` with centered `sanitize($moduleListHeading)`; bulk toolbar includes `bulk-delete-selection.js` and `data-itm-bulk-cancel` Cancel button.
- Delete forms set `data-is-admin="1"` when row ties to admin user — JS confirm dialog warns before POST.
- Bulk delete when row count ≥ `records_per_page`.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import with admin-row guards on each row.

## 7. File Structure
- `index.php` — list, edit (via `$crud_action === 'edit'`), import, admin delete guards, bulk actions.
- `create.php` — wrapper that routes to `index.php` with `$crud_action = 'create'` (redirects to list; no standalone create form).
- `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD entry wrappers.

## 8. Multi-Tenant Rules
- Cross-tenant by design: one user may map to many companies; each row still validates both `employee_id` and `company_id`.
- Writes must not attach users to companies outside admin intent.

## 9. Audit Logging Requirements
- `trg_employee_companies_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls
- Deleting admin user's last company mapping — locks admin out of tenant switcher. [Cursor-Valid]
- Omitting `company_id` on DELETE — cross-tenant data loss. [Cursor-Valid]
- Treating this module as per-user CRUD for regular users — typically admin-maintained. [Cursor-Valid]
- Confusing with `employees.company_id` primary field — both must stay consistent for login defaults. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Tenant-scoped mapping soft-delete
```php
$where = ' WHERE id=? AND company_id=?';
$deleteSql = itm_crud_build_soft_delete_sql('employee_companies', $where, (int)$_SESSION['employee_id']);
$stmt = mysqli_prepare($conn, $deleteSql);
mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
```

## 12. Module Owner Notes (Optional)
Critical for multi-tenant access control. Coordinate with `modules/employees/` and login flow in `login.php` when changing assignment rules.

**Fresh-import seeds:** each company Admin gets a home-company row; TechCorp `Admin` (company 1) is also granted companies 2–5 for the tenant switcher / full-module QA. Prefer `INSERT … SELECT` from `employees` / `companies` over hardcoded `employee_id = 1` only. The late replicate block only upserts home grants (`company_id = employees.company_id`) — it does not attach every passworded employee to every company.
