<?php
/**
 * Regression: employee contact email rule (at least one of work_email / personal_email).
 *
 * CLI: php scripts/verify_employee_contact_email.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

$conn = $GLOBALS['conn'] ?? null;
$nl = itm_script_output_nl();
$failed = 0;

function vece_pass(string $message): void
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function vece_fail(string $message): void
{
    global $nl, $failed;
    $failed++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

if (!$conn instanceof mysqli) {
    vece_fail('Database connection required.');
    itm_script_output_end();
    exit(1);
}

itm_script_output_begin('Verify employee contact email');

$expectedError = itm_employee_contact_email_validation_error();

if (itm_employee_has_contact_email('work@example.com', '')) {
    vece_pass('work_email only is valid.');
} else {
    vece_fail('work_email only should be valid.');
}

if (itm_employee_has_contact_email('', 'personal@gmail.com')) {
    vece_pass('personal_email only is valid.');
} else {
    vece_fail('personal_email only should be valid.');
}

if (itm_employee_has_contact_email('a@b.com', 'c@d.com')) {
    vece_pass('both emails is valid.');
} else {
    vece_fail('both emails should be valid.');
}

if (!itm_employee_has_contact_email('', '')) {
    vece_pass('both empty is invalid.');
} else {
    vece_fail('both empty should be invalid.');
}

if (itm_employee_validate_contact_email_or_error('work@example.com', '') === null) {
    vece_pass('validate returns null for work_email only.');
} else {
    vece_fail('validate should pass for work_email only.');
}

$bothEmptyError = itm_employee_validate_contact_email_or_error('', '');
if ($bothEmptyError === $expectedError) {
    vece_pass('validate returns canonical error when both empty.');
} else {
    vece_fail('validate error message mismatch for both empty.');
}

list($mergedWork, $mergedPersonal) = itm_employee_resolve_contact_emails_after_merge(
    ['work_email' => '', 'personal_email' => ''],
    ['work_email' => 'keep@example.com', 'personal_email' => ''],
    []
);
if ($mergedWork === 'keep@example.com' && $mergedPersonal === '') {
    vece_pass('merge keeps existing work_email when not provided.');
} else {
    vece_fail('merge should preserve existing work_email.');
}

$importRowError = itm_employee_validate_contact_email_or_error(
    itm_employee_contact_email_from_sql_value('NULL'),
    itm_employee_contact_email_from_sql_value('NULL')
);
if ($importRowError === $expectedError) {
    vece_pass('import NULL fragments treated as empty (reject both empty).');
} else {
    vece_fail('import NULL fragments should fail when both empty.');
}

$importMappedError = itm_employee_validate_contact_email_or_error('', '');
if ($importMappedError === $expectedError) {
    vece_pass('import row with only employee_code and no emails would fail validation.');
} else {
    vece_fail('import mapped row with no emails should fail.');
}

$createPath = dirname(__DIR__) . '/modules/employees/create.php';
$editPath = dirname(__DIR__) . '/modules/employees/edit.php';
$createSource = is_file($createPath) ? (string)file_get_contents($createPath) : '';
$editSource = is_file($editPath) ? (string)file_get_contents($editPath) : '';
if (strpos($createSource, 'itm_employee_validate_contact_email_or_error') !== false) {
    vece_pass('employees/create.php uses shared contact email validator.');
} else {
    vece_fail('employees/create.php missing shared contact email validator.');
}
if (strpos($editSource, 'itm_employee_validate_contact_email_or_error') !== false) {
    vece_pass('employees/edit.php uses shared contact email validator.');
} else {
    vece_fail('employees/edit.php missing shared contact email validator.');
}

$fastAccPath = __DIR__ . '/../modules/employees/fast_create_acc.php';
$fastAccSource = is_file($fastAccPath) ? (string)file_get_contents($fastAccPath) : '';
if (strpos($fastAccSource, 'name="personal_email"') !== false && strpos($fastAccSource, 'name="work_email"') !== false) {
    vece_pass('modules/employees/fast_create_acc.php exposes work_email and personal_email fields.');
} else {
    vece_fail('modules/employees/fast_create_acc.php must expose both email fields.');
}
if (strpos($fastAccSource, 'itm_department_option_label') !== false) {
    if (strpos($fastAccSource, 'itm_fk_option_labels.php') !== false) {
        vece_pass('fast_create_acc.php loads itm_fk_option_labels for department labels.');
    } else {
        vece_fail('fast_create_acc.php calls itm_department_option_label() without requiring itm_fk_option_labels.php.');
    }
}

$disposable = itm_script_test_employee_create($conn, 1, ['script_slug' => 'verify-employee-contact-email']);
if (is_array($disposable) && (int)($disposable['id'] ?? 0) > 0) {
    vece_pass('itm_script_test_employee_create() still inserts disposable row with contact email.');
    itm_script_test_employee_delete($conn, (int)$disposable['id']);
} else {
    vece_fail('itm_script_test_employee_create() failed.');
}

$rejectDisposable = itm_script_test_employee_create($conn, 1, [
    'script_slug' => 'verify-employee-contact-email-reject',
    'work_email' => '',
    'personal_email' => '',
    'email' => '',
]);
if ($rejectDisposable === null) {
    vece_pass('itm_script_test_employee_create() rejects both-empty email options.');
} else {
    vece_fail('itm_script_test_employee_create() should reject both-empty emails.');
    if (is_array($rejectDisposable) && (int)($rejectDisposable['id'] ?? 0) > 0) {
        itm_script_test_employee_delete($conn, (int)$rejectDisposable['id']);
    }
}

if ($failed === 0) {
    echo $nl . colorText('[PASS] All employee contact email checks passed.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo $nl . colorText('[FAIL] ' . $failed . ' check(s) failed.', 'fail') . $nl;
itm_script_output_end();
exit(1);
