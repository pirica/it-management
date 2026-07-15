# AGENT_NOTES.md - Audit Logs

## 1. Module Purpose
Admin-facing audit trail for **non-private** INSERT, UPDATE, and DELETE activity. Rows capture `table_name`, `record_id`, `action`, and old/new values as JSON. This module **does not** log private user content — see **Private data exclusion** below and `AGENTS.md` → **Private data — no audit trail**.

## 2. Key Tables
- **audit_logs** — central repository for audit records (not itself trigger-audited).

## 3. Required Relationships
- **audit_logs** → depends on **companies**.
- **audit_logs** → depends on **employees** (via `employee_id`).

## 4. Business Rules (Critical for Agents)
- **Admin-only UI:** `index.php` and `view.php` require `itm_is_admin()`; non-admins are redirected to the dashboard.
- **Immutable**: Audit logs should generally not be editable. The UI supports viewing; admins may back up, download, or clear all tenant logs for maintenance.
- **Admin maintenance actions (`index.php`):** when an administrator is signed in, the list view exposes **Download ALL Logs** (streams a tenant-scoped `.sql` export), **Backup ALL Logs** (writes the same SQL dump under `backups/` via `BACKUP_PATH`, with best-effort duplicate copy when `DUPLICATE_BACKUP_PATH` is set), and **Clear ALL Logs** (deletes all `audit_logs` rows for the active `company_id` after confirm). All three use CSRF POST handlers and re-check `itm_is_admin()`.
- **JSON Metadata**: Old and new values are stored as JSON strings.
- **Automatic triggering:** Most **audited** tables use MySQL triggers (`trg_{table}_audit_insert|update|delete`) in `database.sql`. PHP modules may also call `itm_log_audit()` when `ui_configuration.enable_audit_logs` is on.
- **Private data exclusion:** These tables must **not** have audit triggers and must **not** receive `audit_logs` rows — `emails`, `password_entries`, `password_folders`, `private_contacts`, `todo_categories`, `todo`, `notes`, `note_labels`, `bookmark_folders`, `bookmarks`. The list view shows an informational note; absence of rows for those tables is expected.
- **Employees trigger redaction:** `trg_employees_audit_*` in `database.sql` must not log `password`, `vault_key_hash`, `reset_token`, `reset_token_hash`, or `reset_token_expires_at`.

## 5. UI Behavior Requirements
- **Searchable**: Search by table name, record ID, user, or action. List search matches employee full name, username, and email via `LEFT JOIN employees` (not only raw `employee_id`).
- **Detailed View**: View the JSON diff between old and new states.
- **Admin toolbar**: Download ALL Logs, Backup ALL Logs, and Clear ALL Logs buttons (admin role only).
- **Private-data notice:** `index.php` includes short copy that private user modules are excluded from this trail (passwords, notes, bookmarks, etc.).
- **Responsive:** filter/KPI grids single column below 768px; audit user cells wrap on mobile. Table scroll uses global `.audit-table-wrap` from `css/styles.css` — do not redeclare it in the inline `<style>` block.

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php** — main search and list view.
- **view.php** / **view_all.php** — detailed record view.
- **create.php** — (rarely used) manual log entry.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`. Admins only see logs for their active company.

## 9. Audit Logging Requirements
- This module is the **consumer** of audit rows produced elsewhere; it does not audit its own `DELETE` (clear-all) operations via triggers.
- `scripts/check_audit_logs_coverage.php` skips this module (`audit_logs` table is trigger-exempt).

## 10. Common Pitfalls
- **Performance**: Querying large `audit_logs` tables can be slow; ensure `record_id` and `table_name` are indexed. [Valid]-[2026-07-15]
- **JSON Parsing**: Ensure PHP handles null or malformed JSON values in `old_values`/`new_values` gracefully. [Valid]-[2026-07-15]
- **Expecting private-module history**: Do not add triggers or `itm_log_audit()` for private-data tables — compliance requires they stay out of `audit_logs`. [Valid]-[2026-07-15]

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
Essential for compliance and debugging on shared/operational data. Do not disable production audit triggers on **non-private** tables. Private-data tables are **intentionally** without triggers per `AGENTS.md`.
