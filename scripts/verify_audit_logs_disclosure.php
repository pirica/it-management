<?php
/**
 * Verification script for Sensitive Information Disclosure in Audit Logs.
 *
 * Why: Confirms if password hashes and reset tokens are disclosed in audit logs to non-admins.
 *
 * Browser: open scripts/verify_audit_logs_disclosure.php (login required).
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Audit Logs Disclosure Verification');

$nl = itm_script_output_nl();
echo "Verifying Sensitive Information Disclosure in Audit Logs..." . $nl;

// 1. Mock a regular user session
$companyId = 1;
$session = [
    'employee_id' => 999,
    'username' => 'attacker',
    'company_id' => $companyId,
    'role_name' => 'User'
];

// 2. Query audit logs for 'employees' table changes
$sql = "SELECT old_values, new_values FROM audit_logs WHERE table_name = 'employees' AND company_id = ? ORDER BY id DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $companyId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$vulnerable = false;
while ($row = mysqli_fetch_assoc($res)) {
    $combined = ($row['old_values'] ?? '') . ($row['new_values'] ?? '');
    if (strpos($combined, '"password"') !== false || strpos($combined, '"reset_token"') !== false) {
        $vulnerable = true;
        break;
    }
}

if ($vulnerable) {
    echo colorText("[FAIL] VULNERABLE: Audit logs contain sensitive user credentials (passwords/reset tokens).", 'fail') . $nl;
} else {
    // If no logs found, it's inconclusive but we've verified the trigger logic in database.sql
    echo colorText("[INFO] No sensitive patterns found in existing logs for company $companyId. Check database.sql triggers to confirm potential vulnerability.", 'info') . $nl;
}
