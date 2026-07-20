# AGENT_NOTES.md - Reports Hub

## 1. Module Purpose

Visual dashboard providing key metrics across multiple domains including equipment, tickets, human resources, network devices, budget, floor plans, inventory, and licenses. It aggregates data from existing IT Management tables into visual charts.

---

## 2. Key Tables

This module is read-only and aggregates data from:

- **equipment**
- **equipment_types**
- **tickets**
- **ticket_statuses**
- **employees**
- **departments**
- **annual_budgets**
- **monthly_budgets**
- **expenses** (for Actual vs Budget)
- **ops_report** / **ops_report_fb_outlet** (Hotel Operations charts)
- **gl_accounts**
- **budget_categories**
- **it_locations**
- **inventory_items**
- **license_management**
- **cost_centers** (linked to departments)

---

## 3. Required Relationships

- `annual_budgets.cost_center_id` -> `cost_centers.id` -> `departments.id` (Departmental budget tracking)
- `monthly_budgets.annual_budget_id` -> `annual_budgets.id` (Monthly trends)
- `expenses.gl_account_id` -> `gl_accounts.id` (Actual spend tracking)

---

## 4. Business Rules (Critical for Agents)

- Module access is controlled via `has_module_access($conn, $company_id, 'reports')`.
- All statistical queries must be scoped to the active `company_id`.
- Advanced Budgeting assumes current year (`YEAR(CURDATE())`) for most comparisons unless specified (e.g. YOY).
- `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` seeds Reports Hub demo rows for company 1: `ops_report` (2025 monthly anchors, Jun–Jul 2026 daily trend), `ops_report_fb_outlet` covers (Jul 2026 MTD), expanded `monthly_budgets` / `expenses`, and 2025 `annual_budgets` for YoY charts. Regression: `php scripts/verify_reports_hub.php`.

---

## 5. UI Behavior Requirements

- Uses **Chart.js** for data visualization.
- Responsive dashboard layout with stats cards and chart cards.
- Dark/Light theme support via `body` class.
- **UI configuration reviewed:** gate-excluded bespoke dashboard (`index.php` only) — no flattened CRUD table, CRUD entry files, or Settings list toolbar; all 16 `check_ui_configuration_coverage.php` list-contract checks registered in `scripts/data/ui_configuration_reviewed.json` as `[n/a][n/a][reviewed]`.

---

## 6. API Actions (If Applicable)

None

---

## 7. File Structure

- **index.php** — Main dashboard view and chart initialization.
- **api/helpers.php** — Data retrieval functions for different report categories.
- **../../css/reports/dashboard.css** — Custom styles for the reports hub.

---

## 8. Multi-Tenant Rules

- All data retrieval functions in `api/helpers.php` use the global `$company_id` to filter results.

---

## 9. Audit Logging Requirements

- This module is read-only; no INSERT/UPDATE/DELETE mutations occur.

---

## 10. Common Pitfalls

- **Argument mismatch:** `has_module_access()` requires 3 arguments (`$conn`, `$company_id`, `$module_slug`). [Cursor-Valid]
- **Path errors:** `itm_ensure_upload_directory_chain()` requires a string path, not an array. [Cursor-Valid]
- **SQL Scoping:** Ensure any new report helper correctly uses `$company_id` and prepared statements. [Cursor-Valid]

---

## 11. Examples of Safe Code Patterns

### Safe SELECT (Aggregation)

```php
$sql = "SELECT et.name, COUNT(*) as count
        FROM equipment e
        JOIN equipment_types et ON e.equipment_type_id = et.id
        WHERE e.company_id = ? AND e.active = 1
        GROUP BY et.name
        ORDER BY count DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    // ...
}
```

---

## 12. Module Owner Notes (Optional)

None
