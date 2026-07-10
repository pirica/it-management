<?php
/**
 * Static guard: employees clear_table must stay transactional and ordered.
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

if (!is_file($deletePath) || !is_file($helperPath)) {
    fwrite(STDOUT, colorText("[FAIL] employees delete/clear-table files missing\n", 'fail'));
    exit(1);
}

$deleteSource = (string)file_get_contents($deletePath);
$helperSource = (string)file_get_contents($helperPath);

itm_ect_assert('delete.php requires delete_clear_table.php', stripos($deleteSource, 'delete_clear_table.php') !== false);
itm_ect_assert('delete.php delegates clear_table to helper', stripos($deleteSource, 'employees_clear_table_for_company') !== false);
itm_ect_assert('helper defines employees_clear_table_for_company', stripos($helperSource, 'function employees_clear_table_for_company') !== false);
itm_ect_assert('helper begins transaction', stripos($helperSource, 'mysqli_begin_transaction') !== false);
itm_ect_assert('helper rolls back on failure', stripos($helperSource, 'mysqli_rollback') !== false);
itm_ect_assert('helper commits on success', stripos($helperSource, 'mysqli_commit') !== false);

$accessPos = stripos($helperSource, 'DELETE FROM employee_system_access');
$employeesPos = stripos($helperSource, 'DELETE FROM employees WHERE company_id');
itm_ect_assert('helper deletes employee_system_access', $accessPos !== false);
itm_ect_assert('helper deletes employees by company', $employeesPos !== false);
itm_ect_assert('access delete precedes employees delete', $accessPos !== false && $employeesPos !== false && $accessPos < $employeesPos);

fwrite(STDOUT, "\nFailures: {$failures}\n");
exit($failures > 0 ? 1 : 0);

itm_script_output_end();
