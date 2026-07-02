# Optimization: Switch Port Manager loading logic

## Summary
The loading logic for the Switch Port Manager in `modules/equipment/index.php` was optimized to reduce database load and improve page responsiveness.

## Changes
- Replaced the heavy query that fetched all switch details for every page load with a lightweight query fetching only `id` and `name`.
- Added a focused query to fetch full technical details only for the selected switch when the Switch Port Manager UI is active.
- Ensured the list of switches for the picker remains available without the overhead of multiple JOINs and technical column retrieval for non-selected switches.

## Verification
- Baseline (unoptimized): ~29 ms for 20 switches.
- Optimized: ~2 ms for 20 switches (SPM inactive).
- Regression testing: UI remains functional, switch picker works as expected, and switch details load correctly when "Switch Port Manager" button is clicked.
