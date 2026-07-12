<?php
/**
 * Verification script for Audit Log Sensitive Data Exposure.
 *
 * Why: Ensures that sensitive security tokens (password reset tokens) are
 * redacted before being recorded in the audit logs.
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Audit Token Leak Verification');
$nl = itm_script_output_nl();

echo "Verifying Audit Log Sensitive Data Exposure..." . $nl;

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-audit-token-leak']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$user_id = (int)$testUser['id'];
$username = (string)$testUser['username'];
$sensitiveColumns = ['reset_token', 'reset_token_hash', 'reset_token_expires_at'];
$snapshot = itm_script_test_employee_snapshot($conn, $user_id, $sensitiveColumns);
itm_script_test_employee_register_teardown($conn, $user_id, $snapshot);

// Simulate a password reset update
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);

itm_script_test_employee_set_audit_context($conn, $user_id, $username, $company_id);

$sql = "UPDATE employees SET reset_token = ?, reset_token_hash = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $token, $tokenHash, $user_id);
$stmt->execute();
$stmt->close();

echo "Updated reset_token for disposable user $username." . $nl;

// Check audit log
$res = mysqli_query($conn, "SELECT new_values FROM audit_logs WHERE table_name = 'employees' AND record_id = $user_id ORDER BY id DESC LIMIT 1");
$log = mysqli_fetch_assoc($res);

if ($log) {
    $newValues = json_decode($log['new_values'], true);
    if (isset($newValues['reset_token']) && $newValues['reset_token'] === $token) {
        echo colorText("[FAIL] VULNERABILITY: Plaintext reset_token found in audit log.", 'fail') . $nl;
    } else {
        echo colorText("[PASS] Success: reset_token is redacted or missing in audit log.", 'pass') . $nl;
    }
} else {
    echo colorText("[WARN] No audit log entry found for this update.", 'warn') . $nl;
}

itm_script_output_end();
