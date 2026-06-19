<?php
/**
 * Benchmark sidebar module-access query count and timing.
 *
 * Compares the live sidebar path (structure + has_module_access filter) against an
 * uncached legacy N+1 simulation. Read-only — no schema or CMA mutations.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_benchmark_sidebar_access.php';

itm_script_output_begin('Sidebar Module Access Benchmark');

$nl = itm_script_output_nl();
$isCli = itm_script_cli_is_cli();
$failures = 0;

if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] Database connection is required.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

if (!function_exists('has_module_access') || !function_exists('itm_sidebar_structure')) {
    echo colorText('[FAIL] Required sidebar helpers are not loaded.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$options = $isCli ? getopt('', ['company:', 'employee:', 'iterations:']) : [];
$companyId = isset($options['company']) ? (int)$options['company'] : (int)($_GET['company'] ?? 1);
$employeeId = isset($options['employee']) ? (int)$options['employee'] : (int)($_GET['employee'] ?? 1);
$iterations = isset($options['iterations']) ? max(1, (int)$options['iterations']) : (int)($_GET['iterations'] ?? 3);

$maxFullQueries = (int)(getenv('ITM_BSMA_MAX_FULL_QUERIES') ?: 45);
$minReductionPct = (float)(getenv('ITM_BSMA_MIN_REDUCTION_PCT') ?: 50.0);

echo colorText('Sidebar module-access benchmark (MySQL Questions counter)', 'info') . $nl;
echo 'company_id=' . $companyId . ', employee_id=' . $employeeId . ', iterations=' . $iterations . $nl;
echo 'PASS thresholds: optimized full path <= ' . $maxFullQueries . ' queries; legacy vs optimized reduction >= '
    . $minReductionPct . '% on combined legacy estimate.' . $nl . $nl;

// Why: Warm catalog once outside timed legacy measurements so slug lists match production sidebar.
itm_sidebar_structure($conn, true);
$moduleSlugs = itm_bsma_sidebar_module_slugs_for_filter($conn);
$slugCount = count($moduleSlugs);
echo 'Sidebar module slugs to filter (excluding dashboard/settings): ' . $slugCount . $nl . $nl;

$optimizedQueries = [];
$optimizedMs = [];
for ($i = 0; $i < $iterations; $i++) {
    $result = itm_bsma_run_optimized_sidebar_path($conn, $companyId, $employeeId);
    if ($result === null) {
        echo colorText('[FAIL] Unable to read Questions counter during optimized run.', 'fail') . $nl;
        itm_script_output_end();
        exit(1);
    }
    $optimizedQueries[] = (int)$result['queries'];
    $optimizedMs[] = (float)$result['elapsed_ms'];
    echo sprintf(
        '[INFO] Optimized run %d: %d queries, %.2f ms, %d modules checked, %d sections' . $nl,
        $i + 1,
        $result['queries'],
        $result['elapsed_ms'],
        $result['modules_checked'],
        $result['sections']
    );
}

$legacyFilter = itm_bsma_run_legacy_access_filter($conn, $companyId, $employeeId, $moduleSlugs);
if ($legacyFilter === null) {
    echo colorText('[FAIL] Unable to read Questions counter during legacy filter simulation.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$legacyEnsure = itm_bsma_simulate_legacy_registry_ensure($conn, $moduleSlugs);
if ($legacyEnsure === null) {
    echo colorText('[FAIL] Unable to read Questions counter during legacy registry ensure simulation.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$optimizedMedianQueries = (int)round(itm_bsma_median($optimizedQueries));
$optimizedMedianMs = itm_bsma_median($optimizedMs);
$legacyFilterQueries = (int)$legacyFilter['queries'];
$legacyEnsureQueries = (int)$legacyEnsure['queries'];
$legacyCombinedEstimate = $legacyFilterQueries + $legacyEnsureQueries;

echo $nl . colorText('--- Summary ---', 'info') . $nl;
echo sprintf(
    'Optimized full sidebar path (median of %d runs): %d queries, %.2f ms' . $nl,
    $iterations,
    $optimizedMedianQueries,
    $optimizedMedianMs
);
echo sprintf(
    'Legacy access filter simulation (%d modules): %d queries, %.2f ms' . $nl,
    $legacyFilter['modules_checked'],
    $legacyFilterQueries,
    $legacyFilter['elapsed_ms']
);
echo sprintf(
    'Legacy registry ensure simulation (%d slug lookups): %d queries, %.2f ms' . $nl,
    $legacyEnsure['slug_lookups'],
    $legacyEnsureQueries,
    $legacyEnsure['elapsed_ms']
);
echo 'Legacy combined estimate (filter + ensure): ' . $legacyCombinedEstimate . ' queries' . $nl;

$reductionPct = 0.0;
if ($legacyCombinedEstimate > 0) {
    $reductionPct = round((1 - ($optimizedMedianQueries / $legacyCombinedEstimate)) * 100, 1);
}
echo 'Query reduction (optimized vs legacy combined estimate): ' . $reductionPct . '%' . $nl;

$speedupPct = 0.0;
if ($legacyFilter['elapsed_ms'] > 0) {
    $speedupPct = round((1 - ($optimizedMedianMs / (float)$legacyFilter['elapsed_ms'])) * 100, 1);
}
echo 'Timing note: optimized full path median ' . $optimizedMedianMs . ' ms vs legacy filter-only '
    . $legacyFilter['elapsed_ms'] . ' ms (~' . $speedupPct . '% faster on filter portion; structure included in optimized only).' . $nl . $nl;

if ($optimizedMedianQueries > $maxFullQueries) {
    echo colorText(
        '[FAIL] Optimized full path median ' . $optimizedMedianQueries
        . ' queries exceeds ITM_BSMA_MAX_FULL_QUERIES=' . $maxFullQueries . '.',
        'fail'
    ) . $nl;
    if (!function_exists('itm_has_module_access_bust_cache')) {
        echo colorText(
            '[WARN] Prefetch cache helpers missing — merge includes/itm_company_module_access.php sidebar cache before expecting ~7–25 queries.',
            'warn'
        ) . $nl;
    }
    $failures++;
} else {
    echo colorText(
        '[PASS] Optimized full path median ' . $optimizedMedianQueries . ' queries (<= ' . $maxFullQueries . ').',
        'pass'
    ) . $nl;
}

if ($reductionPct + 0.001 < $minReductionPct) {
    echo colorText(
        '[FAIL] Query reduction ' . $reductionPct . '% is below ITM_BSMA_MIN_REDUCTION_PCT=' . $minReductionPct . '.',
        'fail'
    ) . $nl;
    $failures++;
} else {
    echo colorText(
        '[PASS] Query reduction ' . $reductionPct . '% meets minimum ' . $minReductionPct . '%.',
        'pass'
    ) . $nl;
}

if ($legacyFilterQueries < max(10, $slugCount)) {
    echo colorText(
        '[WARN] Legacy filter query count looks low for ' . $slugCount . ' slugs — verify benchmark environment.',
        'warn'
    ) . $nl;
}

echo $nl . colorText(
    $failures === 0
        ? '[PASS] Sidebar module-access benchmark complete.'
        : '[FAIL] Sidebar module-access benchmark failed one or more thresholds.',
    $failures === 0 ? 'pass' : 'fail'
) . $nl;

itm_script_output_end();
exit($failures === 0 ? 0 : 1);
