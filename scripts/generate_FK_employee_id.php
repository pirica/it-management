<?php
/**
 * Detect missing employee_id foreign keys and suggest ALTER TABLE SQL.
 *
 * Why: Ensures data integrity and consistent ON DELETE RESTRICT behavior
 * for all employee-related data.
 *
 * Browser: open scripts/generate_FK_employee_id.php (login required).
 * CLI: php scripts/generate_FK_employee_id.php
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
itm_script_output_begin('Generate FK employee_id (ON DELETE RESTRICT)');

$res = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    // Skip global tables
    $skip = ['companies','audit_logs','modules_registry','floor_plan_item_tags'];
    if (in_array($table, $skip)) {
        continue;
    }

    // Check if employee_id exists
    $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($col) === 0) {
        continue;
    }

    echo "TABLE: $table" . $nl;

    // Check FK
    $sql = "
        SELECT rc.CONSTRAINT_NAME
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE ku
            ON rc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
        WHERE ku.TABLE_NAME = '$table'
          AND ku.COLUMN_NAME = 'employee_id'
          AND rc.CONSTRAINT_SCHEMA = DATABASE()
    ";
    $fk = mysqli_query($conn, $sql);

    if (mysqli_num_rows($fk) > 0) {
        echo colorText('[OK] FK already exists', 'pass') . $nl;
        continue;
    }

    echo colorText('[ERROR] Missing FK!', 'fail') . $nl;

    echo "Suggested SQL:" . $nl;
    echo "ALTER TABLE `$table` ADD CONSTRAINT `{$table}_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT;" . $nl;
    echo "--------------------------------------------------------" . $nl;
}

itm_script_output_end();
