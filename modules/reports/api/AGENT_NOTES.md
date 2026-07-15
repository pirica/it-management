# AGENT_NOTES.md - Reports Hub API

## 1. Module Purpose
Hosts the backend data retrieval library `helpers.php` which compiles aggregated statistics, trends, and financial performance datasets for the Reports Hub charts.

## 2. Key Tables
Aggregates metrics from:
- **equipment**, **tickets**, **employees**, **annual_budgets**, **expenses**, **license_management**, and **inventory_items**.

## 3. Required Relationships
- Joins multiple tables (e.g., matching expenses to GL accounts and budget categories) to calculate accurate budget variances.

## 4. Business Rules (Critical for Agents)
- **Prepared Statements**: All aggregation queries inside `helpers.php` must use MySQLi prepared statements to prevent SQL Injection.
- **Read-Only**: This library performs SELECT queries only. No write, insert, or delete operations occur.

## 5. UI Behavior Requirements
- Pure PHP data functions called by the main dashboard router (`modules/reports/index.php`) to produce JSON datasets parsed by Chart.js.

## 6. API Actions (If Applicable)
- Exposes modular functions like `reports_get_budget_vs_actual()`, `reports_get_equipment_status_counts()`, and `reports_get_ticket_category_distribution()`.

## 7. File Structure
- **helpers.php** — Database aggregation methods.
- **index.html** — Directory listing prevention.

## 8. Multi-Tenant Rules
- Every single helper function accepts a `$company_id` parameter and scopes all database reads accordingly.

## 9. Audit Logging Requirements
- None (read-only queries).

## 10. Common Pitfalls
- Bypassing the `$company_id` parameter or using hardcoded company identifiers. [Valid]-[2026-07-15]
- Failing to handle NULL values or division-by-zero scenarios gracefully when calculating ratios (e.g., occupancy percentage or budget execution). [Valid]-[2026-07-15]
