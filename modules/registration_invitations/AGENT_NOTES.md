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
- **Role Assignment Rights:** `role_id` options are filtered based on the logged-in user's role assignment rights (except for full Admins). Server-side validation enforces these rights on create/edit.
- **Unique email per company:** `uq_registration_invitations_company_scope` (`company_id`, `email`).
- **`invitation_code` is NOT NULL:** import must generate a code when Excel payload omits it (index import handler).
- **Accepted invitations:** set `accepted_at` when used; do not reuse consumed codes for new accounts.
- **Expiry:** honour `expires_at` when registration flow validates tokens.
- **Registration consumer:** `register.php` resolves tenant **Active** `employment_status_id` via `itm_employee_resolve_active_status_id()` — do not hardcode status id `1` (breaks login for companies 2+). Registration form requires **Confirm Username** (`confirm_username`) with the same client/server mismatch alerts as password confirmation.
- **Invitation email on save:** `create.php` and `edit.php` call `itm_registration_invitation_notify_after_save()` after a successful INSERT/UPDATE when `active = 1`, the invitee email is valid, `invitation_code` is set, and `accepted_at` is empty. Email includes the code and a pre-filled `register.php` link via `includes/itm_registration_invitation_email.php` + `itm_send_email()`. Flash success/error appears on redirect to `index.php` through `includes/header.php`.
- **Invited by (hidden):** create/edit forms hide `invited_by_employee_id`; POST always stamps `$_SESSION['employee_id']` (hidden input + server-side override).

## 5. UI Behavior Requirements
- Standard flattened CRUD (`index.php` procedural template).
- `invitation_code` kept visible in list/export columns so import payloads always carry the required field.
- Bulk delete / clear table when row count ≥ `records_per_page`.
- FK dropdowns for `role_id`, `access_level_id` render labels, not raw IDs. `invited_by_employee_id` is hidden on create/edit (logged-in admin only).

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
