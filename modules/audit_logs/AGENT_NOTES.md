# AGENT_NOTES.md - Audit Logs

## 1. Module Purpose
Provides a comprehensive trail of all mutations (INSERT, UPDATE, DELETE) across the system. It captures old and new values in JSON format.

## 2. Key Tables
- **audit_logs** — central repository for all audit records.

## 3. Required Relationships
- **audit_logs** → depends on **companies**.
- **audit_logs** → depends on **employees** (via `employee_id`).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Admin-only UI:** `index.php` and `view.php` require `itm_is_admin()`; non-admins are redirected to the dashboard.
- **Immutable**: Audit logs should generally not be editable. The UI supports viewing; admins may back up, download, or clear all tenant logs for maintenance.
- **Admin maintenance actions (`index.php`):** when an administrator is signed in, the list view exposes **Download ALL Logs** (streams a tenant-scoped `.sql` export), **Backup ALL Logs** (writes the same SQL dump under `backups/` via `BACKUP_PATH`, with best-effort duplicate copy when `DUPLICATE_BACKUP_PATH` is set), and **Clear ALL Logs** (deletes all `audit_logs` rows for the active `company_id` after confirm). All three use CSRF POST handlers and re-check `itm_is_admin()`.
- **JSON Metadata**: Old and new values are stored as JSON strings.
- **Automatic Triggering**: Most audit logging is handled by MySQL triggers (`trg_{table}_audit_*`).
- **Users trigger redaction:** `trg_users_audit_*` in `database.sql` must not log `password`, `reset_token`, or `reset_token_hash` (credential and reset secrets stay out of `audit_logs`).

## 5. UI Behavior Requirements
- **Searchable**: Search by table name, record ID, user, or action. List search matches employee full name, username, and email via `LEFT JOIN employees` (not only raw `employee_id`).
- **Detailed View**: View the JSON diff between old and new states.
- **Admin toolbar**: Download ALL Logs, Backup ALL Logs, and Clear ALL Logs buttons (admin role only).
- **Responsive:** filter/KPI grids single column below 768px; audit user cells wrap on mobile.

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php** — main search and list view.
- **view.php** / **view_all.php** — detailed record view.
- **create.php** — (rarely used) manual log entry.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`. Admins only see logs for their active company.

## 9. Audit Logging Requirements
- This module logs other modules. It does not log its own deletions typically to save space.

## 10. Common Pitfalls
- **Performance**: Querying large `audit_logs` tables can be slow; ensure `record_id` and `table_name` are indexed.
- **JSON Parsing**: Ensure PHP handles null or malformed JSON values in `old_values`/`new_values` gracefully.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM audit_logs WHERE table_name = ? AND record_id = ? AND company_id = ?");
$stmt->bind_param("sii", $tableName, $recordId, $companyId);
$stmt->execute();
```

### Safe INSERT (Manual)
```php
$stmt = $conn->prepare("INSERT INTO audit_logs (company_id, employee_id, table_name, record_id, action, new_values) VALUES (?, ?, ?, ?, 'INSERT', ?)");
$stmt->bind_param("iiiss", $companyId, $employeeId, $tableName, $recordId, $jsonValues);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for compliance and debugging. Never disable triggers in production.
