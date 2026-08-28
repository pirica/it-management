# AGENT_NOTES.md - OPEX

## 1. Module Purpose
Read-only operating expenditure rollup: budget, forecast, and posted/paid actuals for GL accounts whose `budget_categories.category_kind` is `opex`.

## 2. Key Tables
No owned table. Reads **annual_budgets**, **monthly_budgets**, **forecast_revisions**, **expenses**, **gl_accounts**, **budget_categories**, **cost_centers**.

## 3. Required Relationships
- GL classification: `gl_accounts.category_id` → `budget_categories` with `category_kind = opex`.
- Same period logic as [Budget Report](http://localhost/it-management/modules/budget_report/index.php).

## 4. Business Rules (Critical for Agents)
- **Computed view only** — no CRUD, import, or delete.
- **Tenant isolation:** `company_id` from session.
- **Actuals:** Posted + Paid expenses only; period from `COALESCE(posting_date, date)`.

## 5. UI Behavior Requirements
- Filters: `year`, optional `month`, optional `cost_center_id`, optional `gl_account_id` (OPEX GL list only).
- Search/sort match Budget Report; no pagination.
- Entry: [modules/opex/index.php](http://localhost/it-management/modules/opex/index.php) → `includes/itm_budget_category_report_bootstrap.php` with `category_kind = opex`.

## 6. API Actions (If Applicable)
- Rejects `import_excel_rows` JSON POST.

## 7. File Structure
- `index.php`, `index.html` only (bespoke UI — `docs/list_bespoke_UI.txt`).

## 8. Multi-Tenant Rules
- Scoped by active `company_id`.

## 9. Audit Logging Requirements
- None (read-only aggregation).

## 10. Common Pitfalls
- GL without `category_id` or with `other` kind does not appear on this screen — assign kind on [Budget Categories](http://localhost/it-management/modules/budget_categories/index.php). On first page load the report backfills canonical names (Revenue / Operating Expense / Capital Expense) when `category_kind` was still `other` after a code-only upgrade.
- Default report year is the tenant’s latest `annual_budgets.year` (not always the calendar year). Seed demo budgets use **2026** — change the Year filter if the grid is empty.
- Renaming category display names does not change OPEX scope; `category_kind` drives the filter.

## 11. Examples of Safe Code Patterns
Use `itm_budget_category_report_run($conn, ['category_kind' => 'opex', ...])` from `includes/itm_budget_category_report.php`.

## 12. Module Owner Notes (Optional)
Sidebar: Budgeting → OPEX. Regression: `php scripts/verify_capex_opex.php`.
