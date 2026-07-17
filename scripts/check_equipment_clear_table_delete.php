<?php
/**
 * Static guard: equipment clear_table soft-deletes via shared delete helper.
 *
 * CLI: php scripts/check_equipment_clear_table_delete.php
 * Browser: scripts/check_equipment_clear_table_delete.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
$nl = itm_script_output_nl();

if (!$itmIsCli) {
    itm_script_output_begin('Equipment clear_table guard');
    itm_script_output_close_pre();
    itm_script_browser_nav_echo();
    echo '<h1>Equipment clear_table static guard</h1>';
    echo '<p class="scripts-muted">Verifies <code>clear_table</code> soft-deletes via <code>equipment_delete_record()</code> (transaction + <code>itm_crud_build_soft_delete_sql</code>).</p>';
    echo '<p><strong>Files audited:</strong> '
        . itm_script_external_link_html('../modules/equipment/delete.php', 'modules/equipment/delete.php')
        . ', '
        . itm_script_external_link_html('../modules/equipment/delete_functions.php', 'modules/equipment/delete_functions.php')
        . '</p>';
}

$root = dirname(__DIR__);
$deletePath = $root . '/modules/equipment/delete.php';
$functionsPath = $root . '/modules/equipment/delete_functions.php';

$failures = 0;

function itm_eqct_report(string $label, bool $passed): void
{
    global $failures, $itmIsCli, $nl;

    if ($passed) {
        $line = '[PASS] ' . $label;
        if ($itmIsCli) {
            fwrite(STDOUT, colorText($line . PHP_EOL, 'pass'));
        } else {
            echo itm_script_format_status_line($line) . $nl;
        }
        return;
    }

    $failures++;
    $line = '[FAIL] ' . $label;
    if ($itmIsCli) {
        fwrite(STDOUT, colorText($line . PHP_EOL, 'fail'));
    } else {
        echo itm_script_format_status_line($line) . $nl;
    }
}

if (!is_file($deletePath) || !is_file($functionsPath)) {
    itm_eqct_report('equipment delete helper files exist', false);
    if ($itmIsCli) {
        exit(1);
    }
    itm_script_output_end();
    exit(1);
}

$deleteSource = (string)file_get_contents($deletePath);
$functionsSource = (string)file_get_contents($functionsPath);

if ($itmIsCli) {
    echo colorText('Equipment clear_table static guard', 'info') . PHP_EOL;
}

itm_eqct_report('delete.php requires delete_functions.php', stripos($deleteSource, 'delete_functions.php') !== false);
itm_eqct_report('delete.php delegates clear_table to helper', stripos($deleteSource, 'equipment_clear_table_for_company') !== false);
itm_eqct_report('helper defines equipment_clear_table_for_company', stripos($functionsSource, 'function equipment_clear_table_for_company') !== false);
itm_eqct_report('clear_table selects live rows only', stripos($functionsSource, 'deleted_at IS NULL') !== false);
itm_eqct_report('clear_table checks SELECT result for failure', stripos($functionsSource, '$listResult === false') !== false);
itm_eqct_report('clear_table reports unable to load equipment', stripos($functionsSource, 'Unable to load equipment records for clear table') !== false);
itm_eqct_report('clear_table calls equipment_delete_record per row', stripos($functionsSource, 'equipment_delete_record($conn, $companyId, $equipmentId)') !== false);
itm_eqct_report('equipment_delete_record uses transaction', stripos($functionsSource, 'mysqli_begin_transaction') !== false);
itm_eqct_report('equipment_delete_record rolls back on failure', stripos($functionsSource, 'mysqli_rollback') !== false);
itm_eqct_report('equipment_delete_record commits on success', stripos($functionsSource, 'mysqli_commit') !== false);
itm_eqct_report('equipment_delete_record soft-deletes via helper', stripos($functionsSource, 'itm_crud_build_soft_delete_sql') !== false);
itm_eqct_report('equipment_delete_record does not hard-delete equipment', stripos($functionsSource, 'DELETE FROM equipment') === false);
itm_eqct_report('switch delete clears ports before usage check', stripos($functionsSource, 'equipment_delete_switch_port_data') !== false);

if ($itmIsCli) {
    echo PHP_EOL;
}

if ($failures === 0) {
    $summary = '[PASS] Equipment clear_table guard complete.';
    if ($itmIsCli) {
        fwrite(STDOUT, colorText($summary . PHP_EOL, 'pass'));
        exit(0);
    }
    echo $nl . itm_script_format_status_line($summary) . $nl;
    itm_script_output_end();
    exit(0);
}

$summary = '[FAIL] Equipment clear_table guard failed ' . $failures . ' check(s).';
if ($itmIsCli) {
    fwrite(STDOUT, colorText($summary . PHP_EOL, 'fail'));
    exit(1);
}

echo $nl . itm_script_format_status_line($summary) . $nl;
itm_script_output_end();
exit(1);
