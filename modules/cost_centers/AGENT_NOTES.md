# AGENT_NOTES.md - Cost Centers

## 1. Module Purpose
Manages cost centers for financial tracking and budgeting.

## 2. Key Tables
- **cost_centers** — stores cost center names and codes.

## 3. Required Relationships
- **cost_centers** → depends on **companies**.
- **cost_centers** → depends on **departments**.
- **cost_centers** → referenced by **annual_budgets**, **expenses**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Cost center name must be unique within a company.
- **Department Link**: Every cost center must be linked to a valid department.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Restrictive Deletes**: Cannot delete a cost center that has budget or expense records associated with it (RESTRICT).

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM cost_centers WHERE company_id = ? AND department_id = ?");
$stmt->bind_param("ii", $companyId, $deptId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO cost_centers (company_id, department_id, name, code) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $companyId, $deptId, $name, $code);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for departmental financial accountability.
