<?php
/**
 * Static guard: employees clear_table soft-deletes via shared delete helper.
 *
 * CLI: php scripts/check_employees_clear_table_transaction.php
 * Browser: scripts/check_employees_clear_table_transaction.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
$nl = itm_script_output_nl();

if (!$itmIsCli) {
    itm_script_output_begin('Employees clear_table guard');
    itm_script_output_close_pre();
    echo '<h1>Employees clear_table static guard</h1>';
    echo '<p class="scripts-muted">Verifies <code>clear_table</code> soft-deletes via <code>employees_delete_record()</code> (detach + transaction + <code>itm_crud_build_soft_delete_sql</code>).</p>';
    echo '<p><strong>Files audited:</strong> '
        . itm_script_external_link_html('../modules/employees/delete.php', 'modules/employees/delete.php')
        . ', '
        . itm_script_external_link_html('../modules/employees/delete_clear_table.php', 'modules/employees/delete_clear_table.php')
        . ', '
        . itm_script_external_link_html('../modules/employees/delete_functions.php', 'modules/employees/delete_functions.php')
        . '</p>';
}

$root = dirname(__DIR__);
$deletePath = $root . '/modules/employees/delete.php';
$helperPath = $root . '/modules/employees/delete_clear_table.php';
$functionsPath = $root . '/modules/employees/delete_functions.php';

$failures = 0;

function itm_ect_report(string $label, bool $passed): void
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

if (!is_file($deletePath) || !is_file($helperPath) || !is_file($functionsPath)) {
    itm_ect_report('employees delete/clear-table files exist', false);
    if ($itmIsCli) {
        exit(1);
    }
    itm_script_output_end();
    exit(1);
}

$deleteSource = (string)file_get_contents($deletePath);
$helperSource = (string)file_get_contents($helperPath);
$functionsSource = (string)file_get_contents($functionsPath);

if ($itmIsCli) {
    echo colorText('Employees clear_table static guard', 'info') . PHP_EOL;
}

itm_ect_report('delete.php requires delete_clear_table.php', stripos($deleteSource, 'delete_clear_table.php') !== false);
itm_ect_report('delete.php delegates clear_table to helper', stripos($deleteSource, 'employees_clear_table_for_company') !== false);
itm_ect_report('helper defines employees_clear_table_for_company', stripos($helperSource, 'function employees_clear_table_for_company') !== false);
itm_ect_report('helper selects live rows only', stripos($helperSource, 'deleted_at IS NULL') !== false);
itm_ect_report('helper calls employees_delete_record', stripos($helperSource, 'employees_delete_record') !== false);
itm_ect_report('delete_functions detaches dependencies', stripos($functionsSource, 'itm_employees_detach_delete_dependencies') !== false);
itm_ect_report('delete_functions begins transaction', stripos($functionsSource, 'mysqli_begin_transaction') !== false);
itm_ect_report('delete_functions rolls back on failure', stripos($functionsSource, 'mysqli_rollback') !== false);
itm_ect_report('delete_functions commits on success', stripos($functionsSource, 'mysqli_commit') !== false);
itm_ect_report('delete_functions soft-deletes via helper', stripos($functionsSource, 'itm_crud_build_soft_delete_sql') !== false);
itm_ect_report('delete_functions does not hard-delete employees', stripos($functionsSource, 'DELETE FROM employees') === false);

if ($itmIsCli) {
    echo PHP_EOL;
}

if ($failures === 0) {
    $summary = '[PASS] Employees clear_table guard complete.';
    if ($itmIsCli) {
        fwrite(STDOUT, colorText($summary . PHP_EOL, 'pass'));
        exit(0);
    }
    echo $nl . itm_script_format_status_line($summary) . $nl;
    itm_script_output_end();
    exit(0);
}

$summary = '[FAIL] Employees clear_table guard failed ' . $failures . ' check(s).';
if ($itmIsCli) {
    fwrite(STDOUT, colorText($summary . PHP_EOL, 'fail'));
    exit(1);
}

echo $nl . itm_script_format_status_line($summary) . $nl;
itm_script_output_end();
exit(1);
