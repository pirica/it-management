<?php
/**
 * Regression: authentication attempt identifier redaction.
 *
 * Why: Passwords mistyped into the login email field must not persist verbatim in attempts.email.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Attempts Data Leak Verification');

$nl = itm_script_output_nl();
$leakedSecret = 'P@ssword123!';
$requestIp = '127.0.0.1';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = $requestIp;
$_POST['email'] = $leakedSecret;
$_POST['password'] = 'wrong-password-value';
$_POST['csrf_token'] = itm_get_csrf_token();

echo 'Simulating failed login with a password-like identifier.' . $nl;

ob_start();
include __DIR__ . '/../login.php';
ob_end_clean();

$expectedStored = itm_normalize_login_attempt_identifier($leakedSecret);
$stmt = mysqli_prepare($conn, 'SELECT email FROM attempts WHERE email = ? ORDER BY id DESC LIMIT 1');
if ($stmt === false) {
    echo colorText('[FAIL] Unable to prepare attempts lookup.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

mysqli_stmt_bind_param($stmt, 's', $expectedStored);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res) ?: null;
mysqli_stmt_close($stmt);

$leakedStmt = mysqli_prepare($conn, 'SELECT id FROM attempts WHERE email = ? ORDER BY id DESC LIMIT 1');
$leakedRow = null;
if ($leakedStmt) {
    mysqli_stmt_bind_param($leakedStmt, 's', $leakedSecret);
    mysqli_stmt_execute($leakedStmt);
    $leakedRes = mysqli_stmt_get_result($leakedStmt);
    $leakedRow = mysqli_fetch_assoc($leakedRes) ?: null;
    mysqli_stmt_close($leakedStmt);
}

if ($row && $row['email'] === $expectedStored && $expectedStored !== $leakedSecret && !$leakedRow) {
    echo colorText('[PASS] Identifier redacted before persistence in attempts.email.', 'pass') . $nl;
    $exitCode = 0;
} else {
    echo colorText('[FAIL] Sensitive identifier persisted verbatim in attempts.email.', 'fail') . $nl;
    $exitCode = 1;
}

if ($row) {
    $cleanup = mysqli_prepare($conn, 'DELETE FROM attempts WHERE email = ?');
    if ($cleanup) {
        mysqli_stmt_bind_param($cleanup, 's', $expectedStored);
        mysqli_stmt_execute($cleanup);
        mysqli_stmt_close($cleanup);
    }
}

itm_script_output_end();
exit($exitCode);
