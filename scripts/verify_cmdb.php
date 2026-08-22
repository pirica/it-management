<?php
/**
 * Regression checks for CMDB Lite (configuration items, types, relationships).
 *
 * Usage: php scripts/verify_cmdb.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_cmdb.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_cmdb.php</code>, <code>modules/configuration_items/</code>, or equipment/IDF sync hooks.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('CMDB Lite Verification');
$nl = itm_script_output_nl();
$failures = 0;

function cmdb_verify_fail(string $message): void
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function cmdb_verify_pass(string $message): void
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    cmdb_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

require_once ROOT_PATH . 'includes/itm_cmdb.php';

foreach (['configuration_item_types', 'configuration_items', 'configuration_item_relationships'] as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    if (!$res || mysqli_num_rows($res) === 0) {
        cmdb_verify_fail("Missing table {$table}");
    } else {
        cmdb_verify_pass("Table {$table} exists");
    }
}

if (!function_exists('itm_cmdb_build_impact_graph')) {
    cmdb_verify_fail('Missing itm_cmdb_build_impact_graph()');
} else {
    cmdb_verify_pass('Impact graph helper registered');
}

$companyId = 1;
itm_cmdb_seed_types_for_company($conn, $companyId, 1);
$serverTypeId = itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:server');
if ($serverTypeId <= 0) {
    cmdb_verify_fail('builtin:server type missing for company 1');
} else {
    cmdb_verify_pass('Builtin Server CI type seeded');
}

$token = 'cmdb-verify-' . bin2hex(random_bytes(4));
$testApp = 'CMDB Verify App ' . $token;
$testSrv = 'CMDB Verify Server ' . $token;

$insApp = mysqli_prepare($conn, 'INSERT INTO configuration_items (company_id, ci_type_id, name, active, created_by) VALUES (?, ?, ?, 1, 1)');
$insSrv = mysqli_prepare($conn, 'INSERT INTO configuration_items (company_id, ci_type_id, name, active, created_by) VALUES (?, ?, ?, 1, 1)');
if (!$insApp || !$insSrv) {
    cmdb_verify_fail('Could not prepare disposable CI inserts');
} else {
    mysqli_stmt_bind_param($insApp, 'iis', $companyId, $serverTypeId, $testApp);
    mysqli_stmt_bind_param($insSrv, 'iis', $companyId, $serverTypeId, $testSrv);
    mysqli_stmt_execute($insApp);
    $appId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($insApp);
    mysqli_stmt_execute($insSrv);
    $srvId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($insSrv);

    if ($appId <= 0 || $srvId <= 0) {
        cmdb_verify_fail('Could not create disposable test CIs');
    } else {
        $add = itm_cmdb_add_relationship($conn, $companyId, $srvId, $appId, 'depends_on', 1);
        if (empty($add['ok'])) {
            cmdb_verify_fail('depends_on relationship insert failed: ' . ($add['error'] ?? ''));
        } else {
            cmdb_verify_pass('depends_on relationship created');
        }

        $cycle = itm_cmdb_add_relationship($conn, $companyId, $appId, $srvId, 'depends_on', 1);
        if (!empty($cycle['ok'])) {
            cmdb_verify_fail('Cycle detection should block reverse depends_on edge');
        } else {
            cmdb_verify_pass('Cycle detection blocks reverse edge');
        }

        $graph = itm_cmdb_build_impact_graph($conn, $companyId, $srvId);
        if (count($graph['nodes'] ?? []) < 2) {
            cmdb_verify_fail('Impact graph should include at least two nodes');
        } else {
            cmdb_verify_pass('Impact BFS graph returns linked nodes');
        }

        mysqli_query(
            $conn,
            'UPDATE configuration_item_relationships SET deleted_at = NOW(), active = 0
             WHERE company_id = ' . (int)$companyId . ' AND (parent_ci_id IN (' . $appId . ',' . $srvId . ') OR child_ci_id IN (' . $appId . ',' . $srvId . '))'
        );
        mysqli_query(
            $conn,
            'UPDATE configuration_items SET deleted_at = NOW(), active = 0 WHERE id IN (' . $appId . ',' . $srvId . ')'
        );
    }
}

if (!is_file(ROOT_PATH . 'modules/configuration_items/api.php')) {
    cmdb_verify_fail('Missing modules/configuration_items/api.php');
} else {
    cmdb_verify_pass('Impact API entry exists');
}

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
