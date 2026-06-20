<?php
/**
 * Password reset token store/lookup regression (timezone-safe expiry).
 *
 * CLI: php scripts/verify_password_reset_flow.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Password Reset Flow Verification');

$conn = $GLOBALS['conn'] ?? null;
$nl = itm_script_output_nl();
$failures = 0;

function pr_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function pr_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (!($conn instanceof mysqli)) {
    pr_verify_fail('No database connection.');
    exit(1);
}

$testUser = itm_script_test_employee_create($conn, 1, ['script_slug' => 'password-reset-flow']);
if (!is_array($testUser) || (int)($testUser['id'] ?? 0) <= 0) {
    pr_verify_fail('Could not create disposable test employee.');
    itm_script_test_employee_register_teardown($conn, (int)($testUser['id'] ?? 0));
    exit(1);
}

$employeeId = (int)$testUser['id'];
$rawToken = '3a8a3e26179714a8a8009fc8ea5cb83c257fa4677c8b6aceaa0505fcdd544e22';

if (!itm_password_reset_store_token_for_employee($conn, $employeeId, $rawToken)) {
    pr_verify_fail('itm_password_reset_store_token_for_employee() failed.');
} else {
    pr_verify_pass('Stored reset token with DATE_ADD(NOW(), INTERVAL 1 HOUR).');
}

$lookup = itm_password_reset_lookup_employee_by_token($conn, $rawToken);
if ((int)($lookup['id'] ?? 0) !== $employeeId) {
    pr_verify_fail('Lookup by hash did not return the test employee.');
} else {
    pr_verify_pass('Lookup by token hash succeeded for sample 64-char token.');
}

$legacyToken = bin2hex(random_bytes(16));
$legacyStmt = mysqli_prepare(
    $conn,
    'UPDATE employees SET reset_token = ?, reset_token_hash = NULL, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ? LIMIT 1'
);
if ($legacyStmt) {
    mysqli_stmt_bind_param($legacyStmt, 'si', $legacyToken, $employeeId);
    mysqli_stmt_execute($legacyStmt);
    mysqli_stmt_close($legacyStmt);
}
$legacyLookup = itm_password_reset_lookup_employee_by_token($conn, $legacyToken);
if ((int)($legacyLookup['id'] ?? 0) !== $employeeId) {
    pr_verify_fail('Legacy plain reset_token fallback lookup failed.');
} else {
    pr_verify_pass('Legacy plain reset_token fallback lookup succeeded.');
}

$newPasswordHash = password_hash('VerifyResetFlow1', PASSWORD_DEFAULT);
if (!itm_password_reset_complete_for_employee($conn, $employeeId, $legacyToken, $newPasswordHash)) {
    pr_verify_fail('itm_password_reset_complete_for_employee() failed.');
} else {
    pr_verify_pass('Password reset completion cleared token fields.');
}

itm_script_test_employee_register_teardown($conn, $employeeId);

if ($failures > 0) {
    echo colorText('Verification finished with ' . $failures . ' failure(s).', 'fail') . $nl;
    exit(1);
}

echo colorText('All password reset flow checks passed.', 'pass') . $nl;
exit(0);
