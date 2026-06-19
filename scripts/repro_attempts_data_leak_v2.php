<?php
/**
 * Repro: Attempts Data Leak
 *
 * Demonstrates how sensitive information (like passwords) can be leaked into
 * the `attempts` table if entered into the email/username field during login.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

// 1. Simulate a failed login where the user typed their password in the email field
$leakedSecret = 'P@ssword123!';
$requestIp = '1.2.3.4';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['email'] = $leakedSecret;
$_POST['password'] = 'wrong';
$_POST['csrf_token'] = itm_get_csrf_token();

echo "Simulating failed login with identifier: $leakedSecret\n";

$_SERVER['REMOTE_ADDR'] = '1.2.3.4';

// Handling environment-specific schema differences in Beta
$oldTriggerRes = mysqli_query($conn, "SHOW CREATE TRIGGER trg_attempts_audit_insert");
$oldTriggerRow = mysqli_fetch_assoc($oldTriggerRes);
$oldTriggerSql = $oldTriggerRow['SQL Original Statement'] ?? '';

mysqli_query($conn, "DROP TRIGGER IF EXISTS trg_attempts_audit_insert");
// Temporary simple trigger to allow insertion despite missing employee_id column in trigger
mysqli_query($conn, "CREATE TRIGGER `trg_attempts_audit_insert` AFTER INSERT ON `attempts` FOR EACH ROW
BEGIN
  INSERT INTO `audit_logs` (`company_id`, `table_name`, `record_id`, `action`, `new_values`)
  VALUES (1, 'attempts', NEW.id, 'INSERT', JSON_OBJECT('email', NEW.email));
END");

ob_start();
include __DIR__ . '/../login.php';
ob_end_clean();

// Restore original trigger
if ($oldTriggerSql) {
    mysqli_query($conn, "DROP TRIGGER IF EXISTS trg_attempts_audit_insert");
    mysqli_query($conn, $oldTriggerSql);
}

// 2. Verify the secret is in the `attempts` table
$stmt = mysqli_prepare($conn, "SELECT email FROM attempts WHERE email = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $leakedSecret);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($row && $row['email'] === $leakedSecret) {
    echo "[FAIL] VULNERABLE: Sensitive data '$leakedSecret' leaked into the attempts table.\n";
} else {
    echo "[PASS] Data not found in attempts table.\n";
}

// Cleanup
mysqli_query($conn, "DELETE FROM attempts WHERE email = '" . mysqli_real_escape_string($conn, $leakedSecret) . "'");
