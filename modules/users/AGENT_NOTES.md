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
- **Role assignment:** users may assign only roles permitted by **role_assignment_rights**.
- **Skip clear:** `users` is never auto-cleared in module browser QA (shared auth).

## 5. UI Behavior Requirements
- Standard CRUD; secure password reset flow.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Primary `company_id`; additional tenants via `user_companies`. Hide raw `company_id` in UI lists where standard.

## 9. Audit Logging Requirements
- Database audit triggers on user changes.

## 12. Module Owner Notes (Optional)
Core identity module — coordinate with `modules/user_companies/` and `modules/user_roles/`.
