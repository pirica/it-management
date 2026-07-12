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

$options = $isCli ? getopt('', ['company:', 'employee:', 'iterations:', 'checks:']) : [];
$companyId = isset($options['company']) ? (int)$options['company'] : (int)($_GET['company'] ?? 1);
$employeeId = isset($options['employee']) ? (int)$options['employee'] : (int)($_GET['employee'] ?? 1);
$iterations = isset($options['iterations']) ? max(1, (int)$options['iterations']) : (int)($_GET['iterations'] ?? 3);
$journalChecks = isset($options['checks']) ? max(1, (int)$options['checks']) : (int)($_GET['checks'] ?? 100);

$maxFullQueries = (int)(getenv('ITM_BSMA_MAX_FULL_QUERIES') ?: 45);
$minReductionPct = (float)(getenv('ITM_BSMA_MIN_REDUCTION_PCT') ?: 50.0);
$journalAccessOptimizedMax = (int)(getenv('ITM_BSMA_JOURNAL_ACCESS_OPTIMIZED_MAX') ?: 5);
$journalAccessLegacyMin = (int)(getenv('ITM_BSMA_JOURNAL_ACCESS_LEGACY_MIN') ?: 150);
$journalStructureOptimizedMax = (int)(getenv('ITM_BSMA_JOURNAL_STRUCTURE_OPTIMIZED_MAX') ?: 15);
$journalTimingMinPct = (float)(getenv('ITM_BSMA_JOURNAL_TIMING_MIN_PCT') ?: 50.0);

echo colorText('Sidebar module-access benchmark (MySQL Questions counter)', 'info') . $nl;
echo 'company_id=' . $companyId . ', employee_id=' . $employeeId . ', iterations=' . $iterations . $nl;
echo 'PASS thresholds: optimized full path <= ' . $maxFullQueries . ' queries; legacy vs optimized reduction >= '
    . $minReductionPct . '% on combined legacy estimate.' . $nl;
echo 'BOLT journal component checks use ' . $journalChecks . ' mocked has_module_access iterations.' . $nl . $nl;

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

$structureOnly = itm_bsma_run_optimized_structure_only($conn);
$accessOptimized = itm_bsma_run_optimized_access_only($conn, $companyId, $employeeId, $moduleSlugs, $journalChecks);
$accessLegacy = itm_bsma_run_legacy_access_only($conn, $companyId, $employeeId, $moduleSlugs, $journalChecks);

