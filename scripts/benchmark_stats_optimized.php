<?php
/**
 * Benchmark for user-config.php stats gathering optimization.
 * Compares N individual COUNT queries vs 1 consolidated query (same SQL/filters).
 */

define('ITM_CLI_SCRIPT', true);
require __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_user_config_stats.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Benchmark: user-config.php stats optimization');
$nl = itm_script_output_nl();

$user_id = 1;
$company_id = 1;
$stat_definitions = itm_user_config_stat_definitions();
$stat_count = count($stat_definitions);

$iterations = 10;
echo "Running benchmarks ($iterations iterations, $stat_count stats each)..." . $nl . $nl;

// Why: Compare loop vs batch back-to-back before timed runs — audit_logs grow between phases.
$verify_stats_original = itm_user_config_fetch_stats_loop($conn, $stat_definitions, $user_id, $company_id);
$verify_stats_optimized = itm_user_config_fetch_stats_batch($conn, $stat_definitions, $user_id, $company_id);

// --- 1. ORIGINAL PATTERN (loop of prepared COUNT queries) ---
$startOriginal = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    itm_user_config_fetch_stats_loop($conn, $stat_definitions, $user_id, $company_id);
}
$endOriginal = microtime(true);
$originalTime = $endOriginal - $startOriginal;
echo "Original Loop ($stat_count queries/iter): " . number_format($originalTime, 4) . "s" . $nl;

// --- 2. OPTIMIZED PATTERN (single consolidated query) ---
$startOptimized = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    itm_user_config_fetch_stats_batch($conn, $stat_definitions, $user_id, $company_id);
}
$endOptimized = microtime(true);
$optimizedTime = $endOptimized - $startOptimized;
echo "Optimized Single Query (1 round-trip/iter): " . number_format($optimizedTime, 4) . "s" . $nl;

// --- VERIFICATION ---
$reduction = (($originalTime - $optimizedTime) / max(0.001, $originalTime)) * 100;
$perIterOriginalMs = ($originalTime / $iterations) * 1000;
$perIterOptimizedMs = ($optimizedTime / $iterations) * 1000;
$roundTripsSaved = ($stat_count - 1) * $iterations;

echo $nl . "Per-iteration avg: loop " . number_format($perIterOriginalMs, 2) . "ms vs batch " . number_format($perIterOptimizedMs, 2) . "ms" . $nl;
echo "MySQL round-trips saved ($iterations iters): $roundTripsSaved (" . $stat_count . " → 1 per iteration)" . $nl;
echo "Performance Improvement: " . number_format($reduction, 2) . "%" . $nl;

$match = true;
if (count($verify_stats_original) !== $stat_count) {
    echo "Original returned " . count($verify_stats_original) . " stats (expected $stat_count)." . $nl;
    $match = false;
}
if (count($verify_stats_optimized) !== $stat_count) {
    echo "Optimized returned " . count($verify_stats_optimized) . " stats (expected $stat_count)." . $nl;
    $match = false;
}
if ($match) {
    foreach ($verify_stats_original as $index => $stat) {
        if ($stat['count'] !== $verify_stats_optimized[$index]['count']) {
            echo "Mismatch at index $index (" . $stat['label'] . " / " . $stat['table'] . '.' . $stat['field'] . "): "
                . $stat['count'] . " vs " . $verify_stats_optimized[$index]['count'] . $nl;
            $match = false;
        }
    }
}

if (!$match) {
    echo itm_script_format_status_line("[FAIL] Results mismatch detected!") . $nl;
    itm_script_output_end();
    exit(1);
}

if ($optimizedTime < $originalTime) {
    echo itm_script_format_status_line("[PASS] Results matched; batch is faster than loop.") . $nl;
} else {
    echo itm_script_format_status_line("[PASS] Results matched (batch not faster under current load — timing advisory only).") . $nl;
}

itm_script_output_end();
