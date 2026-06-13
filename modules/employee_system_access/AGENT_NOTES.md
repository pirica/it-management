# AGENT_NOTES.md - Employee System Access

## 1. Module Purpose
Manages and tracks the various systems and applications an employee has access to (e.g., "Email", "VPN", "CRM").

## 2. Key Tables
- **employee_system_access** — mapping table between employees and systems.

## 3. Required Relationships
- **employee_system_access** → depends on **companies**.
- **employee_system_access** → depends on **employees**.
- **employee_system_access** → depends on **system_access** (lookup table).

## 4. Business Rules (Critical for Agents)
- **Tenant Isolation**: Only manage access for employees in the current company.
- **Revocation**: When an employee leaves, access should be marked as revoked or inactive.

## 5. UI Behavior Requirements
- **Standard View/Edit**.
- **Checklist View**: Often presented as a list of checkboxes for each available system.

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **index.php**, **edit.php**, **view.php** — standard CRUD support.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- Forgetting to update system access when an employee's role changes.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM employee_system_access WHERE employee_id = ? AND company_id = ?");
$stmt->bind_param("ii", $employeeId, $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO employee_system_access (company_id, employee_id, system_id, active) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiii", $companyId, $employeeId, $systemId, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for security audits and offboarding.
