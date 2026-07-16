# AGENT_NOTES.md - Contacts

## 1. Module Purpose
Provides a consolidated "Resume" or "Directory" view of employees and departments for quick contact lookups.

## 2. Key Tables
- Reads from **employees**, **departments**, **employee_positions**, and **employee_statuses**.

## 3. Required Relationships
- Depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Filter**: Only shows employees where `on_contacts = 1`.
- **Status Check**: Only shows active employees (via `employee_statuses.active = 1`).
- **Read-Only / Inline**: Primarily a read-only list, though it may support some inline editing for contact fields.

## 5. UI Behavior Requirements
- **Categorized View**: Grouped by Department.
- **Searchable**: Fast lookup for names and numbers.

## 6. API Actions (If Applicable)
- **api/inline_edit.php**: Handles inline updates for contact fields. Restricted to the employee themselves or administrators (IDOR protection).

## 7. File Structure
- **index.php** — main contact directory logic.
- **api/** — might contain async search/update helpers.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Mutation actions (if any) log to source tables (Employees).

## 10. Common Pitfalls
- **Missing Contacts**: Forgetting to set `on_contacts = 1` in the Employees module will hide them here. [Cursor-Valid]
- **Privacy**: Be mindful of displaying personal vs. work numbers. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';
$join = itm_employee_active_employment_status_join_sql('e', 'es');
$predicate = itm_employee_active_employment_status_predicate_sql('es');
$stmt = $conn->prepare("SELECT e.* FROM employees e{$join} WHERE e.company_id = ? AND e.on_contacts = 1 AND {$predicate}");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Highly used for internal communication.
