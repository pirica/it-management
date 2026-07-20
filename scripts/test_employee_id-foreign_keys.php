<?php
/**
 * Validates employee_id foreign keys and scoping across all tables.
 *
 * Why: Ensures that all tables with an employee_id column are properly
 * scoped to the employees table and identifies missing FKs.
 *
 * Browser: open scripts/test_employee_id-foreign_keys.php (login required).
 * CLI: php scripts/test_employee_id-foreign_keys.php
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$nl = itm_script_output_nl();
itm_script_output_begin('Validating employee_id foreign keys');

$res = mysqli_query($conn, "SHOW TABLES");
$tables = [];
while ($row = mysqli_fetch_row($res)) {
    $tables[] = $row[0];
}

// Tabelas que não devem ter employee_id
$globalTables = ['companies', 'audit_logs', 'modules_registry', 'floor_plan_item_tags'];

$skipped = 0;
$ok = 0;
$error = 0;
$empty = 0;

$skippedList = [];
$okList = [];
$errorList = [];
$emptyList = [];

foreach ($tables as $table) {

    if (in_array($table, $globalTables)) {
        $skipped++;
        $skippedList[] = $table;
        continue;
    }

    // Check if table has employee_id column
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    $hasEmployeeId = mysqli_num_rows($colRes) > 0;

    if (!$hasEmployeeId) {
        $skipped++;
        $skippedList[] = $table;
        continue;
    }

    echo "Validating FK for table: $table" . $nl;

    $sql = "
        SELECT 
            rc.CONSTRAINT_NAME, 
            ku.REFERENCED_TABLE_NAME, 
            ku.REFERENCED_COLUMN_NAME,
            rc.UPDATE_RULE,
            rc.DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE ku
            ON rc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
            AND rc.CONSTRAINT_SCHEMA = ku.TABLE_SCHEMA
        WHERE ku.TABLE_NAME = '$table'
          AND ku.COLUMN_NAME = 'employee_id'
          AND rc.CONSTRAINT_SCHEMA = DATABASE()
    ";

    $fkRes = mysqli_query($conn, $sql);

    // Query failed
    if (!$fkRes) {
        echo colorText("[ERROR] Query failed: " . mysqli_error($conn), 'fail') . $nl;
        $error++;
        $errorList[] = $table;
        continue;
    }

    // No FK found
    if (mysqli_num_rows($fkRes) === 0) {
        echo colorText("[ERROR] employee_id exists but has NO FOREIGN KEY!", 'fail') . $nl;
        echo "Proposed FK:" . $nl;
        echo "ALTER TABLE `$table` ADD CONSTRAINT `{$table}_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL;" . $nl;
        $error++;
        $errorList[] = $table;
    } else {
        // FK found
        while ($fk = mysqli_fetch_assoc($fkRes)) {
            echo colorText("[OK]", 'pass') . " {$fk['CONSTRAINT_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}" . $nl;
            echo " - Update rule: {$fk['UPDATE_RULE']} | Delete rule: {$fk['DELETE_RULE']}" . $nl;
        }

        // Check if table has data
        $dataRes = mysqli_query($conn, "SELECT employee_id FROM `$table` LIMIT 1");
        if ($dataRes && mysqli_num_rows($dataRes) === 0) {
            echo colorText("[EMPTY] No data to verify scoping", 'warn') . $nl;
            $empty++;
            $emptyList[] = $table;
        } else {
            $ok++;
            $okList[] = $table;
        }
    }

    echo "--------------------------------------------------------" . $nl;
}

// SUMMARY
echo $nl . "### SUMMARY" . $nl;
echo "SKIPPED: $skipped" . $nl;
echo "OK: $ok" . $nl;
echo "ERROR: $error" . $nl;
echo "EMPTY: $empty" . $nl;

itm_script_output_end();
