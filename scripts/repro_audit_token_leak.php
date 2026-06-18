<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_user.php';

itm_script_output_begin('Audit Log Token Leak PoC');

echo "Verifying Sensitive Information Disclosure in Audit Logs...\n";

$company_id = 1;
// Create a test user
$testUser = itm_script_test_user_create($conn, $company_id, [
    'script_slug' => 'repro-audit-leak'
]);

if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}
itm_script_test_user_register_teardown($conn, (int)$testUser['id']);

$email = $testUser['email'];

// 1. Trigger a password reset to generate a token
$token = 'POC_TOKEN_' . bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $token);
$tokenExpiresAt = date('Y-m-d H:i:s', time() + 3600);

echo "Triggering password reset for " . (itm_script_cli_is_cli() ? $email : htmlspecialchars($email, ENT_QUOTES, 'UTF-8')) . "...\n";
mysqli_query($conn, 'SET @app_user_id = ' . (int)$testUser['id']);
mysqli_query($conn, 'SET @app_company_id = ' . (int)$company_id);
mysqli_query($conn, "SET @app_username = '" . mysqli_real_escape_string($conn, $testUser['username']) . "'");

$stmt = mysqli_prepare(
    $conn,
    'UPDATE users SET reset_token = ?, reset_token_hash = ?, reset_token_expires_at = ? WHERE email = ? LIMIT 1'
);
if (!$stmt) {
    echo colorText('[FAIL] Unable to prepare reset_token UPDATE.', 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_bind_param($stmt, 'ssss', $token, $tokenHash, $tokenExpiresAt, $email);
if (!mysqli_stmt_execute($stmt)) {
    echo colorText('[FAIL] reset_token UPDATE failed: ' . mysqli_stmt_error($stmt), 'fail') . itm_script_output_nl();
    mysqli_stmt_close($stmt);
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_close($stmt);

// 2. Check audit_logs for the plaintext token
echo "Checking audit_logs for leaked token...\n";
$auditRes = mysqli_query($conn, "SELECT new_values FROM audit_logs WHERE table_name = 'users' AND record_id = " . (int)$testUser['id'] . " AND action = 'UPDATE' ORDER BY id DESC LIMIT 1");
if ($auditRow = mysqli_fetch_assoc($auditRes)) {
    $newValues = $auditRow['new_values'];
    $displayValues = itm_script_cli_is_cli()
        ? (string)$newValues
        : htmlspecialchars((string)$newValues, ENT_QUOTES, 'UTF-8');
    echo "Audit Log new_values: {$displayValues}\n";
    if (strpos($newValues, $token) !== false) {
        echo colorText("[FAIL] Vulnerability Confirmed: Plaintext reset token found in audit logs!", 'fail') . itm_script_output_nl();
    } else {
        echo colorText("[PASS] Plaintext reset token NOT found in audit logs.", 'pass') . itm_script_output_nl();
    }
} else {
    echo colorText("[WARN] No audit log entry found for the update.", 'warn') . itm_script_output_nl();
}

itm_script_output_end();
