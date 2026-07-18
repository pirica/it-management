<?php
/**
 * Reproduction & Diagnostics script for IDOR in modules/contacts/api/inline_edit.php.
 *
 * CLI: php scripts/repro_contacts_idor.php
 * Browser: scripts/repro_contacts_idor.php
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('PoC: Contacts IDOR (Diagnostic)');

$nl = itm_script_output_nl();
$company_id = 1;

echo colorText('[DEBUG] Starting Contacts IDOR Diagnostics...', 'info') . $nl;

if (!$conn || !($conn instanceof mysqli)) {
    echo colorText('[ERROR] Database connection is invalid or not a mysqli instance.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

// Why: Stale session @app_employee_id (0 / deleted id) breaks employees INSERT via audit_logs FK.
if (isset($_SESSION)) {
    $_SESSION['employee_id'] = null;
    $_SESSION['company_id'] = null;
}
itm_script_test_employee_clear_audit_context($conn);

echo '[DEBUG] Dynamically resolving valid Foreign Keys for company ID ' . $company_id . '...' . $nl;

$role_id = 0;
$roleStmt = mysqli_prepare(
    $conn,
    "SELECT id, name FROM employee_roles WHERE company_id = ? AND LOWER(name) != 'admin' ORDER BY id ASC LIMIT 1"
);
if ($roleStmt) {
    mysqli_stmt_bind_param($roleStmt, 'i', $company_id);
    if (mysqli_stmt_execute($roleStmt)) {
        $roleRes = mysqli_stmt_get_result($roleStmt);
        if ($roleRes && ($row = mysqli_fetch_assoc($roleRes))) {
            $role_id = (int)$row['id'];
            echo "  -> Found non-admin role: '" . $row['name'] . "' (ID: $role_id)" . $nl;
        }
    }
    mysqli_stmt_close($roleStmt);
}
if ($role_id <= 0) {
    echo colorText('[FATAL ERROR] No non-admin role for company ' . $company_id . '.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$access_level_id = 0;
$accessStmt = mysqli_prepare($conn, 'SELECT id, name FROM access_levels WHERE company_id = ? ORDER BY id ASC LIMIT 1');
if ($accessStmt) {
    mysqli_stmt_bind_param($accessStmt, 'i', $company_id);
    if (mysqli_stmt_execute($accessStmt)) {
        $accessRes = mysqli_stmt_get_result($accessStmt);
        if ($accessRes && ($row = mysqli_fetch_assoc($accessRes))) {
            $access_level_id = (int)$row['id'];
            echo "  -> Found access level: '" . $row['name'] . "' (ID: $access_level_id)" . $nl;
        }
    }
    mysqli_stmt_close($accessStmt);
}
if ($access_level_id <= 0) {
    echo colorText('[FATAL ERROR] No access level for company ' . $company_id . '.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$employment_status_id = function_exists('itm_employee_resolve_active_status_id')
    ? (int)itm_employee_resolve_active_status_id($conn, $company_id)
    : 0;
if ($employment_status_id <= 0) {
    $statusStmt = mysqli_prepare($conn, 'SELECT id, name FROM employee_statuses WHERE company_id = ? ORDER BY id ASC LIMIT 1');
    if ($statusStmt) {
        mysqli_stmt_bind_param($statusStmt, 'i', $company_id);
        if (mysqli_stmt_execute($statusStmt)) {
            $statusRes = mysqli_stmt_get_result($statusStmt);
            if ($statusRes && ($row = mysqli_fetch_assoc($statusRes))) {
                $employment_status_id = (int)$row['id'];
                echo "  -> Found employment status: '" . $row['name'] . "' (ID: $employment_status_id)" . $nl;
            }
        }
        mysqli_stmt_close($statusStmt);
    }
} else {
    echo "  -> Found employment status: 'Active' (ID: $employment_status_id)" . $nl;
}
if ($employment_status_id <= 0) {
    echo colorText('[FATAL ERROR] No employment status for company ' . $company_id . '.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo '[DEBUG] Attempting to create Attacker employee...' . $nl;
$attacker = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-idor-attacker',
    'role_id' => $role_id,
    'access_level_id' => $access_level_id,
    'employment_status_id' => $employment_status_id,
]);
if (!$attacker) {
    echo colorText('[FATAL ERROR] Failed to create attacker employee: ' . mysqli_error($conn), 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$attacker['id']);
echo colorText('[DEBUG] Attacker created successfully.', 'pass') . $nl;

echo '[DEBUG] Attempting to create Victim employee...' . $nl;
$victim = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-idor-victim',
    'role_id' => $role_id,
    'access_level_id' => $access_level_id,
    'employment_status_id' => $employment_status_id,
]);
if (!$victim) {
    echo colorText('[FATAL ERROR] Failed to create victim employee: ' . mysqli_error($conn), 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$victim['id']);
echo colorText('[DEBUG] Victim created successfully.', 'pass') . $nl;

echo 'Attacker ID: ' . $attacker['id'] . $nl;
echo 'Victim ID: ' . $victim['id'] . $nl;
echo 'Victim Email before attack: ' . $victim['email'] . $nl;

$postData = [
    'type' => 'emp',
    'id' => $victim['id'],
    'field' => 'work_email',
    'value' => 'pwned@example.com',
    'csrf_token' => 'test_token',
];

/**
 * @param string $script_path
 * @param array $session_data
 * @param array $post_data
 * @return string|null
 */
function run_contacts_request($script_path, $session_data, $post_data = [])
{
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_contacts');
    $session_str = serialize($session_data);
    $configPath = realpath(__DIR__ . '/../config/config.php');

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/contacts/api/inline_edit.php';
\$_SERVER['SCRIPT_FILENAME'] = " . var_export($script_path, true) . ";

require " . var_export($configPath, true) . ";

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$_POST = " . var_export($post_data, true) . ";

chdir(dirname(" . var_export($script_path, true) . "));
include basename(" . var_export($script_path, true) . ");
";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    @unlink($tmp_file);
    return $output;
}

$modulePath = realpath(__DIR__ . '/../modules/contacts/api/inline_edit.php');
$session = [
    'employee_id' => (int)$attacker['id'],
    'company_id' => $company_id,
    'username' => $attacker['username'],
    'csrf_token' => 'test_token',
];

echo '[DEBUG] Sending POST request simulating IDOR update on inline_edit.php...' . $nl;
$output = run_contacts_request($modulePath, $session, $postData);

echo 'API Output: ' . $output . $nl;

$checkStmt = mysqli_prepare($conn, 'SELECT work_email FROM employees WHERE id = ? LIMIT 1');
$victimEmail = '';
if ($checkStmt) {
    $victimId = (int)$victim['id'];
    mysqli_stmt_bind_param($checkStmt, 'i', $victimId);
    if (mysqli_stmt_execute($checkStmt)) {
        mysqli_stmt_bind_result($checkStmt, $victimEmail);
        mysqli_stmt_fetch($checkStmt);
    }
    mysqli_stmt_close($checkStmt);
}

if ((string)$victimEmail === 'pwned@example.com') {
    echo colorText('[FAIL] IDOR Vulnerability confirmed: Victim\'s email was updated by another user.', 'fail') . $nl;
} else {
    echo colorText('[PASS] IDOR Vulnerability not found or blocked.', 'pass') . $nl;
}

itm_script_output_end();
