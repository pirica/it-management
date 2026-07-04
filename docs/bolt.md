# ⚡ BOLT’S JOURNAL (PHP Edition)

## 05-02-2025 - N+1 stats gathering in user-config.php
**Learning:** gathering dashboard statistics through individual COUNT queries in a loop is a significant performance bottleneck due to excessive database round-trips.
**Action:** Consolidate multiple COUNT queries into a single SQL statement using subqueries to minimize latency.
