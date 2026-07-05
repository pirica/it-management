<?php
/**
 * Optimization logic for user-config.php
 * This script describes the changes to be applied.
 */

$optimization = [
    'title' => 'Redundant Query Consolidation in user-config.php',
    'description' => 'Removed 4 redundant individual database queries for events and alerts counts that were already being gathered in a consolidated query. Optimized the consolidated query to include mandatory company_id scoping and active status filtering where required.',
    'impact' => 'Reduces database round-trips by 4 per dashboard load. Ensures consistent data scoping and filtering across all stats.',
    'steps' => [
        '1. Remove redundant query blocks for $total_events_forme, $total_events_created, $total_alerts_forme, and $total_alerts_created (lines 62-128).',
        '2. Update the consolidated stats gathering block (starting around line 297) to include `company_id` and `active` filters in subqueries.',
        '3. Extract the counts for events and alerts from the `$all_stats` array to maintain compatibility with the UI variables.',
        '4. Ensure all variables used in the UI are properly initialized from the optimized stats array.'
    ]
];

echo json_encode($optimization, JSON_PRETTY_PRINT);
