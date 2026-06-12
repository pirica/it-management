# AGENT_NOTES.md - Expenses

## 1. Module Purpose
Tracks actual financial expenditures against budgets.

## 2. Key Tables
- **expenses** — stores individual expense records.

## 3. Required Relationships
- **expenses** → depends on **companies**.
- **expenses** → depends on **cost_centers**.
- **expenses** → depends on **gl_accounts**.

## 4. Business Rules (Critical for Agents)
- **Decimal Precision**: Amounts must be handled with 2-decimal precision.
- **Reporting Period**: Each expense has a `date` which determines which budget month/year it impacts.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Formatted Currency**: Display amounts with currency symbols/formatting.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM expenses WHERE company_id = ? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $companyId, $startDate, $endDate);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Critical for generating budget vs. actual reports.