if ($structureOnly === null || $accessOptimized === null || $accessLegacy === null) {
    echo colorText('[FAIL] Unable to read Questions counter during BOLT journal component checks.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$legacyStructureLegacy = (int)$structureOnly['queries'] + max(0, $legacyEnsureQueries - 1);
$legacyFullEstimate = $legacyFilterQueries + $legacyStructureLegacy;
$accessTimingPct = 0.0;
if ($accessLegacy['elapsed_ms'] > 0) {
    $accessTimingPct = round((1 - ($accessOptimized['elapsed_ms'] / (float)$accessLegacy['elapsed_ms'])) * 100, 1);
}

echo colorText('--- BOLT journal claim verification ---', 'info') . $nl;
echo 'Reference journal (19-06-2026): full sidebar ~417→~7 queries; has_module_access ×100 ~200→~2; '
    . 'itm_sidebar_structure ~171→~6; timing ~0.057s→~0.014s (~75% faster).' . $nl . $nl;

$journalRows = [
    [
        'label' => 'Full sidebar path (optimized median)',
        'actual' => $optimizedMedianQueries,
        'claimed' => 7,
        'tolerance' => 18,
    ],
    [
        'label' => 'Full sidebar legacy estimate (filter+ensure+structure)',
        'actual' => $legacyFullEstimate,
        'claimed' => 417,
        'tolerance' => 150,
    ],
    [
        'label' => 'has_module_access ×' . $journalChecks . ' (optimized)',
        'actual' => (int)$accessOptimized['queries'],
        'claimed' => 2,
        'tolerance' => 3,
    ],
    [
        'label' => 'has_module_access ×' . $journalChecks . ' (legacy uncached registry+admin)',
        'actual' => (int)$accessLegacy['queries'],
        'claimed' => 200,
        'tolerance' => 80,
    ],
    [
        'label' => 'itm_sidebar_structure only (optimized)',
        'actual' => (int)$structureOnly['queries'],
        'claimed' => 6,
        'tolerance' => 9,
    ],
];

foreach ($journalRows as $journalRow) {
    $match = itm_bsma_journal_claim_match((int)$journalRow['actual'], (int)$journalRow['claimed'], (int)$journalRow['tolerance']);
    $tag = $match ? 'MATCH' : 'DIFFERS';
    $type = $match ? 'pass' : 'warn';
    echo colorText(
        sprintf(
            '[%s] %s: measured=%d (journal ~%d, ±%d)',
            $tag,
            $journalRow['label'],
            $journalRow['actual'],
            $journalRow['claimed'],
            $journalRow['tolerance']
        ),
        $type
    ) . $nl;
}

echo sprintf(
    '[INFO] Access-only timing (%d checks): optimized=%.3fs, legacy=%.3fs, reduction=%.1f%% (journal ~75%%)' . $nl,
    $journalChecks,
    $accessOptimized['elapsed_ms'] / 1000,
    $accessLegacy['elapsed_ms'] / 1000,
    $accessTimingPct
) . $nl;

if ((int)$accessOptimized['queries'] > $journalAccessOptimizedMax) {
    echo colorText(
        '[FAIL] Optimized has_module_access ×' . $journalChecks . ' used '
        . $accessOptimized['queries'] . ' queries (expected <= ' . $journalAccessOptimizedMax . ').',
        'fail'
    ) . $nl;
    $failures++;
} else {
    echo colorText(
        '[PASS] Optimized has_module_access ×' . $journalChecks . ' used '
        . $accessOptimized['queries'] . ' queries (<= ' . $journalAccessOptimizedMax . ').',
        'pass'
    ) . $nl;
}

if ((int)$accessLegacy['queries'] < $journalAccessLegacyMin) {
    echo colorText(
        '[FAIL] Legacy has_module_access ×' . $journalChecks . ' used '
        . $accessLegacy['queries'] . ' queries (expected >= ' . $journalAccessLegacyMin . ').',
        'fail'
    ) . $nl;
    $failures++;
} else {
    echo colorText(
        '[PASS] Legacy has_module_access ×' . $journalChecks . ' used '
        . $accessLegacy['queries'] . ' queries (>= ' . $journalAccessLegacyMin . ').',
        'pass'
    ) . $nl;
}

if ((int)$structureOnly['queries'] > $journalStructureOptimizedMax) {
    echo colorText(
        '[FAIL] Optimized itm_sidebar_structure used '
        . $structureOnly['queries'] . ' queries (expected <= ' . $journalStructureOptimizedMax . ').',
        'fail'
    ) . $nl;
    $failures++;
} else {
    echo colorText(
        '[PASS] Optimized itm_sidebar_structure used '
        . $structureOnly['queries'] . ' queries (<= ' . $journalStructureOptimizedMax . ').',
        'pass'
    ) . $nl;
}

if ($accessTimingPct + 0.001 < $journalTimingMinPct) {
    echo colorText(
        '[FAIL] Access-only timing reduction ' . $accessTimingPct . '% is below '
        . $journalTimingMinPct . '% (journal ~75%).',
        'fail'
    ) . $nl;
    $failures++;
} else {
    echo colorText(
        '[PASS] Access-only timing reduction ' . $accessTimingPct . '% meets minimum '
        . $journalTimingMinPct . '%.',
        'pass'
    ) . $nl;
}

echo $nl;
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
