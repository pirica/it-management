<?php
/**
 * Backfill search_index rows for command-palette phase 2 (dry-run default).
 *
 * CLI: php scripts/apply_search_index_backfill.php [--company=1] [--module=equipment]
 * Browser: scripts/apply_search_index_backfill.php?run=1 (dry-run) / ?run=1&apply=1 (Admin)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/../includes/itm_command_palette_search.php';
require_once __DIR__ . '/../includes/itm_search_index.php';

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/apply_search_index_backfill.php</code> — dry-run preview of <code>search_index</code> sync counts per tenant/module.<br>
<code>php scripts/apply_search_index_backfill.php --apply</code> — writes index rows (Admin required in browser with <code>?apply=1</code>).<br>
Optional: <code>--company=1</code> and <code>--module=equipment</code> (employees, equipment, tickets, ip_addresses, catalogs).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$boot = itm_apply_script_bootstrap('Search index backfill', [
    'usage_gate_title' => 'Search index backfill',
]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$conn = $boot['conn'];

if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

if (!itm_search_index_table_ready($conn)) {
    echo colorText('[FAIL] search_index table is missing — import db/ or apply db/migrations/search_index.sql.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$companyId = 0;
$moduleSlug = '';
$argvLocal = $boot['argv'] ?? [];
if (PHP_SAPI !== 'cli') {
    if (isset($_GET['company'])) {
        $argvLocal[] = '--company=' . (string)$_GET['company'];
    }
    if (isset($_GET['module'])) {
        $argvLocal[] = '--module=' . (string)$_GET['module'];
    }
}
foreach ($argvLocal as $arg) {
    if (strpos((string)$arg, '--company=') === 0) {
        $companyId = (int)substr((string)$arg, 10);
    } elseif (strpos((string)$arg, '--module=') === 0) {
        $moduleSlug = strtolower(trim(substr((string)$arg, 9)));
    }
}

$companyIds = [];
if ($companyId > 0) {
    $companyIds = [$companyId];
} else {
    $res = mysqli_query($conn, 'SELECT id FROM companies WHERE active = 1 ORDER BY id ASC');
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cid = (int)($row['id'] ?? 0);
        if ($cid > 0) {
            $companyIds[] = $cid;
        }
    }
    if ($res) {
        mysqli_free_result($res);
    }
}

if ($companyIds === []) {
    echo colorText('[FAIL] No active companies found to backfill.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$slugs = itm_command_palette_searchable_module_slugs();
if ($moduleSlug !== '') {
    if (!in_array($moduleSlug, $slugs, true)) {
        echo colorText('[FAIL] Unknown module slug: ' . $moduleSlug, 'fail') . $nl;
        itm_script_output_end();
        exit(1);
    }
    $slugs = [$moduleSlug];
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . ': search_index backfill' . $nl;
echo 'Companies: ' . implode(', ', $companyIds) . $nl;
echo 'Modules: ' . implode(', ', $slugs) . $nl . $nl;

$totalWouldSync = 0;
$totalSynced = 0;

foreach ($companyIds as $cid) {
    foreach ($slugs as $slug) {
        $sourceIds = itm_search_index_list_source_record_ids($conn, $slug, $cid);
        $count = count($sourceIds);
        $totalWouldSync += $count;
        echo '[INFO] company ' . $cid . ' / ' . $slug . ': ' . $count . ' live row(s)' . $nl;
        if ($apply && $count > 0) {
            $synced = itm_search_index_backfill_company($conn, $cid, $slug);
            $totalSynced += $synced;
            echo colorText('[PASS] Synced ' . $synced . ' row(s).', 'pass') . $nl;
        }
    }
}

if (!$apply) {
    echo $nl . colorText('[INFO] Would sync up to ' . $totalWouldSync . ' row(s). Re-run with --apply or ?apply=1.', 'info') . $nl;
} else {
    echo $nl . colorText('[PASS] Backfill complete — synced ' . $totalSynced . ' row(s).', 'pass') . $nl;
}

itm_script_output_end();
exit(0);
