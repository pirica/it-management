# AGENT_NOTES.md - Annual Budgets

## 1. Module Purpose
Manages annual financial budget allocations per Cost Center and GL Account for a specific fiscal year.

## 2. Key Tables
- **annual_budgets** — main budget records.

## 3. Required Relationships
- **annual_budgets** → depends on **companies**.
- **annual_budgets** → depends on **cost_centers**.
- **annual_budgets** → depends on **gl_accounts**.
- **annual_budgets** → referenced by **monthly_budgets**.

## 4. Business Rules (Critical for Agents)
- **Unique Constraint**: Only one budget record allowed per `company_id`, `cost_center_id`, `gl_account_id`, and `year`.
- **Persistence**: Deleting an annual budget will cascade delete associated monthly budgets.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Formatted Currency**: Amounts should be handled as decimals.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers (`trg_annual_budgets_audit_*`).

## 10. Common Pitfalls
- **Duplicate Budgets**: Attempting to insert a budget for a combination that already exists will trigger a unique key violation. [Valid]-[2026-07-15]
- **Cascade Deletes**: Be careful when deleting annual budgets as it removes the monthly breakdown. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM annual_budgets WHERE company_id = ? AND year = ?");
$stmt->bind_param("ii", $companyId, $year);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO annual_budgets (company_id, cost_center_id, gl_account_id, year, amount) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiid", $companyId, $costCenterId, $glAccountId, $year, $amount);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Ensure the year is valid (usually current or future).
