<?php
/**
 * Benchmark for user-config.php redundant query removal.
 * Compares the performance of individual queries vs. consolidated query results.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Benchmark: Redundant Queries');
$nl = itm_script_output_nl();

// Why: Use session user/company if available, otherwise default to Admin for benchmark.
$user_id = (int)($_SESSION['employee_id'] ?? 1);
$company_id = (int)($_SESSION['company_id'] ?? 1);
$iterations = 100;

echo colorText("Running benchmark for redundant queries in user-config.php ($iterations iterations)...", 'info') . $nl;
echo "User ID: $user_id, Company ID: $company_id" . $nl . $nl;

// --- 1. REDUNDANT INDIVIDUAL QUERIES ---
$startRedundant = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    // Events for me
    $stmt = mysqli_prepare($conn, "SELECT COUNT(assigned_to_employee_id) FROM events WHERE assigned_to_employee_id = ? AND company_id = ? AND active = 1");
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_events_forme);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Events created
    $stmt = mysqli_prepare($conn, "SELECT COUNT(created_by_employee_id) FROM events WHERE created_by_employee_id = ? AND company_id = ? AND active = 1");
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_events_created);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Alerts for me
    $stmt = mysqli_prepare($conn, "SELECT COUNT(assigned_to_employee_id) FROM alerts WHERE assigned_to_employee_id = ? AND company_id = ? AND active = 1");
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_alerts_forme);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Alerts created
    $stmt = mysqli_prepare($conn, "SELECT COUNT(created_by_employee_id) FROM alerts WHERE created_by_employee_id = ? AND company_id = ? AND active = 1");
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_alerts_created);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}
$endRedundant = microtime(true);
$redundantTime = $endRedundant - $startRedundant;
echo "Redundant Individual Queries (4 queries): " . number_format($redundantTime, 4) . "s" . $nl;

// --- 2. CONSOLIDATED QUERY RESULTS ---
$stat_definitions = [
    ['table' => 'alerts', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Alerts', 'slug' => 'alerts'],
    ['table' => 'alerts', 'field' => 'created_by_employee_id', 'label' => 'Created Alerts', 'slug' => 'alerts'],
    ['table' => 'events', 'field' => 'assigned_to_employee_id', 'label' => 'Events for Me', 'slug' => 'events'],
    ['table' => 'events', 'field' => 'created_by_employee_id', 'label' => 'Events Created', 'slug' => 'events'],
];

$startConsolidated = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $all_stats = [];
    $subqueries = [];
    foreach ($stat_definitions as $index => $def) {
        $subqueries[] = "(SELECT COUNT(*) FROM `" . $def['table'] . "` WHERE `" . $def['field'] . "` = ? AND `company_id` = ? AND `active` = 1) AS stat_" . $index;
    }
    $sql = "SELECT " . implode(", ", $subqueries);
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $types = str_repeat('ii', count($stat_definitions));
        $params = [];
        foreach($stat_definitions as $def) {
            $params[] = $user_id;
            $params[] = $company_id;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $counts = itm_mysqli_stmt_fetch_assoc($stmt);
        if (is_array($counts)) {
            foreach ($stat_definitions as $index => $def) {
                $all_stats[] = array_merge($def, ['count' => (int)($counts['stat_' . $index] ?? 0)]);
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Extraction (negligible)
    $total_alerts_forme_opt = 0;
    $total_alerts_created_opt = 0;
    $total_events_forme_opt = 0;
    $total_events_created_opt = 0;

    foreach ($all_stats as $s) {
        if ($s['table'] === 'alerts' && $s['field'] === 'assigned_to_employee_id') $total_alerts_forme_opt = $s['count'];
        if ($s['table'] === 'alerts' && $s['field'] === 'created_by_employee_id') $total_alerts_created_opt = $s['count'];
        if ($s['table'] === 'events' && $s['field'] === 'assigned_to_employee_id') $total_events_forme_opt = $s['count'];
        if ($s['table'] === 'events' && $s['field'] === 'created_by_employee_id') $total_events_created_opt = $s['count'];
    }
}
$endConsolidated = microtime(true);
$consolidatedTime = $endConsolidated - $startConsolidated;
echo "Consolidated Single Query (1 query): " . number_format($consolidatedTime, 4) . "s" . $nl;

$reduction = (($redundantTime - $consolidatedTime) / max(0.001, $redundantTime)) * 100;
echo $nl . colorText("Performance Improvement: " . number_format($reduction, 2) . "%", 'pass') . $nl;
echo "Reduction in Database Round-trips: 4 per request -> 1 per request (for these 4 stats)." . $nl;
echo "Total round-trip savings across entire user-config.php stats: 31 separate queries -> 1 consolidated query." . $nl;
