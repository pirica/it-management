# AGENT_NOTES.md - Users

## 1. Module Purpose
Manages system users: credentials, role assignments, and password-vault keys.

## 2. Key Tables
- **users** — main user account data.

## 3. Required Relationships
- **users** → **companies** (primary company).
- **users** → **user_roles**.
- **user_companies** — multi-company access.

## 4. Business Rules (Critical for Agents)
- **Security:** passwords hashed; vault master keys handled via session (`vault_key`).
- **Administrator gate:** all user-management entry points call `itm_require_admin()`; `delete.php` routes to `index.php` (same contract as `view.php`). Non-admins receive HTTP 403 on POST mutations (delete, create, edit, import).
- **List/view columns:** `$uiColumns` for list and view must pass through `itm_users_filter_ui_columns()` so `password`, `vault_key_hash`, and reset-token fields never render in HTML.
- **Role assignment:** users may assign only roles permitted by **role_assignment_rights**.
- **Skip clear:** `users` is never auto-cleared in module browser QA (shared auth).

## 5. UI Behavior Requirements
- Standard CRUD; secure password reset flow.
- Sensitive columns stripped via `itm_users_filter_ui_columns()` on list/view.
- Role dropdown respects **role_assignment_rights** — users cannot assign roles above their rights.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — admin-only bulk user import with custom column handling in index handler.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Primary `company_id`; additional tenants via `user_companies`. Hide raw `company_id` in UI lists where standard.

## 9. Audit Logging Requirements
- `trg_users_audit_insert|update|delete` in `database.sql`.
- Never log plaintext passwords or `vault_key_hash` in application error logs.

## 10. Common Pitfalls
- Rendering `password`, `vault_key_hash`, or reset-token fields in list/view HTML.
- Allowing non-admin POST on create/edit/delete/import — must call `itm_require_admin()` on every mutation path.
- Module browser QA auto-clear — `users` table is excluded from clear-table steps (shared auth).
- Hardcoding user id `1` in scripts/tests — use `scripts/lib/itm_script_test_user.php`.

## 11. Examples of Safe Code Patterns

### Filter sensitive columns from UI
```php
$uiColumns = itm_users_filter_ui_columns($uiColumns);
```

### Admin-only mutation gate
```php
itm_require_admin($conn, $_SESSION['user_id'] ?? 0);
```

## 12. Module Owner Notes (Optional)
Core identity module — coordinate with `modules/user_companies/` and `modules/user_roles/`.
