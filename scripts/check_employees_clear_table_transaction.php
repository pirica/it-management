<?php
/**
 * Static guard: employees clear_table soft-deletes via shared delete helper.
 *
 * CLI: php scripts/check_employees_clear_table_transaction.php
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
    echo '<p><strong>CLI only.</strong></p><pre>php scripts/check_employees_clear_table_transaction.php</pre></body></html>';
    exit(1);
}

$root = dirname(__DIR__);
$deletePath = $root . '/modules/employees/delete.php';
$helperPath = $root . '/modules/employees/delete_clear_table.php';
$functionsPath = $root . '/modules/employees/delete_functions.php';

$failures = 0;

function itm_ect_assert(string $label, bool $condition): void
{
    global $failures;
    if ($condition) {
        fwrite(STDOUT, colorText("[PASS] {$label}\n", 'pass'));
        return;
    }
    $failures++;
    fwrite(STDOUT, colorText("[FAIL] {$label}\n", 'fail'));
}

if (!is_file($deletePath) || !is_file($helperPath) || !is_file($functionsPath)) {
    fwrite(STDOUT, colorText("[FAIL] employees delete/clear-table files missing\n", 'fail'));
    exit(1);
}

$deleteSource = (string)file_get_contents($deletePath);
$helperSource = (string)file_get_contents($helperPath);
$functionsSource = (string)file_get_contents($functionsPath);

itm_ect_assert('delete.php requires delete_clear_table.php', stripos($deleteSource, 'delete_clear_table.php') !== false);
itm_ect_assert('delete.php delegates clear_table to helper', stripos($deleteSource, 'employees_clear_table_for_company') !== false);
itm_ect_assert('helper defines employees_clear_table_for_company', stripos($helperSource, 'function employees_clear_table_for_company') !== false);
itm_ect_assert('helper selects live rows only', stripos($helperSource, 'deleted_at IS NULL') !== false);
itm_ect_assert('helper calls employees_delete_record', stripos($helperSource, 'employees_delete_record') !== false);

itm_ect_assert('delete_functions detaches dependencies', stripos($functionsSource, 'itm_employees_detach_delete_dependencies') !== false);
itm_ect_assert('delete_functions begins transaction', stripos($functionsSource, 'mysqli_begin_transaction') !== false);
itm_ect_assert('delete_functions rolls back on failure', stripos($functionsSource, 'mysqli_rollback') !== false);
itm_ect_assert('delete_functions commits on success', stripos($functionsSource, 'mysqli_commit') !== false);
itm_ect_assert('delete_functions soft-deletes via helper', stripos($functionsSource, 'itm_crud_build_soft_delete_sql') !== false);
itm_ect_assert('delete_functions does not hard-delete employees', stripos($functionsSource, 'DELETE FROM employees') === false);

fwrite(STDOUT, "\nFailures: {$failures}\n");
exit($failures > 0 ? 1 : 0);

itm_script_output_end();
