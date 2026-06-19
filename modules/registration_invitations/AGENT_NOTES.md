# AGENT_NOTES.md - Registration Invitations

## 1. Module Purpose
Admin-only CRUD for onboarding invitations. Each row stores a unique `invitation_code`, recipient email, optional role/access-level defaults, and acceptance/expiry metadata for self-service registration.

## 2. Key Tables
- **registration_invitations** — `email`, `invitation_code`, `invited_by_employee_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`.

## 3. Required Relationships
- **registration_invitations** → **companies** (`company_id`, unique per company+email).
- **registration_invitations** → **users** (`invited_by_employee_id` sender).
- **registration_invitations** → **employee_roles** (`role_id`), **access_levels** (`access_level_id`).

## 4. Business Rules (Critical for Agents)
- **Admin only:** all entry points call `itm_require_admin()` — non-admins get HTTP 403 on mutations.
- **Unique email per company:** `uq_registration_invitations_company_scope` (`company_id`, `email`).
- **`invitation_code` is NOT NULL:** import must generate a code when Excel payload omits it (index import handler).
- **Accepted invitations:** set `accepted_at` when used; do not reuse consumed codes for new accounts.
- **Expiry:** honour `expires_at` when registration flow validates tokens.

## 5. UI Behavior Requirements
- Standard flattened CRUD (`index.php` procedural template).
- `invitation_code` kept visible in list/export columns so import payloads always carry the required field.
- Bulk delete / clear table when row count ≥ `records_per_page`.
- FK dropdowns for `role_id`, `access_level_id`, `invited_by_employee_id` render labels, not raw IDs.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import; auto-generates `invitation_code` when missing from row data.

## 7. File Structure
- `index.php` — list, search, import, bulk actions.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD wrappers (admin-gated).

## 8. Multi-Tenant Rules
- All queries filter `company_id = session company`.
- Hide `company_id` from UI per standard CRUD rules.

## 9. Audit Logging Requirements
- `trg_registration_invitations_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls
- Allowing non-admin users to create invitations — breaks onboarding security model.
- Import rows without `invitation_code` — must generate unique code server-side, not insert NULL.
- Deleting invitations that are still pending without checking registration flow consumers.
- Exposing invitation codes in audit log payloads to non-admin users.

## 11. Examples of Safe Code Patterns

### Admin gate at top of entry file
```php
require_once '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
```

### Tenant-scoped single delete
```php
$stmt = $conn->prepare('DELETE FROM registration_invitations WHERE id = ? AND company_id = ?');
$stmt->bind_param('ii', $id, $companyId);
```

## 12. Module Owner Notes (Optional)
Controlled onboarding for new system users. Registration consumer (login/sign-up flow) lives outside this folder — keep code/token contract aligned when changing column shapes.
