# AGENT_NOTES.md - Budget Report

## 1. Module Purpose
Provides a financial overview and comparison of actual expenses vs. budgets. It allows period-based comparisons (e.g., MoM, YoY).

## 2. Key Tables
- Primarily reads from **annual_budgets**, **monthly_budgets**, and **expenses**.

## 3. Required Relationships
- Depends on **companies**, **cost_centers**, and **gl_accounts**.

## 4. Business Rules (Critical for Agents)
- **Calculated View**: This is a report module; it aggregates data rather than managing a primary table of its own.
- **Tenant Isolation**: Only aggregates data for the active `company_id`.

## 5. UI Behavior Requirements
- **Period selector:** Form filters via `year`, `month` (optional), `cost_center_id`, and `gl_account_id`.
- **Search (all fields):** `search` query param filters cost center name, GL account code, and account name via prepared `LIKE` clauses; reset uses emoji-only 🔙 (`title="Clear"`).
- **Column sort:** `sort` / `dir` query params with ▲/▼ header links on cost center, GL fields, and numeric period columns; default `cost_center` ASC with `ga.account_code` tie-break. Variance columns (Forecast − Actual, etc.) are computed in PHP and are not sortable.
- **No pagination / CRUD shell:** full result set on one page; no Actions column or create/edit/view/delete/list_all entry files. Gate-excluded UI configuration checks for pagination, Table Actions, new-button layout, bulk delete, and CRUD entry files are reviewed in `scripts/data/ui_configuration_reviewed.json` (manifest: `scripts/ui_configuration_reviewed.php`).
- **Comparison Columns:** Shows current actuals, previous month, and previous year same month.

## 6. API Actions (If Applicable)
- None (Read-only view).

## 7. File Structure
- **index.php** — main report interface and logic.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- None for the report itself, as it is read-only.

## 10. Common Pitfalls
- **Division by Zero**: Handle cases where budgets are zero when calculating variances. [Cursor-Valid]
- **Missing Data**: Ensure the report handles missing budget or expense records for a period gracefully (displaying 0 or "—"). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (Aggregation)
```php
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE company_id = ? AND year = ? AND month = ?");
$stmt->bind_param("iii", $companyId, $year, $month);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
```

## 12. Module Owner Notes (Optional)
Critical for finance department review sessions.
