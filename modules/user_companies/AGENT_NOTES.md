# AGENT_NOTES.md - User Companies

## 1. Module Purpose
Maps users to companies they may access after login. Drives the company picker on dashboard/login and complements each user's primary `users.company_id`.

## 2. Key Tables
- **user_companies** — `user_id`, `company_id`, `granted_by_user_id`, `active`.

## 3. Required Relationships
- **user_companies** → depends on **users**, **companies**.
- **user_companies** → referenced by session `company_id` selection and `itm_user_has_company_access()` helpers.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see `AGENTS.md` §3).
- **Administrator gate:** every entry file (`index.php`, `edit.php`, `view.php`, `list_all.php`) calls `itm_require_admin()` immediately after `config.php`; `delete.php` routes through `index.php`. Non-admins are redirected away on GET and receive HTTP 403 on POST mutations.
- **Primary link:** rows determine which companies appear in the company selection list.
- **Admin row guard:** delete/import flows check `cr_is_admin_user_company_row()` — prevents removing last admin access without confirmation (`itmConfirmUserCompanyDelete()`).
- **MBQA bypass:** `itm_user_company_assignment_bypasses_admin_delete_guard()` allows disposable test users through QA delete steps only.
- Module browser QA skips some write steps — read-only / shared auth constraints.

## 5. UI Behavior Requirements
- Standard flattened CRUD with FK labels for `user_id` and `company_id`.
- Delete forms set `data-is-admin="1"` when row ties to admin user — JS confirm dialog warns before POST.
- Bulk delete when row count ≥ `records_per_page`.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import with admin-row guards on each row.

## 7. File Structure
- `index.php` — list, import, admin delete guards, bulk actions.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD entry points.

## 8. Multi-Tenant Rules
- Cross-tenant by design: one user may map to many companies; each row still validates both `user_id` and `company_id`.
- Writes must not attach users to companies outside admin intent.

## 9. Audit Logging Requirements
- `trg_user_companies_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls
- Deleting admin user's last company mapping — locks admin out of tenant switcher.
- Omitting `company_id` on DELETE — cross-tenant data loss.
- Treating this module as per-user CRUD for regular users — typically admin-maintained.
- Confusing with `users.company_id` primary field — both must stay consistent for login defaults.

## 11. Examples of Safe Code Patterns

### Tenant-scoped mapping delete
```php
$stmt = $conn->prepare('DELETE FROM user_companies WHERE id = ? AND company_id = ?');
$stmt->bind_param('ii', $id, $companyId);
```

## 12. Module Owner Notes (Optional)
Critical for multi-tenant access control. Coordinate with `modules/users/` and login flow in `login.php` when changing assignment rules.
