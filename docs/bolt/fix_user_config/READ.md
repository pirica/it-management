# Optimization: Redundant Queries Removal in user-config.php

This optimization removes two redundant `SELECT` database queries that fetch properties (`workstation_mode_id` and `assignment_type_id`) that have already been retrieved in the initial employee profile query.

## Impact
- **Database round-trips saved:** 2 database round-trips per load of `user-config.php`.
- **Resources freed:** Eliminates statement handle leaks since the previous statements were not correctly closed.
- **Safety:** 100% safe as it retrieves the same properties directly from the existing `$current_user` array.
