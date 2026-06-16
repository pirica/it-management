# AGENT_NOTES.md - Monthly Budgets

## 1. Module Purpose
Breaks down the annual budget into monthly allocations for finer financial control.

## 2. Key Tables
- **monthly_budgets** — stores monthly budget amounts.

## 3. Required Relationships
- **monthly_budgets** → depends on **companies**.
- **monthly_budgets** → depends on **annual_budgets** (via `annual_budget_id`).

## 4. Business Rules (Critical for Agents)
- **Unique Month per Year**: Only one record per company, annual budget link, and month (1-12).
- **Cascade**: Deleting an annual budget removes these monthly records.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Currency Formatting**: Handle decimal amounts.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM monthly_budgets WHERE annual_budget_id = ? AND month = ?");
$stmt->bind_param("ii", $annualBudgetId, $month);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used for monthly financial performance tracking.
