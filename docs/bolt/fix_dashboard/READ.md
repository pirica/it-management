# ⚡ BOLT Optimization: Dashboard Query Consolidation

## 🔍 PROFILE
The `dashboard.php` file currently performs 5 separate count queries to display statistics for Equipment, Tickets, Employees, Active Employees, and Employees On Leave. Additionally, it performs redundant `information_schema` checks for column existence.

- **Baseline Query Count**: 8 (including schema checks)
- **Baseline Execution Time**: ~0.047s

## 💡 SELECT
Consolidate all statistic counts into a single SQL query using subqueries. This reduces database round-trips from 5+ to 1.

## 🚀 OPTIMIZE
- Replace individual `fetch_company_count` calls with a single consolidated SELECT.
- Remove redundant `table_has_column` checks for `equipment.active` since the schema is known to contain it in this version.
- Use a single prepared statement for all counts.

## 🔬 VERIFY
- Measurement: `docs/bolt/fixed_files_performance_dashboard/scripts/benchmark_dashboard_updated.php`
- Expected Impact: Query count reduced to 1 for statistics; ~20-30% reduction in dashboard initialization time.
