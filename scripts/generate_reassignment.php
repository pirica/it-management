<?php
/**
 * Generates UPDATE SQL to reassign data from an old employee to a new one before deletion.
 *
 * Why: Facilitates safe employee deletion by providing the necessary SQL
 * to preserve related data through reassignment.
 *
 * Browser: open scripts/generate_reassignment.php (login required).
 * CLI: php scripts/generate_reassignment.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
} else {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Generate reassignment SQL before deleting an employee');

$res = mysqli_query($conn, "SHOW TABLES");

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    // Check if table has employee_id
    $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($col) === 0) continue;

    echo "TABLE: $table" . $nl;

    echo "-- Reassign before deleting employee" . $nl;
    echo "UPDATE `$table` SET employee_id = :new_employee_id WHERE employee_id = :old_employee_id;" . $nl;
    echo "--------------------------------------------------------" . $nl;
}

itm_script_output_end();
