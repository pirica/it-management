<?php
/**
 * Benchmark for user-config.php redundant alerts/events query removal.
 *
 * Compares legacy 4 extra COUNT round-trips vs production: full dashboard batch +
 * itm_user_config_extract_alerts_events_counts() (no extra queries).
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_user_config_stats.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Benchmark: Redundant Queries');
$nl = itm_script_output_nl();

$user_id = (int)($_SESSION['employee_id'] ?? 1);
$company_id = (int)($_SESSION['company_id'] ?? 1);
$iterations = 100;
$redundant_defs = itm_user_config_redundant_stat_definitions();
$all_defs = itm_user_config_stat_definitions();
$redundant_count = count($redundant_defs);

echo colorText("Running benchmark for redundant alerts/events queries in user-config.php ($iterations iterations)...", 'info') . $nl;
echo "User ID: $user_id, Company ID: $company_id" . $nl;
echo "Legacy redundant stats: $redundant_count (alerts + events)" . $nl . $nl;

// --- 1. LEGACY: 4 separate COUNT queries per request ---
$legacyCounts = [];
$startLegacy = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $legacyRows = itm_user_config_fetch_stats_loop($conn, $redundant_defs, $user_id, $company_id);
    $legacyCounts = itm_user_config_redundant_stat_counts_from_rows($legacyRows);
}
$endLegacy = microtime(true);
$legacyTime = $endLegacy - $startLegacy;
echo "Legacy redundant loop ($redundant_count queries/iter): " . number_format($legacyTime, 4) . "s" . $nl;

// --- 2. PRODUCTION: full dashboard batch + PHP extract (no extra queries) ---
$productionCounts = [];
$startProduction = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $all_stats = itm_user_config_fetch_stats_batch($conn, $all_defs, $user_id, $company_id);
    $productionCounts = itm_user_config_extract_alerts_events_counts($all_stats);
}
$endProduction = microtime(true);
$productionTime = $endProduction - $startProduction;
echo "Production batch (" . count($all_defs) . " stats) + extract/iter: " . number_format($productionTime, 4) . "s" . $nl;

// Extraction-only cost (batch already paid for elsewhere on the page)
$startExtractOnly = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    itm_user_config_extract_alerts_events_counts($all_stats);
}
$extractOnlyTime = microtime(true) - $startExtractOnly;
echo "Extract-only ($iterations iters, batch reused): " . number_format($extractOnlyTime, 6) . "s" . $nl;

$roundTripsSavedPerRequest = $redundant_count;
$legacyPerIterMs = ($legacyTime / $iterations) * 1000;
$extractPerIterMs = ($extractOnlyTime / $iterations) * 1000;
$savingsVsLegacy = (($legacyTime - $extractOnlyTime) / max(0.001, $legacyTime)) * 100;

echo $nl . "Per-request legacy redundant queries: $roundTripsSavedPerRequest MySQL round-trips" . $nl;
echo "Per-iteration avg: legacy loop " . number_format($legacyPerIterMs, 2) . "ms vs extract-only " . number_format($extractPerIterMs, 4) . "ms" . $nl;
echo "Round-trip savings (legacy loop vs extract): " . number_format($savingsVsLegacy, 2) . "%" . $nl;
echo "Note: production already runs the " . count($all_defs) . "-stat batch for the dashboard; extract adds no extra queries." . $nl;

$match = ($legacyCounts === $productionCounts);
if (!$match) {
    echo $nl . "Legacy counts:" . $nl;
    print_r($legacyCounts);
    echo "Production counts:" . $nl;
    print_r($productionCounts);
}

if ($match) {
    echo itm_script_format_status_line('[PASS] Legacy redundant loop matches production batch + extract counts.') . $nl;
} else {
    echo itm_script_format_status_line('[FAIL] Count mismatch between legacy loop and production extract.') . $nl;
    itm_script_output_end();
    exit(1);
}

if ($extractOnlyTime < $legacyTime) {
    echo itm_script_format_status_line('[PASS] Extract-only is faster than legacy redundant loop (expected).') . $nl;
} else {
    echo itm_script_format_status_line('[FAIL] Extract-only was not faster than legacy loop — investigate environment.') . $nl;
    itm_script_output_end();
    exit(1);
}

itm_script_output_end();
