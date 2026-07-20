<?php
/**
 * Build db/02_data_sample.sql from db/02_data.sql (company 1 markers) and live MySQL.
 *
 * Every tenant-scoped table (schema company_id, minus exempt list) must end with at least
 * one template row. Parsed db/02_data.sql rows are preferred; missing tables backfill from
 * MySQL company 1 (or first available tenant row rewritten to company 1 marker).
 *
 * Browser + CLI; dry-run by default. Writes with --apply or browser ?apply=1 (Admin).
 */
define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$ctx = itm_apply_script_bootstrap('Extract 02_data_sample.sql', ['skip_db_tests' => false]);
$apply = $ctx['apply'];
$nl = $ctx['nl'];

$templateCompanyId = defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')
    ? (int)ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID
    : 1;

require_once dirname(__DIR__) . '/includes/itm_sample_sql_export.php';

$targetPath = itm_database_sql_sample_path();
$dbConn = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
$collection = itm_sample_sql_collect_table_templates($dbConn, $templateCompanyId);
$rowsByTable = $collection['rows_by_table'];
$missingTables = $collection['missing_tables'];
$dbBackfilled = $collection['db_backfilled'];
$synthesized = $collection['synthesized'] ?? [];

$rowCount = 0;
foreach ($rowsByTable as $insertRows) {
    $rowCount += count($insertRows);
}

$output = itm_sample_sql_build_sample_file_body($rowsByTable, $templateCompanyId);
$outputSize = strlen($output);

echo 'Target: ' . $targetPath . $nl;
echo 'Template tables: ' . count($rowsByTable) . $nl;
echo 'Template rows: ' . $rowCount . $nl;
echo 'DB backfill tables: ' . count($dbBackfilled) . $nl;
if ($dbBackfilled !== []) {
    echo '  ' . implode(', ', $dbBackfilled) . $nl;
}
echo 'Synthesized tables (no company ' . $templateCompanyId . ' row yet): ' . count($synthesized) . $nl;
if ($synthesized !== []) {
    echo '  ' . implode(', ', $synthesized) . $nl;
}
echo 'Missing tables (no SQL/DB template): ' . count($missingTables) . $nl;
if ($missingTables !== []) {
    echo '  ' . implode(', ', $missingTables) . $nl;
}
echo 'Output size: ' . number_format($outputSize) . ' bytes' . $nl;

if ($missingTables !== []) {
    echo $nl . '[FAIL] Cannot build full sample file until every table has a company ' . $templateCompanyId . ' template source.' . $nl;
    exit(1);
}

if (!$apply) {
    echo $nl . 'Dry-run — pass --apply (CLI) or ?apply=1 (browser Admin) to write.' . $nl;
    exit(0);
}

if (file_put_contents($targetPath, $output) === false) {
    echo '[FAIL] Could not write ' . $targetPath . $nl;
    exit(1);
}

echo '[OK] Wrote ' . $targetPath . $nl;
exit(0);
