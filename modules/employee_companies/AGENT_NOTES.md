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
- Standard flattened CRUD with FK labels for `employee_id` and `company_id`.
- Delete forms set `data-is-admin="1"` when row ties to admin user — JS confirm dialog warns before POST.
- Bulk delete when row count ≥ `records_per_page`.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import with admin-row guards on each row.

## 7. File Structure
- `index.php` — list, create (via `$crud_action === 'create'`), import, admin delete guards, bulk actions.
- `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD entry wrappers (no standalone `create.php`).

## 8. Multi-Tenant Rules
- Cross-tenant by design: one user may map to many companies; each row still validates both `employee_id` and `company_id`.
- Writes must not attach users to companies outside admin intent.

## 9. Audit Logging Requirements
- `trg_employee_companies_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls
- Deleting admin user's last company mapping — locks admin out of tenant switcher. [Cursor-Valid]
- Omitting `company_id` on DELETE — cross-tenant data loss. [Cursor-Valid]
- Treating this module as per-user CRUD for regular users — typically admin-maintained. [Cursor-Valid]
- Confusing with `employees.company_id` primary field — both must stay consistent for login defaults. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Tenant-scoped mapping delete
```php
$stmt = $conn->prepare('DELETE FROM employee_companies WHERE id = ? AND company_id = ?');
$stmt->bind_param('ii', $id, $companyId);
```

## 12. Module Owner Notes (Optional)
Critical for multi-tenant access control. Coordinate with `modules/employees/` and login flow in `login.php` when changing assignment rules.

**Fresh-import seeds:** each company Admin gets a home-company row; TechCorp `Admin` (company 1) is also granted companies 2–5 for the tenant switcher / full-module QA. Prefer `INSERT … SELECT` from `employees` / `companies` over hardcoded `employee_id = 1` only. The late replicate block only upserts home grants (`company_id = employees.company_id`) — it does not attach every passworded employee to every company.
