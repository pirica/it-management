<?php
/**
 * Reproduction script for Sensitive Information Disclosure in Audit Logs.
 *
 * Why: Confirms that sensitive security tokens (password reset tokens) are
 * recorded in plaintext in the audit logs.
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Audit Log Info Disclosure Verification');

$nl = itm_script_output_nl();
echo "Testing Sensitive Information Disclosure in Audit Logs..." . $nl;

$company_id = 1;
// Ensure audit logging is enabled for this test
mysqli_query($conn, "UPDATE ui_configuration SET enable_audit_logs = 1 WHERE company_id = $company_id");

// 1. Simulate a password reset request
$user_id = 1; // Existing user
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$tokenExpiresAt = date('Y-m-d H:i:s', time() + 3600);

// Audit triggers rely on these variables
mysqli_query($conn, "SET @app_user_id = 1");
mysqli_query($conn, "SET @app_company_id = $company_id");
mysqli_query($conn, "SET @app_username = 'admin'");

$sql = "UPDATE users SET reset_token = ?, reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $token, $tokenHash, $tokenExpiresAt, $user_id);
$stmt->execute();
$stmt->close();

echo "Triggered password reset for user ID $user_id." . $nl;

// 2. Inspect the latest audit log entry for the users table
$res = mysqli_query($conn, "SELECT * FROM audit_logs WHERE table_name = 'users' AND record_id = $user_id ORDER BY id DESC LIMIT 1");
$log = mysqli_fetch_assoc($res);

if ($log) {
    $new_values = json_decode($log['new_values'], true);
    if (isset($new_values['reset_token']) && $new_values['reset_token'] === $token) {
        echo colorText("[FAIL] VULNERABILITY CONFIRMED: Plaintext reset token found in audit log entry.", 'fail') . $nl;
        echo "Found token: " . $new_values['reset_token'] . $nl;
    } else {
        echo colorText("[PASS] SAFE: Reset token not found in plaintext in the audit log.", 'pass') . $nl;
    }
} else {
    echo "No audit log entry captured." . $nl;
}

// Cleanup
mysqli_query($conn, "UPDATE users SET reset_token = NULL, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = $user_id");

itm_script_output_end();
