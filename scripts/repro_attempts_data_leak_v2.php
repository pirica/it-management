<?php
/**
 * Regression: authentication attempt identifier redaction.
 *
 * Why: Passwords mistyped into the login email field must not persist verbatim in attempts.email.
 *
 * Browser + CLI. Uses a disposable secret per run and checks only the row inserted by this request.
 */

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Attempts Data Leak Verification');

$nl = itm_script_output_nl();
$leakedSecret = 'P@ssword_' . bin2hex(random_bytes(4)) . '!';
$requestIp = '127.0.0.1';

$maxIdBefore = 0;
$maxRes = mysqli_query($conn, 'SELECT COALESCE(MAX(id), 0) AS max_id FROM attempts');
if ($maxRes && ($maxRow = mysqli_fetch_assoc($maxRes))) {
    $maxIdBefore = (int)($maxRow['max_id'] ?? 0);
}

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
if ($expectedStored === null || $expectedStored === $leakedSecret) {
    echo colorText('[FAIL] Normalizer did not redact the disposable test identifier.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, email FROM attempts
     WHERE id > ?
       AND attempt_source = 'login'
       AND attempt_type = 'failure'
       AND ip_address = ?
     ORDER BY id DESC
     LIMIT 1"
);
if ($stmt === false) {
    echo colorText('[FAIL] Unable to prepare attempts lookup.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

mysqli_stmt_bind_param($stmt, 'is', $maxIdBefore, $requestIp);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res) ?: null;
mysqli_stmt_close($stmt);

if ($row && (string)$row['email'] === (string)$expectedStored) {
    echo colorText('[PASS] Identifier redacted before persistence in attempts.email.', 'pass') . $nl;
    echo 'Stored: ' . (string)$row['email'] . $nl;
    $exitCode = 0;
} else {
    echo colorText('[FAIL] Sensitive identifier persisted verbatim in attempts.email.', 'fail') . $nl;
    if ($row) {
        echo 'Latest inserted attempts.email: ' . (string)$row['email'] . $nl;
    } else {
        echo 'No new login failure row was inserted for this run.' . $nl;
    }
    echo 'Expected redacted value: ' . (string)$expectedStored . $nl;
    $exitCode = 1;
}

if ($row) {
    $attemptId = (int)$row['id'];
    $cleanup = mysqli_prepare($conn, 'DELETE FROM attempts WHERE id = ?');
    if ($cleanup) {
        mysqli_stmt_bind_param($cleanup, 'i', $attemptId);
        mysqli_stmt_execute($cleanup);
        mysqli_stmt_close($cleanup);
    }
}

itm_script_output_end();
exit($exitCode);
