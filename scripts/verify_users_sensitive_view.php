<?php
/**
 * Verification script for Users list/view sensitive column disclosure.
 *
 * Why: Ensures password and reset-token columns never appear on list/view HTML.
 *
 * Browser: open scripts/verify_users_sensitive_view.php (admin login required).
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_employees_auth_sensitive_fields.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Users Sensitive View Verification');

$nl = itm_script_output_nl();
$companyId = 1;
$failed = false;

$testUser = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-users-sensitive-view']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$testUser['id'];
itm_script_test_employee_register_teardown($conn, $employeeId);

$secretToken = 'VERIFY_RESET_' . bin2hex(random_bytes(8));
$secretHash = hash('sha256', $secretToken);
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
$stmt = $conn->prepare('UPDATE employees SET reset_token = ?, reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?');
$stmt->bind_param('sssi', $secretToken, $secretHash, $expiresAt, $employeeId);
$stmt->execute();
$stmt->close();

$filtered = itm_employees_auth_filter_ui_columns(array_map(function ($name) {
    return ['Field' => $name];
}, array_merge(['username', 'email'], itm_employees_auth_sensitive_field_names())));

$filteredNames = array_map(function ($col) {
    return $col['Field'] ?? '';
}, $filtered);

foreach (itm_employees_auth_sensitive_field_names() as $sensitiveField) {
    if (in_array($sensitiveField, $filteredNames, true)) {
        echo colorText("[FAIL] uiColumns filter still includes {$sensitiveField}.", 'fail') . $nl;
        $failed = true;
    }
}

if (!$failed) {
    echo colorText('[PASS] Sensitive fields removed from Users uiColumns filter.', 'pass') . $nl;
}

$adminId = 1;
$stmtAdmin = $conn->prepare('SELECT id, username FROM employees WHERE id = ? LIMIT 1');
$stmtAdmin->bind_param('i', $adminId);
$stmtAdmin->execute();
$adminRow = $stmtAdmin->get_result()->fetch_assoc();
$stmtAdmin->close();

if (!is_array($adminRow)) {
    echo colorText('[FAIL] Seed admin user not found for isolated view check.', 'fail') . $nl;
    $failed = true;
} else {
    $session = [
        'company_id' => $companyId,
        'user_id' => (int)$adminRow['id'],
        'username' => (string)$adminRow['username'],
    ];
    $get = ['id' => $employeeId];
    $extraGlobals = ['crud_action' => 'view'];

    $scriptPath = ROOT_PATH . 'modules/employees/index.php';
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
if (session_status() === PHP_SESSION_NONE) session_start();
\$_SESSION['company_id'] = " . var_export($companyId, true) . ";
\$_SESSION['employee_id'] = " . var_export((int)$adminRow['id'], true) . ";
\$_SESSION['username'] = " . var_export((string)$adminRow['username'], true) . ";
\$_GET['id'] = " . var_export($employeeId, true) . ";
\$crud_table = 'employees';
\$crud_title = 'Users';
\$crud_action = 'view';
chdir(" . var_export(dirname($scriptPath), true) . ");
ob_start();
include " . var_export(basename($scriptPath), true) . ";
echo ob_get_clean();
";
    $tmpFile = tempnam(sys_get_temp_dir(), 'verify_users_view');
    file_put_contents($tmpFile, $code);
    $output = [];
    exec(PHP_BINARY . ' -d error_reporting=0 ' . escapeshellarg($tmpFile) . ' 2>&1', $output);
    unlink($tmpFile);
    $html = implode("\n", $output);

    if (strpos($html, $secretToken) !== false || stripos($html, 'reset token hash') !== false || stripos($html, '>Reset Token<') !== false) {
        echo colorText('[FAIL] VULNERABLE: Users view HTML exposes reset-token fields.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] Users view HTML omits reset-token columns.', 'pass') . $nl;
    }
}

$stmtCleanup = $conn->prepare('DELETE FROM employees WHERE id = ?');
$stmtCleanup->bind_param('i', $employeeId);
$stmtCleanup->execute();
$stmtCleanup->close();

itm_script_output_end();
exit($failed ? 1 : 0);
