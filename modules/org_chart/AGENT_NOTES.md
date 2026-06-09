# AGENT_NOTES.md - Org Chart

## 1. Module Purpose
Provides a visual, interactive organizational structure diagram showing the reporting lines between employees.

## 2. Key Tables
- Reads from **employees**, **departments**, and **employee_positions**.

## 3. Required Relationships
- Depends on **companies**.
- Relies on the `reports_to` self-reference in the `employees` table.

## 4. Business Rules (Critical for Agents)
- **Visibility**: Only shows active employees with `on_orgchart = 1`.
- **Drag & Drop**: Supports updating the `reports_to` field via AJAX when nodes are moved.

## 5. UI Behavior Requirements
- **Visual Diagram**: Renders a tree structure.
- **Image Export**: Option to save the chart as an image.

## 6. API Actions (If Applicable)
- **update_hierarchy** — AJAX handler to change an employee's manager.

## 7. File Structure
- **index.php** — main chart UI and hierarchy update logic.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Hierarchy updates should be logged.

## 10. Common Pitfalls
- **Circular Loops**: Ensure the UI prevents an employee from reporting to themselves or a subordinate.

## 11. Examples of Safe Code Patterns

### Safe UPDATE (Hierarchy)
```php
$stmt = $conn->prepare("UPDATE employees SET reports_to = ? WHERE id = ? AND company_id = ?");
$stmt->bind_param("iii", $managerId, $employeeId, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary way to visualize management structure.
