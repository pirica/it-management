<?php
/**
 * Static guard: equipment clear_table must surface SELECT failures and delegate deletes safely.
 *
 * CLI: php scripts/check_equipment_clear_table_delete.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();


if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;">';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong></p><pre>php scripts/check_equipment_clear_table_delete.php</pre></body></html>';
    exit(1);
}

$root = dirname(__DIR__);
$deletePath = $root . '/modules/equipment/delete.php';
$functionsPath = $root . '/modules/equipment/delete_functions.php';

$failures = 0;

function itm_eqct_assert(string $label, bool $condition): void
{
    global $failures;
    if ($condition) {
        fwrite(STDOUT, colorText("[PASS] {$label}\n", 'pass'));
        return;
    }
    $failures++;
    fwrite(STDOUT, colorText("[FAIL] {$label}\n", 'fail'));
}

if (!is_file($deletePath) || !is_file($functionsPath)) {
    fwrite(STDOUT, colorText("[FAIL] equipment delete helper files missing\n", 'fail'));
    exit(1);
}

$deleteSource = (string)file_get_contents($deletePath);
$functionsSource = (string)file_get_contents($functionsPath);

itm_eqct_assert('delete.php requires delete_functions.php', stripos($deleteSource, 'delete_functions.php') !== false);
itm_eqct_assert('delete.php delegates clear_table to helper', stripos($deleteSource, 'equipment_clear_table_for_company') !== false);
itm_eqct_assert('helper defines equipment_clear_table_for_company', stripos($functionsSource, 'function equipment_clear_table_for_company') !== false);
itm_eqct_assert('clear_table checks SELECT result for failure', stripos($functionsSource, '$listResult === false') !== false);
itm_eqct_assert('clear_table reports unable to load equipment', stripos($functionsSource, 'Unable to load equipment records for clear table') !== false);
itm_eqct_assert('clear_table calls equipment_delete_record per row', stripos($functionsSource, 'equipment_delete_record($conn, $companyId, $equipmentId)') !== false);
itm_eqct_assert('equipment_delete_record uses transaction', stripos($functionsSource, 'mysqli_begin_transaction') !== false);
itm_eqct_assert('equipment_delete_record rolls back on failure', stripos($functionsSource, 'mysqli_rollback') !== false);
itm_eqct_assert('switch delete clears ports before usage check', stripos($functionsSource, 'equipment_delete_switch_port_data') !== false);

fwrite(STDOUT, "\nFailures: {$failures}\n");
exit($failures > 0 ? 1 : 0);
