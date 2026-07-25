<?php
/**
 * Verification script for Employees list/view sensitive column disclosure.
 *
 * Why: Ensures password and reset-token columns never appear on list/view HTML.
 *
 * Browser: open scripts/verify_employees_sensitive_view.php (admin login required).
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_employees_auth_sensitive_fields.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Employees Sensitive View Verification');

$nl = itm_script_output_nl();
$companyId = 1;
$failed = false;

/**
 * Why: Subprocess include mirrors browser session without mutating the runner process.
 */
function verify_employees_sensitive_run_isolated($scriptPath, array $session, array $get = [])
{
    $sessionExport = var_export($session, true);
    $getExport = var_export($get, true);
    $dir = var_export(dirname($scriptPath), true);
    $base = var_export(basename($scriptPath), true);
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
\$_SESSION = {$sessionExport};
\$_GET = {$getExport};
chdir({$dir});
ob_start();
include {$base};
echo ob_get_clean();
";
    $tmpFile = tempnam(sys_get_temp_dir(), 'verify_employees_sensitive');
    file_put_contents($tmpFile, $code);
    $output = [];
    exec(PHP_BINARY . ' -d error_reporting=0 ' . escapeshellarg($tmpFile) . ' 2>&1', $output);
    unlink($tmpFile);

    return implode("\n", $output);
}

function verify_employees_sensitive_html_leaks($html, $secretToken)
{
    if ($secretToken !== '' && strpos($html, $secretToken) !== false) {
        return true;
    }
    if (stripos($html, 'reset token hash') !== false || stripos($html, '>Reset Token<') !== false) {
        return true;
    }
    if (stripos($html, '>Password<') !== false && stripos($html, 'password generator') === false) {
        return true;
    }
    if (stripos($html, '>Vault Key Hash<') !== false) {
        return true;
    }

    return false;
}

$testUser = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-employees-sensitive-view']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test employee.', 'fail') . $nl;
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
    echo colorText('[PASS] Sensitive fields removed from Employees uiColumns filter.', 'pass') . $nl;
}

$adminId = 1;
$stmtAdmin = $conn->prepare('SELECT id, username FROM employees WHERE id = ? LIMIT 1');
$stmtAdmin->bind_param('i', $adminId);
$stmtAdmin->execute();
$adminRow = $stmtAdmin->get_result()->fetch_assoc();
$stmtAdmin->close();

if (!is_array($adminRow)) {
    echo colorText('[FAIL] Seed admin employee not found for isolated view check.', 'fail') . $nl;
    $failed = true;
} else {
    $session = [
        'company_id' => $companyId,
        'employee_id' => (int)$adminRow['id'],
        'username' => (string)$adminRow['username'],
    ];

    $viewHtml = verify_employees_sensitive_run_isolated(
        ROOT_PATH . 'modules/employees/view.php',
        $session,
        ['id' => $employeeId]
    );
    if (verify_employees_sensitive_html_leaks($viewHtml, $secretToken)) {
        echo colorText('[FAIL] VULNERABLE: Employees view.php HTML exposes reset-token fields.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] Employees view.php omits reset-token columns.', 'pass') . $nl;
    }

    $listHtml = verify_employees_sensitive_run_isolated(
        ROOT_PATH . 'modules/employees/index.php',
        $session
    );
    if (verify_employees_sensitive_html_leaks($listHtml, $secretToken)) {
        echo colorText('[FAIL] VULNERABLE: Employees index list HTML exposes auth-sensitive columns.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] Employees index list omits auth-sensitive columns.', 'pass') . $nl;
    }
}

$stmtCleanup = $conn->prepare('DELETE FROM employees WHERE id = ?');
$stmtCleanup->bind_param('i', $employeeId);
$stmtCleanup->execute();
$stmtCleanup->close();

itm_script_output_end();
exit($failed ? 1 : 0);
