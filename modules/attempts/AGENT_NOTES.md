# AGENT_NOTES.md - Attempts

## 1. Module Purpose
Tracks authentication-related attempts, including logins and password resets. It is used for security auditing and rate limiting/brute-force protection.

## 2. Key Tables
- **attempts** — logs individual success/failure events for login and reset requests.

## 3. Required Relationships
- **attempts** → depends on **employees** (via `employee_id`, nullable).

## 4. Business Rules (Critical for Agents)
- **Log Source/Type**: Must distinguish between 'login' and 'password_reset' sources and 'success', 'failure', 'request', or 'reset' types.
- **Privacy**: IP addresses and emails are stored for auditing; handle according to local data protection laws. Login identifiers are normalized via `itm_normalize_login_attempt_identifier()` before insert — valid emails and simple usernames persist verbatim; password-like values (invalid `@` strings, special characters, mixed-case+digit secrets such as `Password123`) are stored as `[redacted:{hash}]`.

## 5. UI Behavior Requirements
- **Read-Only mostly**: UI typically allows viewing and deleting (for cleanup) but not manual "creation" or "editing" through a standard form. No `create.php` / `edit.php` — reviewed in `scripts/data/ui_configuration_reviewed.json` for `check_ui_configuration_coverage.php` (`+ New Button`, create/edit entry checks).
- **Filtering**: List view should support filtering by employee, IP, or type.
- **FK labels**: list/view uses `cr_username_for_employee_id()` to render `employee_id` as username.

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php** — list view of attempts.
- **delete.php** — handles log cleanup.
- **view.php** — detailed view of an attempt.

## 8. Multi-Tenant Rules
- **Multi-Tenant Scoping**: The table includes a `company_id` column.
- Since login attempts happen before a company is selected, `company_id` may be NULL or automatically resolved via `trg_attempts_before_insert` trigger based on employee record or active session.
- Standard queries are scoped by `company_id` when logged in.

## 9. Audit Logging Requirements
- This module *is* a form of logging. It does not typically have its own audit triggers to avoid circularity.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **High Volume**: This table can grow very large; ensure indexes are used for performance. [Cursor-Valid]
- **False Positives**: Rate limiting logic based on this table must be carefully tuned to avoid locking out legitimate users. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM attempts WHERE ip_address = ? AND attempt_source = 'login' AND created_at > ?");
$stmt->bind_param("ss", $ipAddress, $sinceTime);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO attempts (employee_id, email, attempt_source, attempt_type, ip_address) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $employeeId, $email, $source, $type, $ipAddress);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used by `login.php` and `forgot-password.php` to enforce security policies.
