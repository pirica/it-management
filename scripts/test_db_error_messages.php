<?php
/**
 * Verifies human-friendly MySQL error formatting.
 *
 * Browser + CLI. Read-only assertion harness (no DB/file writes; no dry-run gate).
 *
 * Usage:
 *   php scripts/test_db_error_messages.php
 *   Browser: scripts/test_db_error_messages.php
 */

declare(strict_types=1);

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('DB Error Message Assertions');

$nl = itm_script_output_nl();

$failures = 0;
$passed = [];
$failed = [];

function itm_test_db_error_assert($label, $condition)
{
    global $failures, $nl, $passed, $failed;
    if ($condition) {
        $passed[] = $label;
        echo colorText('[PASS] ' . $label, 'pass') . $nl;
        return;
    }
    $failures++;
    $failed[] = $label;
    echo colorText('[FAIL] ' . $label, 'fail') . $nl;
}

echo 'Running human-friendly DB error message assertions...' . $nl . $nl;

$employeeNull = itm_format_db_constraint_error(1048, "Column 'employee_id' cannot be null");
itm_test_db_error_assert('1048 employee_id is humanized', stripos($employeeNull, 'Employee') !== false);
itm_test_db_error_assert('1048 employee_id has no Database error prefix', stripos($employeeNull, 'Database error:') === false);
itm_test_db_error_assert('1048 employee_id asks to select', stripos($employeeNull, 'select') !== false);

$nameNull = itm_format_db_constraint_error(1048, "Column 'name' cannot be null");
itm_test_db_error_assert('1048 name asks to enter', stripos($nameNull, 'enter') !== false);
itm_test_db_error_assert('1048 name mentions Name', stripos($nameNull, 'Name') !== false);

$tooLong = itm_format_db_constraint_error(1406, "Data too long for column 'name' at row 1");
itm_test_db_error_assert('1406 mentions too long', stripos($tooLong, 'too long') !== false);

$fkDelete = itm_format_db_constraint_error(1451, 'Cannot delete or update a parent row: a foreign key constraint fails');
itm_test_db_error_assert('1451 delete message preserved', stripos($fkDelete, 'cannot be deleted') !== false);

$duplicate = itm_format_db_constraint_error(1062, "Duplicate entry '1' for key 'PRIMARY'");
itm_test_db_error_assert('1062 duplicate message preserved', stripos($duplicate, 'already exists') !== false);

$unknown = itm_format_db_constraint_error(9999, 'Some internal server issue');
itm_test_db_error_assert('unknown uses generic save message', stripos($unknown, 'could not save') !== false);
itm_test_db_error_assert('unknown has no Database error prefix', stripos($unknown, 'Database error:') === false);

$legacyPrefix = itm_format_db_constraint_error(0, "Database error: Column 'department_id' cannot be null");
itm_test_db_error_assert('legacy Database error prefix is stripped', stripos($legacyPrefix, 'Department') !== false);

$rendered = itm_render_alert_errors(['Database error: Column \'employee_id\' cannot be null']);
itm_test_db_error_assert('render includes itm-alert', strpos($rendered, 'itm-alert') !== false);
itm_test_db_error_assert('render humanizes mysql text', stripos($rendered, 'Employee') !== false);

$apiDb = itm_humanize_api_error_message('DB error: Column \'name\' cannot be null');
itm_test_db_error_assert('API DB error prefix humanized', stripos($apiDb, 'Name') !== false);
itm_test_db_error_assert('API message has no DB error prefix', stripos($apiDb, 'DB error:') === false);

$rawDuplicate = itm_normalize_user_error_message("Duplicate entry '1-2' for key 'approvers.uk_company_employee'");
itm_test_db_error_assert('raw duplicate infers 1062 guidance', stripos($rawDuplicate, 'already exists') !== false);

$rawFkDelete = itm_normalize_user_error_message('Cannot delete or update a parent row: a foreign key constraint fails (`itmanagement`.`rack_positions`, CONSTRAINT `fk_rack_positions_rack`)');
itm_test_db_error_assert('raw FK delete infers 1451 guidance', stripos($rawFkDelete, 'cannot be deleted') !== false);

$rawFkInsert = itm_normalize_user_error_message('Cannot add or update a child row: a foreign key constraint fails (`itmanagement`.`approvers`, CONSTRAINT `fk_approvers_employee`)');
itm_test_db_error_assert('raw FK insert infers 1452 guidance', stripos($rawFkInsert, 'no longer available') !== false);

echo $nl . 'Failures: ' . $failures . $nl;
itm_script_echo_list('Passed (' . count($passed) . ')', $passed);
itm_script_echo_list('Failed (' . count($failed) . ')', $failed);

if ($failures === 0) {
    echo colorText('[OK] All assertions passed.', 'pass') . $nl;
} else {
    echo colorText('[FAIL] One or more assertions failed.', 'fail') . $nl;
}

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
