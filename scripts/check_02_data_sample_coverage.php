<?php
/**
 * Static gate: every tenant-scoped table has at least one row in db/02_data_sample.sql.
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/check_02_data_sample_coverage.php</code> — exit <code>1</code> when a required table lacks a template. Skips <code>itm_sample_sql_exempt_tables()</code> (child/junction/matrix tables — see <code>includes/itm_sample_sql_export.php</code>).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/itm_sample_sql_export.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin();
$nl = itm_script_output_nl();

$templateCompanyId = defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')
    ? (int)ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID
    : 1;

$requiredTables = itm_sample_sql_schema_tables_with_company_id();
$sampleBody = itm_database_sql_read_sample();
if ($sampleBody === '') {
    echo '[FAIL] db/02_data_sample.sql is missing or empty.' . $nl;
    itm_script_output_end();
    exit(1);
}

$parsed = itm_parse_database_sql_inserts($sampleBody);
$missing = [];
$short = [];
foreach ($requiredTables as $tableName) {
    $rows = $parsed[$tableName] ?? [];
    if ($rows === []) {
        $missing[] = $tableName;
        continue;
    }
    $companyRows = itm_sample_sql_filter_company_template_rows($rows, $templateCompanyId);
    if ($companyRows === [] && itm_table_has_column($conn, $tableName, 'company_id')) {
        $missing[] = $tableName . ' (no company_id=' . $templateCompanyId . ' marker rows)';
        continue;
    }
    if (count($companyRows) < 1 && count($rows) < 1) {
        $short[] = $tableName;
    }
}

if ($missing !== []) {
    echo '[FAIL] Missing sample templates for ' . count($missing) . ' table(s):' . $nl;
    echo '  ' . implode(', ', $missing) . $nl;
    itm_script_output_end();
    exit(1);
}

echo '[PASS] db/02_data_sample.sql has at least one template row for ' . count($requiredTables) . ' tenant-scoped tables.' . $nl;
itm_script_output_end();
exit(0);
