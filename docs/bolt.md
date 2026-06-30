# 📓 BOLT’S JOURNAL (PHP Edition)

## 18-06-2026 - Dashboard Query Consolidation
**Learning:** The dashboard was performing 5+ separate count queries for statistics, each incurring a network round-trip. Additionally, it was querying `information_schema.COLUMNS` to check for column existence on every request, which is unexpectedly expensive in MySQL when called repeatedly.
**Action:** Consolidate multiple counts into a single SQL query using subqueries. This reduces round-trips to 1 and eliminates the need for repeated schema metadata checks.
