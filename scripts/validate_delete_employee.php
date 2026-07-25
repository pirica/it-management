<?php
/**
 * Checks if employees can be safely deleted by auditing referencing FKs and triggers.
 *
 * Why: Prevents accidental data loss or foreign key constraint violations
 * when attempting to delete an employee record.
 *
 * Browser: open scripts/validate_delete_employee.php (login required).
 * CLI: php scripts/validate_delete_employee.php
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
itm_script_output_begin('Validating employee deletion safety');

echo "### Checking FKs referencing employees..." . $nl;

$sql = "
SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE REFERENCED_TABLE_NAME = 'employees'
  AND CONSTRAINT_SCHEMA = DATABASE()
";
$res = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($res)) {
    $ruleColor = ($row['DELETE_RULE'] === 'CASCADE') ? 'warn' : 'pass';
    echo " - {$row['TABLE_NAME']} → {$row['CONSTRAINT_NAME']} (DELETE: " . colorText($row['DELETE_RULE'], $ruleColor) . ")" . $nl;
}

echo $nl . "### Checking triggers containing DELETE FROM employees..." . $nl;

$sql = "
SELECT TRIGGER_NAME, ACTION_STATEMENT
FROM information_schema.TRIGGERS
WHERE ACTION_STATEMENT LIKE '%DELETE FROM employees%'
  AND TRIGGER_SCHEMA = DATABASE()
";
$res = mysqli_query($conn, $sql);

if (mysqli_num_rows($res) === 0) {
    echo colorText("No triggers delete employees", 'pass') . $nl;
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        echo colorText("Trigger {$row['TRIGGER_NAME']} deletes employees!", 'fail') . $nl;
    }
}

itm_script_output_end();
