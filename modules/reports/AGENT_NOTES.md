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
- **scheduled_reports** (admin-managed email schedules; see `docs/SCHEDULED_REPORTS.md`)
- **saved_report_views** (user-saved list filters/columns; **My reports** section on index)

**Reports Hub charts (phase 2):**

- **Ticket CSAT trend** — `get_ticket_csat_trend()` (12-month average `tickets.csat_score`)
- **Survey response rate** — `get_ticket_survey_response_rate_trend()` (12-month issued vs completed bar chart `#surveyResponseRateChart` on index)
- **Survey question averages** — `get_ticket_survey_question_averages()` (default questionnaire rating averages, last 90 days; HTML table on index)
- **Ticket survey KPIs** — dedicated dashboard [modules/ticket_survey_dashboard/](http://localhost/it-management/modules/ticket_survey_dashboard/index.php) (`itm_ticket_survey_stats_aggregate()`; `docs/TICKET_SURVEYS.md`)
- **Asset lifecycle stages** — `get_asset_lifecycle_stage_summary()` (equipment `lifecycle_stage` counts; see `docs/ASSET_LIFECYCLE.md`)
- **Upcoming maintenance forecast** — `get_upcoming_maintenance_forecast()` includes warranty, certificate, license, and **EOL** (`includes/itm_software_eol.php`; see `docs/SOFTWARE_EOL.md`)
- **Problem Management** — `get_problem_management_summary()` (`problems` status doughnut + linked incident / closed-this-month counts; see `docs/PROBLEM_MANAGEMENT.md`)
- **CAPEX vs OPEX (Financial Performance)** — `get_capex_opex_annual_budget_split()`, `get_capex_opex_budget_vs_actual()`, `get_capex_opex_monthly_actual_trend()` — `category_kind` split; chart year = latest `annual_budgets.year`; links to [CAPEX](http://localhost/it-management/modules/capex/index.php) / [OPEX](http://localhost/it-management/modules/opex/index.php) in card copy.

---

## Scheduled executive reports

- **Table:** `scheduled_reports` — tenant-scoped cron schedules (`report_slug`, five-field `schedule_cron`, `recipients_json`, `format` `pdf`|`xlsx`, `last_sent_at`, `enabled`).
- **UI:** [modules/reports/index.php](http://localhost/it-management/modules/reports/index.php) — admins only (`itm_is_admin($conn, $employee_id)`). Modal POST actions `save_scheduled_report` / `delete_scheduled_report`.
- **Runner:** `includes/itm_scheduled_reports.php`; cron `php scripts/run_scheduled_reports.php`.
- **Verify:** `php scripts/verify_scheduled_reports.php` — [browser](http://localhost/it-management/scripts/verify_scheduled_reports.php?run=1).
- **Saved views:** schedule dropdown and email runner also accept `report_slug` `saved_view:{id}` (links `scheduled_reports.saved_view_id`).

## My reports (saved list views)

- **Table:** `saved_report_views` — per-user filter/column JSON for `tickets`, `equipment`, `expenses`; `shared_scope` private | department | company.
- **Save UI:** 💾 control on those modules’ list search rows (`includes/itm_saved_reports_ui.php`).
- **Hub section:** `#my-saved-reports` on [modules/reports/index.php](http://localhost/it-management/modules/reports/index.php) — open list, JSON run, export Excel/PDF, edit (owner), share link, schedule email (owner), delete (owner); owner schedules table below list.
- **Export:** [saved_report_views/export.php?id=&format=xlsx|pdf](http://localhost/it-management/modules/saved_report_views/export.php?id=1&format=xlsx) (session).
- **Public share:** [saved_report_views/join.php?t=](http://localhost/it-management/modules/saved_report_views/join.php) (token from `api.php?action=share`).
- **Dashboard widget:** [dashboard.php](http://localhost/it-management/dashboard.php) — Saved reports section (`itm_saved_reports_dashboard_snapshot()`).
- **API:** [saved_report_views/api.php?action=run&id=](http://localhost/it-management/modules/saved_report_views/api.php?action=run&id=1) (session + rate limit).
- **Verify:** `php scripts/verify_saved_report_views.php` — [browser](http://localhost/it-management/scripts/verify_saved_report_views.php?run=1).

---

## 3. Required Relationships

- `annual_budgets.cost_center_id` -> `cost_centers.id` -> `departments.id` (Departmental budget tracking)
- `monthly_budgets.annual_budget_id` -> `annual_budgets.id` (Monthly trends)
- `expenses.gl_account_id` -> `gl_accounts.id` (Actual spend tracking)

---

## 4. Business Rules (Critical for Agents)

- Module access is controlled via `has_module_access($conn, $company_id, 'reports')`.
- All statistical queries must be scoped to the active `company_id`.
- Advanced Budgeting uses `YEAR(CURDATE())` for legacy budget vs actual / department / YoY charts. **CAPEX/OPEX** hub charts use `reports_resolve_budget_chart_year()` (latest `annual_budgets.year`, aligned with CAPEX/OPEX report modules).
- `db/` seeds Reports Hub demo rows for company 1: `ops_report` (2025 monthly anchors, Jun–Jul 2026 daily trend), `ops_report_fb_outlet` covers (Jul 2026 MTD), expanded `monthly_budgets` / `expenses`, and 2025 `annual_budgets` for YoY charts. Regression: `php scripts/verify_reports_hub.php`.

---

## 5. UI Behavior Requirements

- Uses **Chart.js** for data visualization.
- Responsive dashboard layout with stats cards and chart cards.
- Dark/Light theme support via `body` class.
- **Locale display (Settings → UI Configuration):** money insight cards and Chart.js financial axes/tooltips use `itm_ui_locale_format_money_display()` / `itm_ui_locale_chart_money_format_payload()` (symbol suffix/prefix from `ui_configuration`). Chart date labels in `api/helpers.php` use `itm_ui_locale_format_month_short_labels()`, `itm_ui_locale_format_year_month_chart_label()`, `itm_ui_locale_format_chart_day_label()`, and `itm_ui_locale_format_month_full_label()`. Scheduled report **Last sent** uses `itm_format_cell_scalar_display()` (datetime locale).
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

- Canonical saved-views doc: `docs/SAVED_REPORT_VIEWS.md`
- Regression: `php scripts/verify_reports_hub.php` when changing chart helpers; `php scripts/verify_saved_report_views.php` when changing **My reports** integration.
