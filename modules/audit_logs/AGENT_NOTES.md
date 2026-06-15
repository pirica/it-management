# AGENT_NOTES.md - Audit Logs

## 1. Module Purpose
Provides a comprehensive trail of all mutations (INSERT, UPDATE, DELETE) across the system. It captures old and new values in JSON format.

## 2. Key Tables
- **audit_logs** — central repository for all audit records.

## 3. Required Relationships
- **audit_logs** → depends on **companies**.
- **audit_logs** → depends on **users** (via `user_id`).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Immutable**: Audit logs should generally not be editable. The UI only supports viewing and deletion (for maintenance).
- **JSON Metadata**: Old and new values are stored as JSON strings.
- **Automatic Triggering**: Most audit logging is handled by MySQL triggers (`trg_{table}_audit_*`).

## 5. UI Behavior Requirements
- **Searchable**: Search by table name, record ID, user, or action.
- **Detailed View**: View the JSON diff between old and new states.

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php** — main search and list view.
- **view.php** / **view_all.php** — detailed record view.
- **create.php** — (rarely used) manual log entry.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`. Users should only see logs for their company.

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
$stmt = $conn->prepare("INSERT INTO audit_logs (company_id, user_id, table_name, record_id, action, new_values) VALUES (?, ?, ?, ?, 'INSERT', ?)");
$stmt->bind_param("iiiss", $companyId, $userId, $tableName, $recordId, $jsonValues);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for compliance and debugging. Never disable triggers in production.
