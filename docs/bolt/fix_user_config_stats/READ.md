# ⚡ Optimization: Consolidated Stats Gathering in user-config.php

## 💡 What
Consolidating 31 separate `SELECT COUNT(*)` queries into a single database round-trip using a single `SELECT` statement with 31 subqueries.

## 🎯 Why
The original code executed one query per statistic in a `foreach` loop. This pattern, known as N+1 queries, results in high latency due to multiple network round-trips between the PHP application and the MySQL database server.

## 📊 Impact
- **Database round-trips:** Reduced from 31 to 1.
- **Latency:** Expected improvement of ~90% in stats gathering time.
- **Scalability:** Reduces overhead on the database server by minimizing connection and query parsing cycles.

## 🔬 Measurement
Run the provided benchmark script:
`php docs/bolt/fixed_files_user_config_stats/scripts/benchmark_stats_optimized.php`
