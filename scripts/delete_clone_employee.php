<?php
/**
 * Reverse an employee clone by deleting the employee and their related data.
 *
 * Why: Allows cleaning up after experimental data transfers.
 *
 * CLI: php scripts/delete_clone_employee.php --id=N
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Delete Clone Employee</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> Destructive tool must be run from the terminal.</p><pre>php scripts/delete_clone_employee.php --id=N</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Delete Clone Employee');

// Parse CLI ID
$options = getopt('', ['id:']);
$new_employee_id = isset($options['id']) ? (int)$options['id'] : 0;

if ($new_employee_id <= 0) {
    echo colorText('[FAIL] Please specify a valid employee ID with --id=N', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo "Reversing clone — deleting employee $new_employee_id" . $nl;

$excludeTables = ['audit_logs', 'attempts']; // nunca apagar

$errors = [];
$deletedTables = [];

$res = mysqli_query($conn, "SHOW TABLES");

// STEP 1 — DELETE RELATED DATA
echo "Deleting related data..." . $nl;

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    if (in_array($table, $excludeTables)) {
        continue;
    }

    // Check if table has employee_id
    $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($col) === 0) continue;

    $sql = "DELETE FROM `$table` WHERE employee_id = $new_employee_id";

    if (mysqli_query($conn, $sql)) {
        $affected = mysqli_affected_rows($conn);
        if ($affected > 0) {
            echo " - $table: " . colorText("DELETED ($affected rows)", 'pass') . $nl;
        }
        $deletedTables[] = $table;
    } else {
        echo " - $table: " . colorText("FAILED", 'fail') . " " . mysqli_error($conn) . $nl;
        $errors[] = $table;
    }
}

// STEP 2 — DELETE EMPLOYEE ITSELF
echo "Deleting employee record..." . $nl;

$sql = "DELETE FROM employees WHERE id = $new_employee_id";

if (mysqli_query($conn, $sql)) {
    echo " - employees: " . colorText("DELETED", 'pass') . $nl;
} else {
    echo " - employees: " . colorText("FAILED", 'fail') . " " . mysqli_error($conn) . $nl;
    $errors[] = "employees";
}

// FINAL RESULT
if (empty($errors)) {
    echo $nl . colorText('Clone reversed successfully — all data removed.', 'pass') . $nl;
} else {
    echo $nl . colorText('Some tables failed to delete:', 'fail') . $nl;
    foreach ($errors as $t) {
        echo " - $t" . $nl;
    }
}

itm_script_output_end();
