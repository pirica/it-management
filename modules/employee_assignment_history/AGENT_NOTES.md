# AGENT_NOTES.md - Employee Assignment History

## 1. Module Purpose
Tracks the history of assets or roles assigned to employees over time.

## 2. Key Tables
- **employee_assignment_history** — logs the timeline of assignments.

## 3. Required Relationships
- **employee_assignment_history** → depends on **companies**.
- **employee_assignment_history** → depends on **employees**.
- **employee_assignment_history** → depends on **assignment_types**.
- **employee_assignment_history** → depends on **equipment** (optional, depending on assignment context).

## 4. Business Rules (Critical for Agents)
- **Immutable Timeline**: Entries should generally represent a point-in-time event.
- **Audit Requirement**: Changes to assignments must be recorded here.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Chronological View**: Should be sortable by date.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- Deleting assignment history instead of just ending an assignment.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM employee_assignment_history WHERE employee_id = ? AND company_id = ?");
$stmt->bind_param("ii", $employeeId, $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO employee_assignment_history (company_id, employee_id, assignment_type_id, notes) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $companyId, $employeeId, $typeId, $notes);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Vital for tracking asset custody.
